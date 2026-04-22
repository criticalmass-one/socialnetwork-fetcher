<?php declare(strict_types=1);

namespace App\Controller;

use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/rssapp-profiles')]
class RssAppProfileController extends AbstractController
{
    #[Route('', name: 'app_rssapp_profile_index')]
    public function index(ProfileRepository $profileRepository, ItemRepository $itemRepository): Response
    {
        $profiles = $profileRepository->findWithRssAppFeedId();
        $profileIds = array_map(static fn ($p) => $p->getId(), $profiles);
        $lastItemDates = $itemRepository->findLastItemDateByProfileIds($profileIds);

        return $this->render('rssapp_profile/index.html.twig', [
            'profiles' => $profiles,
            'lastItemDates' => $lastItemDates,
        ]);
    }
}
