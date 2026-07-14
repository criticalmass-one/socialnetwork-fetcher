<?php declare(strict_types=1);

namespace App\Tests\Unit\Push;

use App\Entity\Group;
use App\Entity\Profile;
use App\Entity\PushSubscription;
use App\Push\GroupPushNotifier;
use App\Push\WebPushSenderInterface;
use App\Repository\GroupRepository;
use App\Repository\PushSubscriptionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class GroupPushNotifierTest extends TestCase
{
    public function testBundlesNewItemsPerGroupAndSendsOnce(): void
    {
        $group = $this->group(1, 'Wahlkampf', 'slug-1');
        $subscription = (new PushSubscription())->setGroup($group)->setEndpoint('e')->setP256dh('p')->setAuth('a');

        $groupRepo = $this->createMock(GroupRepository::class);
        $groupRepo->method('findByProfile')->willReturn([$group]);

        $subRepo = $this->createMock(PushSubscriptionRepository::class);
        $subRepo->method('findByGroup')->with($group)->willReturn([$subscription]);

        $sender = $this->createMock(WebPushSenderInterface::class);
        $sender->method('isEnabled')->willReturn(true);
        $sender->expects(self::once())
            ->method('send')
            ->with(
                [$subscription],
                self::callback(fn (array $p): bool => $p['body'] === '3 neue Beiträge'
                    && $p['title'] === 'Wahlkampf'
                    && str_contains($p['url'], 'slug-1')),
            );

        $notifier = new GroupPushNotifier($groupRepo, $subRepo, $sender, $this->urlGenerator());

        // Two profiles of the same group in one run: 2 + 1 = 3 bundled.
        $notifier->recordNewItems(new Profile(), 2);
        $notifier->recordNewItems(new Profile(), 1);
        $notifier->dispatch();
    }

    public function testSingularBody(): void
    {
        $group = $this->group(1, 'G', 's');
        $sub = (new PushSubscription())->setGroup($group)->setEndpoint('e')->setP256dh('p')->setAuth('a');

        $groupRepo = $this->createMock(GroupRepository::class);
        $groupRepo->method('findByProfile')->willReturn([$group]);
        $subRepo = $this->createMock(PushSubscriptionRepository::class);
        $subRepo->method('findByGroup')->willReturn([$sub]);

        $sender = $this->createMock(WebPushSenderInterface::class);
        $sender->method('isEnabled')->willReturn(true);
        $sender->expects(self::once())->method('send')
            ->with(self::anything(), self::callback(fn (array $p): bool => $p['body'] === '1 neuer Beitrag'));

        $notifier = new GroupPushNotifier($groupRepo, $subRepo, $sender, $this->urlGenerator());
        $notifier->recordNewItems(new Profile(), 1);
        $notifier->dispatch();
    }

    public function testSkipsGroupsWithoutPublicPage(): void
    {
        $group = $this->group(1, 'G', 's');
        $group->setPublicPageEnabled(false);

        $groupRepo = $this->createMock(GroupRepository::class);
        $groupRepo->method('findByProfile')->willReturn([$group]);
        $subRepo = $this->createMock(PushSubscriptionRepository::class);

        $sender = $this->createMock(WebPushSenderInterface::class);
        $sender->method('isEnabled')->willReturn(true);
        $sender->expects(self::never())->method('send');

        $notifier = new GroupPushNotifier($groupRepo, $subRepo, $sender, $this->urlGenerator());
        $notifier->recordNewItems(new Profile(), 5);
        $notifier->dispatch();
    }

    public function testDoesNothingWhenSenderDisabled(): void
    {
        $groupRepo = $this->createMock(GroupRepository::class);
        $groupRepo->expects(self::never())->method('findByProfile');
        $subRepo = $this->createMock(PushSubscriptionRepository::class);

        $sender = $this->createMock(WebPushSenderInterface::class);
        $sender->method('isEnabled')->willReturn(false);
        $sender->expects(self::never())->method('send');

        $notifier = new GroupPushNotifier($groupRepo, $subRepo, $sender, $this->urlGenerator());
        $notifier->recordNewItems(new Profile(), 5);
        $notifier->dispatch();
    }

    private function group(int $id, string $name, string $slug): Group
    {
        $group = new Group();
        $group->setName($name);
        $group->setPublicPageEnabled(true);
        $group->setPublicSlug($slug);

        $ref = new \ReflectionProperty(Group::class, 'id');
        $ref->setValue($group, $id);

        return $group;
    }

    private function urlGenerator(): UrlGeneratorInterface
    {
        $generator = $this->createMock(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []): string => 'https://example.test/p/' . ($params['slug'] ?? ''),
        );

        return $generator;
    }
}
