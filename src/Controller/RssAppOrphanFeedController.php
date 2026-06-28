<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Profile;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use App\RssApp\FeedRegistrar;
use App\RssApp\RssAppInterface;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly NetworkRepository $networkRepository,
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
            $feedId = $profile->getRssAppFeedId();
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

            $feed['detected_network'] = $sourceUrl !== null
                ? $this->networkRepository->findNetworkForProfileUrl($sourceUrl)
                : null;

            $feed['created_at_dt'] = $this->parseDate($feed['created_at'] ?? null);

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

    #[Route('/{feedId}/adopt', name: 'app_rssapp_orphan_feed_adopt', methods: ['POST'])]
    public function adopt(
        Request $request,
        string $feedId,
        FeedRegistrar $feedRegistrar,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('rssapp-adopt-' . $feedId, $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_rssapp_orphan_feed_index');
        }

        $sourceUrl = trim($request->request->getString('source_url'));

        if ($sourceUrl === '') {
            $this->addFlash('danger', 'Source-URL fehlt.');
            return $this->redirectToRoute('app_rssapp_orphan_feed_index');
        }

        $network = $this->networkRepository->findNetworkForProfileUrl($sourceUrl);

        if ($network === null) {
            $this->addFlash('danger', sprintf('Kein passendes Netzwerk für URL "%s" gefunden.', $sourceUrl));
            return $this->redirectToRoute('app_rssapp_orphan_feed_index');
        }

        $existing = $this->profileRepository->findOneByNetworkAndIdentifier($network, $sourceUrl);

        if ($existing !== null) {
            if ($existing->isDeleted()) {
                $existing->setDeleted(false);
                $existing->setDeletedAt(null);
            }

            $importedCount = $feedRegistrar->linkExistingFeedAndImport($existing, $feedId);
            $em->flush();

            $this->addFlash('success', sprintf(
                'Bestehendes Profil wurde mit RSS.app-Feed verknüpft und %d Item%s importiert.',
                $importedCount,
                $importedCount === 1 ? '' : 's',
            ));

            return $this->redirectToRoute('app_profile_show', ['id' => $existing->getId()]);
        }

        $profile = new Profile();
        $profile->setId($this->profileRepository->findNextFreeId());
        $profile->setNetwork($network);
        $profile->setIdentifier($sourceUrl);
        $profile->setCreatedAt(new \DateTimeImmutable());

        $em->persist($profile);
        $em->flush();

        $importedCount = $feedRegistrar->linkExistingFeedAndImport($profile, $feedId);
        $em->flush();

        $this->addFlash('success', sprintf(
            'Profil wurde angelegt, mit RSS.app-Feed verknüpft und %d Item%s importiert.',
            $importedCount,
            $importedCount === 1 ? '' : 's',
        ));

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    private function normalizeUrl(string $url): string
    {
        $url = strtolower(trim($url));
        $url = rtrim($url, '/');
        $url = preg_replace('#^https?://(www\.)?#', '', $url);

        return $url;
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
