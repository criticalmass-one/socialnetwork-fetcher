<?php declare(strict_types=1);

namespace App\Tests\MediaDownloader;

use App\Entity\Item;
use App\Entity\Network;
use App\Entity\Profile;
use App\MediaDownloader\MediaDownloadService;
use App\MediaDownloader\MediaUrlExtractor;
use App\MediaDownloader\PhotoDownloader;
use App\MediaDownloader\VideoDownloader;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class MediaDownloadServiceTest extends TestCase
{
    private MediaUrlExtractor $extractor;
    private PhotoDownloader $photoDownloader;
    private VideoDownloader $videoDownloader;
    private EntityManagerInterface $em;
    private ItemRepository $itemRepository;
    private MediaDownloadService $service;

    protected function setUp(): void
    {
        $this->extractor = $this->createMock(MediaUrlExtractor::class);
        $this->photoDownloader = $this->createMock(PhotoDownloader::class);
        $this->videoDownloader = $this->createMock(VideoDownloader::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->itemRepository = $this->createMock(ItemRepository::class);

        $this->service = new MediaDownloadService(
            $this->extractor,
            $this->photoDownloader,
            $this->videoDownloader,
            $this->em,
            $this->itemRepository,
        );
    }

    private function createItem(): Item
    {
        $network = new Network();
        $profile = new Profile();
        $profile->setId(42);
        $profile->setNetwork($network);
        $profile->setIdentifier('test');

        $item = new Item();
        $ref = new \ReflectionProperty(Item::class, 'id');
        $ref->setValue($item, 100);
        $item->setProfile($profile);

        return $item;
    }

    public function testDownloadMediaSinglePhoto(): void
    {
        $item = $this->createItem();

        $this->extractor->method('extractPhotoUrls')->willReturn(['https://example.com/photo.jpg']);
        $this->extractor->method('extractVideoUrl')->willReturn(null);

        $this->photoDownloader->expects($this->once())
            ->method('download')
            ->with('https://example.com/photo.jpg', 42, 100, 0)
            ->willReturn('42/100/photo_0.jpg');

        $this->em->expects($this->exactly(2))->method('flush');

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus());
        $this->assertSame(['42/100/photo_0.jpg'], $item->getPhotoPaths());
        $this->assertNull($item->getMediaError());
    }

    public function testDownloadMediaMultiplePhotos(): void
    {
        $item = $this->createItem();

        $this->extractor->method('extractPhotoUrls')->willReturn([
            'https://example.com/photo1.jpg',
            'https://example.com/photo2.jpg',
            'https://example.com/photo3.jpg',
        ]);
        $this->extractor->method('extractVideoUrl')->willReturn(null);

        $this->photoDownloader->expects($this->exactly(3))
            ->method('download')
            ->willReturnCallback(fn (string $url, int $profileId, int $itemId, int $index) =>
                sprintf('%d/%d/photo_%d.jpg', $profileId, $itemId, $index)
            );

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus());
        $this->assertSame([
            '42/100/photo_0.jpg',
            '42/100/photo_1.jpg',
            '42/100/photo_2.jpg',
        ], $item->getPhotoPaths());
    }

    public function testDownloadMediaSetsFailedOnError(): void
    {
        $item = $this->createItem();

        $this->extractor->method('extractPhotoUrls')->willReturn(['https://example.com/photo.jpg']);
        $this->extractor->method('extractVideoUrl')->willReturn(null);

        $this->photoDownloader->method('download')
            ->willThrowException(new \RuntimeException('Network error'));

        $this->service->downloadMedia($item);

        $this->assertSame('failed', $item->getMediaStatus());
        $this->assertStringContainsString('Network error', $item->getMediaError());
    }

    public function testDownloadMediaSkipsPhotoWhenFlagIsFalse(): void
    {
        $item = $this->createItem();

        $this->extractor->expects($this->never())->method('extractPhotoUrls');
        $this->extractor->method('extractVideoUrl')->willReturn(null);
        $this->videoDownloader->method('isAvailable')->willReturn(false);

        $this->service->downloadMedia($item, photo: false, video: true);

        $this->assertSame('completed', $item->getMediaStatus());
    }

    public function testDownloadMediaSkipsVideoWhenFlagIsFalse(): void
    {
        $item = $this->createItem();

        $this->extractor->method('extractPhotoUrls')->willReturn([]);
        $this->extractor->expects($this->never())->method('extractVideoUrl');

        $this->service->downloadMedia($item, photo: true, video: false);

        $this->assertSame('completed', $item->getMediaStatus());
    }

    public function testDownloadMediaSkipsItemWithoutProfile(): void
    {
        $item = new Item();

        $this->em->expects($this->never())->method('flush');

        $this->service->downloadMedia($item);
    }

    public function testDownloadNewItemsForProfile(): void
    {
        $profile = new Profile();
        $profile->setId(42);
        $profile->setIdentifier('test');
        $profile->setNetwork(new Network());
        $profile->setSavePhotos(true);
        $profile->setSaveVideos(false);

        $item = $this->createItem();

        $this->itemRepository->method('findBy')
            ->with(['profile' => $profile, 'mediaStatus' => null])
            ->willReturn([$item]);

        $this->extractor->method('extractPhotoUrls')->willReturn([]);

        $this->service->downloadNewItemsForProfile($profile);

        $this->assertSame('completed', $item->getMediaStatus());
    }
}
