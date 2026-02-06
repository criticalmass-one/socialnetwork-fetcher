<?php declare(strict_types=1);

namespace App\Controller;

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
    ): Response {
        $networks = $networkRepository->findAll();

        $networkStats = [];
        foreach ($networks as $network) {
            $profiles = $profileRepository->findBy(['network' => $network]);
            $itemCount = 0;
            foreach ($profiles as $profile) {
                $itemCount += $itemRepository->count(['profile' => $profile]);
            }
            $networkStats[] = [
                'network' => $network,
                'profileCount' => count($profiles),
                'itemCount' => $itemCount,
            ];
        }

        return $this->render('dashboard/index.html.twig', [
            'networkCount' => count($networks),
            'profileCount' => $profileRepository->count([]),
            'itemCount' => $itemRepository->count([]),
            'networkStats' => $networkStats,
        ]);
    }
}
