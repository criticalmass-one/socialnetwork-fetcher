<?php declare(strict_types=1);

namespace App\PublicPage;

use App\Entity\Group;
use App\Entity\PublicPageEvent;
use App\Repository\PublicPageEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Records public-page views and outbound-link clicks (one timestamped row each)
 * and aggregates them into the admin-only statistics summary.
 */
class PublicPageAnalytics
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PublicPageEventRepository $repository,
    ) {
    }

    public function recordView(Group $group): void
    {
        $this->record($group, PublicPageEvent::TYPE_VIEW, null);
    }

    public function recordClick(Group $group, ?string $url): void
    {
        $url = $url !== null ? mb_substr(trim($url), 0, 500) : null;
        $this->record($group, PublicPageEvent::TYPE_CLICK, $url !== '' ? $url : null);
    }

    /**
     * @return array{views: int, clicks: int, views7: int, views30: int, topLinks: list<array{url: string, count: int}>}
     */
    public function summary(Group $group): array
    {
        return [
            'views' => $this->repository->countByGroupAndType($group, PublicPageEvent::TYPE_VIEW),
            'clicks' => $this->repository->countByGroupAndType($group, PublicPageEvent::TYPE_CLICK),
            'views7' => $this->repository->countByGroupAndType($group, PublicPageEvent::TYPE_VIEW, new \DateTimeImmutable('-7 days')),
            'views30' => $this->repository->countByGroupAndType($group, PublicPageEvent::TYPE_VIEW, new \DateTimeImmutable('-30 days')),
            'topLinks' => $this->repository->topClickedUrls($group, 10),
        ];
    }

    private function record(Group $group, string $type, ?string $url): void
    {
        $event = (new PublicPageEvent())
            ->setGroup($group)
            ->setType($type)
            ->setUrl($url)
            ->setOccurredAt(new \DateTimeImmutable());

        $this->entityManager->persist($event);
        $this->entityManager->flush();
    }
}
