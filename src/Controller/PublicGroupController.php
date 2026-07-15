<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Group;
use App\Entity\Item;
use App\Entity\PushSubscription;
use App\Repository\GroupRepository;
use App\Repository\ItemRepository;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public, unauthenticated feed page for a single group at /p/{slug}.
 *
 * The page is only reachable when the group has publicPageEnabled = true and a
 * slug set. Password-protected groups show an unlock form first; a successful
 * unlock is remembered in the session. Which media (photos/videos/transcripts)
 * and captions are exposed is decided server-side from the group's settings, so
 * disabled media never reaches the browser.
 */
#[Route('/p')]
class PublicGroupController extends AbstractController
{
    private const ITEMS_PER_PAGE = 20;
    private const MEDIA_PATH_PREFIX = '/media/';

    public function __construct(
        private readonly GroupRepository $groupRepository,
        private readonly ItemRepository $itemRepository,
        private readonly UrlHelper $urlHelper,
        private readonly PushSubscriptionRepository $pushSubscriptionRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly \App\PublicPage\PublicPageAnalytics $analytics,
        private readonly \App\PublicPage\OutboundLinkSigner $outboundLinkSigner,
        private readonly string $vapidPublicKey,
    ) {
    }

    #[Route('/{slug}', name: 'app_public_group', methods: ['GET'])]
    public function page(string $slug, Request $request): Response
    {
        $group = $this->requirePublicGroup($slug);
        $this->analytics->recordView($group);

        if ($this->isLocked($group, $request)) {
            return $this->render('public/password.html.twig', [
                'group' => $group,
                'error' => false,
            ]);
        }

        $since = $this->windowStart($group);
        $items = $this->itemRepository->findPaginatedForPublicGroup($group, 1, self::ITEMS_PER_PAGE, $since);
        $total = $this->itemRepository->countForPublicGroup($group, $since);

        return $this->render('public/group.html.twig', [
            'group' => $group,
            'viewItems' => $this->buildViewItems($items, $group),
            'total' => $total,
            'page' => 1,
            'hasMore' => $total > self::ITEMS_PER_PAGE,
            'pushEnabled' => $this->vapidPublicKey !== '',
            'vapidPublicKey' => $this->vapidPublicKey,
        ]);
    }

    #[Route('/{slug}/go', name: 'app_public_group_go', methods: ['GET'])]
    public function trackOutbound(string $slug, Request $request): Response
    {
        $group = $this->requirePublicGroup($slug);

        $url = (string) $request->query->get('u', '');
        $signature = (string) $request->query->get('s', '');
        $isHttp = str_starts_with($url, 'http://') || str_starts_with($url, 'https://');

        // A tampered/unsigned target must not turn this into an open redirect.
        if (!$isHttp || !$this->outboundLinkSigner->verify($url, $signature)) {
            return $this->redirectToRoute('app_public_group', ['slug' => $slug]);
        }

        $this->analytics->recordClick($group, $url);

        return $this->redirect($url);
    }

    #[Route('/{slug}/push/subscribe', name: 'app_public_group_push_subscribe', methods: ['POST'])]
    public function pushSubscribe(string $slug, Request $request): JsonResponse
    {
        $group = $this->requirePublicGroup($slug);

        if ($this->isLocked($group, $request)) {
            return new JsonResponse(['error' => 'locked'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        $endpoint = is_array($data) ? ($data['endpoint'] ?? null) : null;
        $p256dh = is_array($data) ? ($data['keys']['p256dh'] ?? null) : null;
        $auth = is_array($data) ? ($data['keys']['auth'] ?? null) : null;

        if (!is_string($endpoint) || !is_string($p256dh) || !is_string($auth) || $endpoint === '') {
            return new JsonResponse(['error' => 'invalid subscription'], Response::HTTP_BAD_REQUEST);
        }

        $subscription = $this->pushSubscriptionRepository->findOneByGroupAndEndpoint($group, $endpoint)
            ?? (new PushSubscription())->setGroup($group)->setEndpoint($endpoint)->setCreatedAt(new \DateTimeImmutable());

        $subscription->setP256dh($p256dh)->setAuth($auth);

        $this->entityManager->persist($subscription);
        $this->entityManager->flush();

        return new JsonResponse(['status' => 'subscribed'], Response::HTTP_CREATED);
    }

    #[Route('/{slug}/push/unsubscribe', name: 'app_public_group_push_unsubscribe', methods: ['POST'])]
    public function pushUnsubscribe(string $slug, Request $request): JsonResponse
    {
        $group = $this->requirePublicGroup($slug);

        $data = json_decode($request->getContent(), true);
        $endpoint = is_array($data) ? ($data['endpoint'] ?? null) : null;

        if (is_string($endpoint) && $endpoint !== '') {
            $subscription = $this->pushSubscriptionRepository->findOneByGroupAndEndpoint($group, $endpoint);
            if ($subscription !== null) {
                $this->entityManager->remove($subscription);
                $this->entityManager->flush();
            }
        }

        return new JsonResponse(['status' => 'unsubscribed']);
    }

    #[Route('/{slug}/more', name: 'app_public_group_more', methods: ['GET'])]
    public function more(string $slug, Request $request): Response
    {
        $group = $this->requirePublicGroup($slug);

        if ($this->isLocked($group, $request)) {
            throw new NotFoundHttpException();
        }

        $page = max(1, $request->query->getInt('page', 1));
        $since = $this->windowStart($group);
        $items = $this->itemRepository->findPaginatedForPublicGroup($group, $page, self::ITEMS_PER_PAGE, $since);
        $total = $this->itemRepository->countForPublicGroup($group, $since);

        return $this->render('public/_cards.html.twig', [
            'group' => $group,
            'viewItems' => $this->buildViewItems($items, $group),
            'page' => $page,
            'hasMore' => $total > $page * self::ITEMS_PER_PAGE,
        ]);
    }

    #[Route('/{slug}/unlock', name: 'app_public_group_unlock', methods: ['POST'])]
    public function unlock(string $slug, Request $request): Response
    {
        $group = $this->requirePublicGroup($slug);

        if (!$group->isPublicPasswordProtected()) {
            return $this->redirectToRoute('app_public_group', ['slug' => $slug]);
        }

        $password = (string) $request->request->get('password', '');
        if ($group->verifyPublicPassword($password)) {
            $request->getSession()->set($this->sessionKey($group), true);

            return $this->redirectToRoute('app_public_group', ['slug' => $slug]);
        }

        return $this->render('public/password.html.twig', [
            'group' => $group,
            'error' => true,
        ]);
    }

    private function requirePublicGroup(string $slug): Group
    {
        $group = $this->groupRepository->findOneBy(['publicSlug' => $slug]);
        if ($group === null || !$group->isPublicPageEnabled()) {
            throw new NotFoundHttpException('Public page not found.');
        }

        return $group;
    }

    private function isLocked(Group $group, Request $request): bool
    {
        if (!$group->isPublicPasswordProtected()) {
            return false;
        }

        return $request->getSession()->get($this->sessionKey($group)) !== true;
    }

    private function sessionKey(Group $group): string
    {
        return 'public_group_ok_' . $group->getId();
    }

    private function windowStart(Group $group): ?\DateTimeImmutable
    {
        $days = $group->getTimeWindowDays();
        if ($days === null || $days <= 0) {
            return null;
        }

        return new \DateTimeImmutable(sprintf('-%d days', $days));
    }

    /**
     * Map items to render-ready view models, honouring the group's media flags.
     * Photos/videos/transcripts absent from the returned structure are never
     * emitted to the page.
     *
     * @param list<Item> $items
     * @return list<array{item: Item, photos: list<string>, videoUrl: ?string, poster: ?string, transcript: ?string}>
     */
    private function buildViewItems(array $items, Group $group): array
    {
        $view = [];
        foreach ($items as $item) {
            $photos = [];
            if ($group->isShowPhotos()) {
                foreach ($item->getPhotoPaths() as $path) {
                    $photos[] = $this->mediaUrl($path);
                }
            }

            $videoUrl = null;
            $poster = null;
            if ($group->isShowVideos() && $item->getVideoPath() !== null) {
                $videoUrl = $this->mediaUrl($item->getVideoPath());
                $poster = $photos[0] ?? null;
            }

            $transcript = null;
            if ($group->isShowTranscript() && $item->getTranscript() !== null && trim($item->getTranscript()) !== '') {
                $transcript = $item->getTranscript();
            }

            $view[] = [
                'item' => $item,
                'photos' => $photos,
                'videoUrl' => $videoUrl,
                'poster' => $poster,
                'transcript' => $transcript,
                'hue' => $this->avatarHue($item),
            ];
        }

        return $view;
    }

    /** Deterministic 0–359 hue for a profile's avatar, matching the reference page. */
    private function avatarHue(Item $item): int
    {
        $key = ($item->getProfile()?->getIdentifier() ?? '') . ($item->getProfile()?->getId() ?? '');
        $hash = 0;
        $len = strlen($key);
        for ($i = 0; $i < $len; $i++) {
            $hash = ($hash * 31 + ord($key[$i])) & 0xffff;
        }

        return $hash % 360;
    }

    private function mediaUrl(string $relativePath): string
    {
        return $this->urlHelper->getAbsoluteUrl(self::MEDIA_PATH_PREFIX . ltrim($relativePath, '/'));
    }
}
