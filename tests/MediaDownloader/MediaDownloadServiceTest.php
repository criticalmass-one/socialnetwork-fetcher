<?php declare(strict_types=1);

namespace App\Tests\MediaDownloader;

use App\Entity\Item;
use App\Entity\Network;
use App\Entity\Profile;
use App\MediaDownloader\MediaDownloadService;
use App\MediaDownloader\MediaUrlExtractor;
use App\MediaDownloader\PhotoDownloader;
use App\MediaDownloader\VideoDownloader;
use App\MediaDownloader\YtDlpPhotoDownloader;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class MediaDownloadServiceTest extends TestCase
{
    private MediaUrlExtractor $extractor;
    private PhotoDownloader $photoDownloader;
    private VideoDownloader $videoDownloader;
    private YtDlpPhotoDownloader $ytDlpPhotoDownloader;
    private EntityManagerInterface $em;
    private ItemRepository $itemRepository;
    private MediaDownloadService $service;

    protected function setUp(): void
    {
        $this->extractor = $this->createMock(MediaUrlExtractor::class);
        $this->photoDownloader = $this->createMock(PhotoDownloader::class);
        $this->videoDownloader = $this->createMock(VideoDownloader::class);
        $this->ytDlpPhotoDownloader = $this->createMock(YtDlpPhotoDownloader::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->itemRepository = $this->createMock(ItemRepository::class);

        $this->service = new MediaDownloadService(
            $this->extractor,
            $this->photoDownloader,
            $this->videoDownloader,
            $this->ytDlpPhotoDownloader,
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

    public function testDownloadMediaUsesYtDlpForInstagram(): void
    {
        $network = new Network();
        $network->setIdentifier('instagram_profile');
        $profile = new Profile();
        $profile->setId(42);
        $profile->setNetwork($network);
        $profile->setIdentifier('test');

        $item = new Item();
        $ref = new \ReflectionProperty(Item::class, 'id');
        $ref->setValue($item, 100);
        $item->setProfile($profile);
        $item->setPermalink('https://www.instagram.com/p/abc123');

        $this->ytDlpPhotoDownloader->method('isAvailable')->willReturn(true);
        $this->ytDlpPhotoDownloader->expects($this->once())
            ->method('download')
            ->with('https://www.instagram.com/p/abc123', 42, 100)
            ->willReturn(['42/100/photo_00001.jpg', '42/100/photo_00002.jpg']);

        $this->extractor->method('extractVideoUrl')->willReturn(null);

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus());
        $this->assertSame(['42/100/photo_00001.jpg', '42/100/photo_00002.jpg'], $item->getPhotoPaths());
    }

    public function testDownloadMediaFallsBackToThumbnailWhenYtDlpFails(): void
    {
        $network = new Network();
        $network->setIdentifier('instagram_profile');
        $profile = new Profile();
        $profile->setId(42);
        $profile->setNetwork($network);
        $profile->setIdentifier('test');

        $item = new Item();
        $ref = new \ReflectionProperty(Item::class, 'id');
        $ref->setValue($item, 100);
        $item->setProfile($profile);
        $item->setPermalink('https://www.instagram.com/p/abc123');

        $this->ytDlpPhotoDownloader->method('isAvailable')->willReturn(true);
        $this->ytDlpPhotoDownloader->method('download')->willReturn([]);

        $this->extractor->method('extractPhotoUrls')->willReturn(['https://example.com/thumb.jpg']);
        $this->extractor->method('extractVideoUrl')->willReturn(null);

        $this->photoDownloader->expects($this->once())
            ->method('download')
            ->with('https://example.com/thumb.jpg', 42, 100, 0)
            ->willReturn('42/100/photo_0.jpg');

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus());
        $this->assertSame(['42/100/photo_0.jpg'], $item->getPhotoPaths());
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

    public function testInstagramPhotoPostRunsYtDlpPhotoExtractor(): void
    {
        $network = new Network();
        $network->setIdentifier('instagram_photo');

        $profile = new Profile();
        $profile->setId(42);
        $profile->setNetwork($network);
        $profile->setIdentifier('test');

        $item = new Item();
        $ref = new \ReflectionProperty(Item::class, 'id');
        $ref->setValue($item, 100);
        $item->setProfile($profile);
        $item->setPermalink('https://www.instagram.com/p/DYKYyTgjdNe');

        $this->ytDlpPhotoDownloader->method('isAvailable')->willReturn(true);
        $this->ytDlpPhotoDownloader->expects($this->once())
            ->method('download')
            ->with('https://www.instagram.com/p/DYKYyTgjdNe', 42, 100)
            ->willReturn(['42/100/photo_1.jpg']);

        $this->extractor->method('extractVideoUrl')->willReturn(null);

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus());
        $this->assertSame(['42/100/photo_1.jpg'], $item->getPhotoPaths());
    }

    public function testPhotoSuccessWithVideoFailureIsCompletedWithWarning(): void
    {
        $item = $this->createItem();
        $item->setPermalink('https://example.com/post/1');

        $this->extractor->method('extractPhotoUrls')->willReturn(['https://example.com/photo.jpg']);
        $this->extractor->method('extractVideoUrl')->willReturn('https://example.com/post/1');

        $this->photoDownloader->method('download')->willReturn('42/100/photo_0.jpg');

        $this->videoDownloader->method('isAvailable')->willReturn(true);
        $this->videoDownloader->method('download')
            ->willThrowException(new \RuntimeException('yt-dlp failed: There is no video in this post'));

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus(), 'photos succeeded so status should be completed');
        $this->assertSame(['42/100/photo_0.jpg'], $item->getPhotoPaths());
        $this->assertNotNull($item->getMediaError(), 'video failure preserved as warning');
        $this->assertStringContainsString('Video:', $item->getMediaError());
    }

    public function testPhotoFailureWithVideoFailureIsFailed(): void
    {
        $item = $this->createItem();

        $this->extractor->method('extractPhotoUrls')->willReturn(['https://example.com/photo.jpg']);
        $this->extractor->method('extractVideoUrl')->willReturn('https://example.com/post/1');

        $this->photoDownloader->method('download')
            ->willThrowException(new \RuntimeException('photo broke'));

        $this->videoDownloader->method('isAvailable')->willReturn(true);
        $this->videoDownloader->method('download')
            ->willThrowException(new \RuntimeException('video broke'));

        $this->service->downloadMedia($item);

        $this->assertSame('failed', $item->getMediaStatus());
        $this->assertStringContainsString('Photo:', $item->getMediaError());
        $this->assertStringContainsString('Video:', $item->getMediaError());
    }

    public function testVideoDownloadQueuesTranscriptionWhenEnabled(): void
    {
        $network = new Network();
        $profile = new Profile();
        $profile->setId(42);
        $profile->setNetwork($network);
        $profile->setIdentifier('test');
        $profile->setTranscribeVideos(true);

        $item = new Item();
        $ref = new \ReflectionProperty(Item::class, 'id');
        $ref->setValue($item, 100);
        $item->setProfile($profile);

        $this->extractor->method('extractPhotoUrls')->willReturn([]);
        $this->extractor->method('extractVideoUrl')->willReturn('https://example.com/post/1');
        $this->videoDownloader->method('isAvailable')->willReturn(true);
        $this->videoDownloader->method('download')->willReturn('42/100/video.mp4');

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus());
        $this->assertSame('42/100/video.mp4', $item->getVideoPath());
        $this->assertSame('pending', $item->getTranscriptStatus());
    }

    public function testVideoDownloadDoesNotQueueTranscriptionWhenDisabled(): void
    {
        $item = $this->createItem();

        $this->extractor->method('extractPhotoUrls')->willReturn([]);
        $this->extractor->method('extractVideoUrl')->willReturn('https://example.com/post/1');
        $this->videoDownloader->method('isAvailable')->willReturn(true);
        $this->videoDownloader->method('download')->willReturn('42/100/video.mp4');

        $this->service->downloadMedia($item);

        $this->assertSame('completed', $item->getMediaStatus());
        $this->assertNull($item->getTranscriptStatus());
    }

    public function testQueueItemSetsPendingAndClearsError(): void
    {
        $item = $this->createItem();
        $item->setMediaStatus('failed');
        $item->setMediaError('boom');

        $this->em->expects($this->once())->method('flush');

        $this->service->queueItem($item);

        $this->assertSame('pending', $item->getMediaStatus());
        $this->assertNull($item->getMediaError());
    }

    public function testQueueProfileQueuesNewAndFailedItems(): void
    {
        $profile = new Profile();
        $profile->setId(42);
        $profile->setIdentifier('test');
        $profile->setNetwork(new Network());

        $a = $this->createItem();
        $b = $this->createItem();

        $this->itemRepository->expects($this->once())
            ->method('findNewOrFailedForProfile')
            ->with($profile)
            ->willReturn([$a, $b]);
        $this->itemRepository->expects($this->never())->method('findBy');
        $this->em->expects($this->once())->method('flush');

        $count = $this->service->queueProfile($profile);

        $this->assertSame(2, $count);
        $this->assertSame('pending', $a->getMediaStatus());
        $this->assertSame('pending', $b->getMediaStatus());
    }

    public function testQueueProfileWithForceQueuesAllItems(): void
    {
        $profile = new Profile();
        $profile->setId(42);
        $profile->setIdentifier('test');
        $profile->setNetwork(new Network());

        $a = $this->createItem();

        $this->itemRepository->expects($this->once())
            ->method('findBy')
            ->with(['profile' => $profile])
            ->willReturn([$a]);
        $this->itemRepository->expects($this->never())->method('findNewOrFailedForProfile');

        $count = $this->service->queueProfile($profile, force: true);

        $this->assertSame(1, $count);
        $this->assertSame('pending', $a->getMediaStatus());
    }

    public function testDownloadPendingItemsRespectsProfileFlags(): void
    {
        $profile = new Profile();
        $profile->setId(42);
        $profile->setIdentifier('test');
        $profile->setNetwork(new Network());
        $profile->setSavePhotos(true);
        $profile->setSaveVideos(false);

        $item = $this->createItem();
        $item->setProfile($profile);
        $item->setMediaStatus('pending');

        $this->itemRepository->expects($this->once())
            ->method('findBy')
            ->with(['mediaStatus' => 'pending'], ['id' => 'ASC'], null)
            ->willReturn([$item]);

        // savePhotos=true → photos attempted (none found here); saveVideos=false → video skipped
        $this->extractor->method('extractPhotoUrls')->willReturn([]);
        $this->extractor->expects($this->never())->method('extractVideoUrl');

        $count = $this->service->downloadPendingItems();

        $this->assertSame(1, $count);
        $this->assertSame('completed', $item->getMediaStatus());
    }
}
