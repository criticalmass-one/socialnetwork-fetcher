<?php declare(strict_types=1);

namespace App\Tests\Functional\Web;

use App\Entity\Item;
use App\Entity\Profile;
use App\Tests\Functional\AbstractWebTestCase;

/**
 * A video download that fails while photos succeed keeps mediaStatus
 * "completed"; the item detail page must still surface the video error so the
 * missing video is visible, not mistaken for a full success.
 */
class ItemMediaFailureWebTest extends AbstractWebTestCase
{
    public function testCompletedItemWithVideoFailureShowsWarning(): void
    {
        $id = $this->createItem('completed', 'Video: yt-dlp failed: HTTP Error 404: Not Found', ['90001/1/photo_0.jpg']);

        $this->loginAsAdmin();
        $this->client->request('GET', sprintf('/items/%d', $id));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-warning');
        self::assertSelectorTextContains('.alert-warning', 'HTTP Error 404');
        self::assertSelectorTextContains('.badge.bg-warning', 'Video fehlgeschlagen');
    }

    public function testFailedItemShowsDangerError(): void
    {
        $id = $this->createItem('failed', 'Photo: HTTP 403; Video: yt-dlp failed', []);

        $this->loginAsAdmin();
        $this->client->request('GET', sprintf('/items/%d', $id));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-danger');
    }

    /**
     * @param list<string> $photoPaths
     */
    private function createItem(string $mediaStatus, string $mediaError, array $photoPaths): int
    {
        $em = $this->entityManager();
        /** @var Profile $profile */
        $profile = $em->getRepository(Profile::class)->find(90001);

        $item = new Item();
        $item->setProfile($profile);
        $item->setUniqueIdentifier('media-failure-' . $mediaStatus);
        $item->setPermalink('https://www.instagram.com/p/DavbOT2kxtJ');
        $item->setText('Media failure test');
        $item->setDateTime(new \DateTimeImmutable('-1 hour'));
        $item->setMediaStatus($mediaStatus);
        $item->setMediaError($mediaError);
        $item->setPhotoPaths($photoPaths);
        $em->persist($item);
        $em->flush();

        return $item->getId();
    }
}
