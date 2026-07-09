<?php declare(strict_types=1);

namespace App\Tests\Transcription;

use App\Entity\Item;
use App\Entity\Network;
use App\Entity\Profile;
use App\Repository\ItemRepository;
use App\Transcription\TranscriptionService;
use App\Transcription\WhisperTranscriber;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TranscriptionServiceTest extends TestCase
{
    private WhisperTranscriber $whisperTranscriber;
    private EntityManagerInterface $em;
    private ItemRepository $itemRepository;
    private TranscriptionService $service;

    protected function setUp(): void
    {
        $this->whisperTranscriber = $this->createMock(WhisperTranscriber::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->itemRepository = $this->createMock(ItemRepository::class);

        $this->service = new TranscriptionService(
            $this->whisperTranscriber,
            $this->em,
            $this->itemRepository,
        );
    }

    private function createItem(bool $withVideo = true): Item
    {
        $profile = new Profile();
        $profile->setId(42);
        $profile->setNetwork(new Network());
        $profile->setIdentifier('test');

        $item = new Item();
        $ref = new \ReflectionProperty(Item::class, 'id');
        $ref->setValue($item, 100);
        $item->setProfile($profile);

        if ($withVideo) {
            $item->setVideoPath('42/100/video.mp4');
        }

        return $item;
    }

    public function testTranscribeSetsCompletedTranscript(): void
    {
        $item = $this->createItem();

        $this->whisperTranscriber->expects($this->once())
            ->method('transcribe')
            ->with('42/100/video.mp4', 42, 100)
            ->willReturn('Hallo Welt');

        $this->em->expects($this->exactly(2))->method('flush');

        $this->service->transcribe($item);

        $this->assertSame('completed', $item->getTranscriptStatus());
        $this->assertSame('Hallo Welt', $item->getTranscript());
        $this->assertNull($item->getTranscriptError());
    }

    public function testTranscribeSetsFailedOnError(): void
    {
        $item = $this->createItem();

        $this->whisperTranscriber->method('transcribe')
            ->willThrowException(new \RuntimeException('whisper.cpp failed'));

        $this->service->transcribe($item);

        $this->assertSame('failed', $item->getTranscriptStatus());
        $this->assertStringContainsString('whisper.cpp failed', (string) $item->getTranscriptError());
        $this->assertNull($item->getTranscript());
    }

    public function testTranscribeSkipsItemWithoutVideo(): void
    {
        $item = $this->createItem(withVideo: false);
        $item->setTranscriptStatus('pending');

        $this->whisperTranscriber->expects($this->never())->method('transcribe');
        $this->em->expects($this->once())->method('flush');

        $this->service->transcribe($item);

        $this->assertNull($item->getTranscriptStatus());
    }

    public function testTranscribeSkipsItemWithoutProfile(): void
    {
        $item = new Item();

        $this->whisperTranscriber->expects($this->never())->method('transcribe');

        $this->service->transcribe($item);

        $this->assertNull($item->getTranscriptStatus());
    }

    public function testQueueItemSetsPendingAndClearsError(): void
    {
        $item = $this->createItem();
        $item->setTranscriptStatus('failed');
        $item->setTranscriptError('boom');

        $this->em->expects($this->once())->method('flush');

        $this->service->queueItem($item);

        $this->assertSame('pending', $item->getTranscriptStatus());
        $this->assertNull($item->getTranscriptError());
    }

    public function testQueueProfileQueuesTranscribableItems(): void
    {
        $profile = new Profile();
        $profile->setId(42);
        $profile->setIdentifier('test');
        $profile->setNetwork(new Network());

        $a = $this->createItem();
        $b = $this->createItem();

        $this->itemRepository->expects($this->once())
            ->method('findTranscribableForProfile')
            ->with($profile)
            ->willReturn([$a, $b]);
        $this->itemRepository->expects($this->never())->method('findBy');
        $this->em->expects($this->once())->method('flush');

        $count = $this->service->queueProfile($profile);

        $this->assertSame(2, $count);
        $this->assertSame('pending', $a->getTranscriptStatus());
        $this->assertSame('pending', $b->getTranscriptStatus());
    }

    public function testQueueProfileWithForceQueuesAllVideoItems(): void
    {
        $profile = new Profile();
        $profile->setId(42);
        $profile->setIdentifier('test');
        $profile->setNetwork(new Network());

        $withVideo = $this->createItem();
        $withoutVideo = $this->createItem(withVideo: false);

        $this->itemRepository->expects($this->once())
            ->method('findBy')
            ->with(['profile' => $profile])
            ->willReturn([$withVideo, $withoutVideo]);
        $this->itemRepository->expects($this->never())->method('findTranscribableForProfile');

        $count = $this->service->queueProfile($profile, force: true);

        $this->assertSame(1, $count);
        $this->assertSame('pending', $withVideo->getTranscriptStatus());
        $this->assertNull($withoutVideo->getTranscriptStatus());
    }

    public function testTranscribePendingItems(): void
    {
        $item = $this->createItem();
        $item->setTranscriptStatus('pending');

        $this->itemRepository->expects($this->once())
            ->method('findBy')
            ->with(['transcriptStatus' => 'pending'], ['id' => 'ASC'], null)
            ->willReturn([$item]);

        $this->whisperTranscriber->method('transcribe')->willReturn('Text');

        $count = $this->service->transcribePendingItems();

        $this->assertSame(1, $count);
        $this->assertSame('completed', $item->getTranscriptStatus());
        $this->assertSame('Text', $item->getTranscript());
    }
}
