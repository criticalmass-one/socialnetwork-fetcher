<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\ProfileRepository;
use App\RssApp\RssAppInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rssapp/orphan-feeds')]
class RssAppOrphanFeedController extends AbstractController
{
    public function __construct(
        private readonly RssAppInterface $rssApp,
        private readonly ProfileRepository $profileRepository,
    ) {
    }

    #[Route('', name: 'app_rssapp_orphan_feed_index', methods: ['GET'])]
    public function index(): Response
    {
        $feeds = $this->rssApp->listFeeds();
        $profiles = $this->profileRepository->findAll();

        $knownFeedIds = [];
        $knownSourceUrls = [];

        foreach ($profiles as $profile) {
            $additionalData = $profile->getAdditionalData() ?? [];
            $feedId = $additionalData['rss_feed_id'] ?? null;
            if ($feedId !== null) {
                $knownFeedIds[$feedId] = true;
            }

            $identifier = $profile->getIdentifier();
            if ($identifier !== null) {
                $knownSourceUrls[$this->normalizeUrl($identifier)] = true;
            }
        }

        $orphans = [];
        foreach ($feeds as $feed) {
            $feedId = $feed['id'] ?? null;
            $sourceUrl = $feed['source_url'] ?? null;

            if ($feedId !== null && isset($knownFeedIds[$feedId])) {
                continue;
            }

            if ($sourceUrl !== null && isset($knownSourceUrls[$this->normalizeUrl($sourceUrl)])) {
                continue;
            }

            $orphans[] = $feed;
        }

        return $this->render('rssapp_orphan_feed/index.html.twig', [
            'orphans' => $orphans,
            'totalFeeds' => count($feeds),
        ]);
    }

    #[Route('/{feedId}/delete', name: 'app_rssapp_orphan_feed_delete', methods: ['POST'])]
    public function delete(Request $request, string $feedId): Response
    {
        if ($this->isCsrfTokenValid('rssapp-orphan-' . $feedId, $request->request->getString('_token'))) {
            $this->rssApp->deleteFeed($feedId);
            $this->addFlash('success', 'Feed wurde bei RSS.app entfernt.');
        }

        return $this->redirectToRoute('app_rssapp_orphan_feed_index');
    }

    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = rtrim($url, '/');
        $url = preg_replace('#^https?://(www\.)?#', '', $url);

        return $url;
    }
}
