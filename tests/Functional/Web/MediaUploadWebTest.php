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
