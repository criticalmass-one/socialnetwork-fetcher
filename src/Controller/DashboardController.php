<?php declare(strict_types=1);

namespace App\Controller;

use App\FeedFetcher\FeedFetcher;
use App\Repository\ItemRepository;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractController
{
    #[Route('/', name: 'app_dashboard')]
    public function index(
        NetworkRepository $networkRepository,
        ProfileRepository $profileRepository,
        ItemRepository $itemRepository,
        FeedFetcher $feedFetcher,
    ): Response {
        $networks = $networkRepository->findAll();

        $now = new \DateTimeImmutable();
        $intervals = [
            'last24h' => $now->modify('-24 hours'),
            'last7d' => $now->modify('-7 days'),
            'last31d' => $now->modify('-31 days'),
            'last365d' => $now->modify('-365 days'),
        ];

        $networkStats = [];
        foreach ($networks as $network) {
            $profiles = $profileRepository->findBy(['network' => $network]);
            if (count($profiles) === 0) {
                continue;
            }
            $itemCount = 0;
            foreach ($profiles as $profile) {
                $itemCount += $itemRepository->count(['profile' => $profile]);
            }
            $networkStats[] = [
                'network' => $network,
                'profileCount' => count($profiles),
                'itemCount' => $itemCount,
                'itemCounts' => $itemRepository->countByNetworkSince($network, $intervals),
            ];
        }

        $latestItems = $itemRepository->findBy([], ['createdAt' => 'DESC'], 10);

        $fetchableNetworkIdentifiers = [];
        foreach ($feedFetcher->getNetworkFetcherList() as $fetcher) {
            $fetchableNetworkIdentifiers[] = $fetcher->getNetworkIdentifier();
        }

        return $this->render('dashboard/index.html.twig', [
            'networkCount' => count($networks),
            'profileCount' => $profileRepository->count([]),
            'itemCount' => $itemRepository->count([]),
            'networkStats' => $networkStats,
            'latestItems' => $latestItems,
            'fetchableNetworkIdentifiers' => $fetchableNetworkIdentifiers,
        ]);
    }
}
