<?php declare(strict_types=1);

namespace App\Push;

use App\Entity\Group;
use App\Entity\Profile;
use App\Repository\GroupRepository;
use App\Repository\PushSubscriptionRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Collects new-item counts per group during a fetch run and, once dispatched,
 * sends one bundled Web Push notification per group ("3 neue Beiträge …") to
 * its public-page subscribers. Only public-page-enabled groups are notified,
 * since that is where visitors subscribe and where the notification links to.
 */
class GroupPushNotifier
{
    /** @var array<int, array{group: Group, count: int}> */
    private array $pending = [];

    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly PushSubscriptionRepository $subscriptionRepository,
        private readonly WebPushSenderInterface $sender,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function recordNewItems(Profile $profile, int $count): void
    {
        if ($count <= 0 || !$this->sender->isEnabled()) {
            return;
        }

        foreach ($this->groupRepository->findByProfile($profile) as $group) {
            if (!$group->isPublicPageEnabled() || $group->getPublicSlug() === null) {
                continue;
            }

            $id = $group->getId();
            if (!isset($this->pending[$id])) {
                $this->pending[$id] = ['group' => $group, 'count' => 0];
            }
            $this->pending[$id]['count'] += $count;
        }
    }

    public function dispatch(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $entry) {
            $this->notifyGroup($entry['group'], $entry['count']);
        }
    }

    private function notifyGroup(Group $group, int $count): void
    {
        if ($count <= 0) {
            return;
        }

        $subscriptions = $this->subscriptionRepository->findByGroup($group);
        if ($subscriptions === []) {
            return;
        }

        $this->sender->send($subscriptions, [
            'title' => $group->getPublicTitle() ?: ($group->getName() ?? 'Neue Beiträge'),
            'body' => $count === 1 ? '1 neuer Beitrag' : sprintf('%d neue Beiträge', $count),
            'url' => $this->urlGenerator->generate(
                'app_public_group',
                ['slug' => $group->getPublicSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ]);
    }
}
