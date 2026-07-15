<?php declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Item;
use App\Entity\Network;
use App\Entity\Profile;
use App\Tests\Functional\AbstractWebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaUploadWebTest extends AbstractWebTestCase
{
    private const PERMALINK = 'https://www.instagram.com/p/UploadTest1/';

    public function testUploadAttachesVideoAndQueuesTranscription(): void
    {
        $this->createItemWithTranscription();

        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => self::PERMALINK],
            ['video' => $this->fakeUpload('clip.mp4', 'video/mp4')],
            ['HTTP_AUTHORIZATION' => 'Bearer test-upload-token'],
        );

        self::assertResponseIsSuccessful();

        $em = $this->entityManager();
        $em->clear();
        $item = $em->getRepository(Item::class)->findOneBy(['permalink' => self::PERMALINK]);

        self::assertNotNull($item->getVideoPath());
        self::assertSame('completed', $item->getMediaStatus());
        self::assertSame('pending', $item->getTranscriptStatus(), 'transcription queued for a video on a transcribe-enabled profile');
    }

    public function testMatchesPermalinkDespiteTrailingSlashMismatch(): void
    {
        $this->createItemWithTranscription(); // stored with a trailing slash

        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => rtrim(self::PERMALINK, '/')], // sent without one
            ['video' => $this->fakeUpload('clip.mp4', 'video/mp4')],
            ['HTTP_AUTHORIZATION' => 'Bearer test-upload-token'],
        );

        self::assertResponseIsSuccessful();
    }

    public function testRejectsWrongToken(): void
    {
        $this->createItemWithTranscription();

        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => self::PERMALINK],
            ['video' => $this->fakeUpload('clip.mp4', 'video/mp4')],
            ['HTTP_AUTHORIZATION' => 'Bearer wrong'],
        );

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownPermalinkIs404(): void
    {
        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => 'https://www.instagram.com/p/DoesNotExist/'],
            ['video' => $this->fakeUpload('clip.mp4', 'video/mp4')],
            ['HTTP_AUTHORIZATION' => 'Bearer test-upload-token'],
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function testMatchesReelUrlAgainstStoredPostUrl(): void
    {
        $this->createItemWithTranscription(); // stored as .../p/UploadTest1/

        // A video post's canonical URL is often the /reel/ form; it must still
        // match the stored /p/ item instead of creating a duplicate.
        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => 'https://www.instagram.com/reel/UploadTest1/'],
            ['video' => $this->fakeUpload('clip.mp4', 'video/mp4')],
            ['HTTP_AUTHORIZATION' => 'Bearer test-upload-token'],
        );

        self::assertResponseIsSuccessful();

        $em = $this->entityManager();
        $em->clear();
        self::assertSame(1, $em->getRepository(Item::class)->count(['uniqueIdentifier' => 'upload-test-1']), 'no duplicate created');
        $item = $em->getRepository(Item::class)->findOneBy(['uniqueIdentifier' => 'upload-test-1']);
        self::assertNotNull($item->getVideoPath(), 'media attached to the existing item');
    }

    public function testCreatesItemWhenMissingAndAuthorMatchesProfile(): void
    {
        $em = $this->entityManager();
        $network = $em->getRepository(Network::class)->findOneBy(['identifier' => 'instagram_profile']);

        $profile = new Profile();
        $profile->setId(95002);
        $profile->setIdentifier('https://www.instagram.com/uploadtest2/');
        $profile->setNetwork($network);
        $profile->setCreatedAt(new \DateTimeImmutable());
        $profile->setSaveVideos(true);
        $profile->setTranscribeVideos(true);
        $em->persist($profile);
        $em->flush();

        $permalink = 'https://www.instagram.com/p/BrandNew1/';

        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => $permalink, 'author' => 'uploadtest2', 'text' => 'Neuer Post', 'dateTime' => '2026-07-15T10:00:00+00:00'],
            ['video' => $this->fakeUpload('clip.mp4', 'video/mp4')],
            ['HTTP_AUTHORIZATION' => 'Bearer test-upload-token'],
        );

        self::assertResponseIsSuccessful();

        $em->clear();
        // Stored without the trailing slash to match the RSS.app fetcher's uniqueIdentifier.
        $item = $em->getRepository(Item::class)->findOneBy(['permalink' => rtrim($permalink, '/')]);
        self::assertNotNull($item, 'item was created for the new permalink');
        self::assertNotNull($item->getVideoPath());
        self::assertSame('pending', $item->getTranscriptStatus());
    }

    public function testUnknownAuthorIs422(): void
    {
        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => 'https://www.instagram.com/p/Whatever/', 'author' => 'nobody_tracked'],
            ['video' => $this->fakeUpload('clip.mp4', 'video/mp4')],
            ['HTTP_AUTHORIZATION' => 'Bearer test-upload-token'],
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testRejectsUnsupportedType(): void
    {
        $this->createItemWithTranscription();

        $this->client->request(
            'POST',
            '/media-upload',
            ['permalink' => self::PERMALINK],
            ['video' => $this->fakeUpload('clip.exe', 'application/octet-stream')],
            ['HTTP_AUTHORIZATION' => 'Bearer test-upload-token'],
        );

        self::assertResponseStatusCodeSame(415);
    }

    private function createItemWithTranscription(): void
    {
        $em = $this->entityManager();
        $network = $em->getRepository(Network::class)->findOneBy(['identifier' => 'instagram_profile']);

        $profile = new Profile();
        $profile->setId(95001);
        $profile->setIdentifier('https://www.instagram.com/uploadtest/');
        $profile->setNetwork($network);
        $profile->setCreatedAt(new \DateTimeImmutable());
        $profile->setSaveVideos(true);
        $profile->setTranscribeVideos(true);
        $em->persist($profile);

        $item = new Item();
        $item->setProfile($profile);
        $item->setUniqueIdentifier('upload-test-1');
        $item->setPermalink(self::PERMALINK);
        $item->setText('Upload test');
        $item->setDateTime(new \DateTimeImmutable('-1 hour'));
        $item->setMediaStatus('completed');
        $item->setMediaError('Video: yt-dlp failed');
        $em->persist($item);
        $em->flush();
    }

    private function fakeUpload(string $name, string $mime): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, 'dummy-media-bytes');

        return new UploadedFile($tmp, $name, $mime, null, true);
    }
}
