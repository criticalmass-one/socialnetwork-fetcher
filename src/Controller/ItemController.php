<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Form\ItemType;
use App\Repository\ItemRepository;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/items')]
class ItemController extends AbstractController
{
    private const ITEMS_PER_PAGE = 50;

    #[Route('', name: 'app_item_index')]
    public function index(
        Request $request,
        ItemRepository $itemRepository,
        ProfileRepository $profileRepository,
        NetworkRepository $networkRepository,
        CsrfTokenManagerInterface $csrfTokenManager,
    ): Response {
        $profileId = $request->query->getInt('profile') ?: null;
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim($request->query->getString('search', ''));
        $networkIds = array_map('intval', (array) $request->query->all('networks'));
        $status = $request->query->getString('status', '');

        $total = $itemRepository->countFiltered($profileId, $networkIds, $search, $status);
        $pages = max(1, (int) ceil($total / self::ITEMS_PER_PAGE));
        $page = min($page, $pages);

        $items = $itemRepository->findPaginated($page, self::ITEMS_PER_PAGE, $profileId, $networkIds, $search, $status);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return new JsonResponse([
                'items' => $this->serializeItems($items),
                'csrfToken' => $csrfTokenManager->getToken('toggle-item')->getValue(),
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
            ]);
        }

        return $this->render('item/index.html.twig', [
            'items' => $items,
            'profiles' => $profileRepository->findBy([], ['identifier' => 'ASC']),
            'networks' => $networkRepository->findBy([], ['name' => 'ASC']),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'search' => $search,
            'selectedProfile' => $profileId,
            'selectedNetworks' => $networkIds,
            'selectedStatus' => $status,
        ]);
    }

    /**
     * @param list<Item> $items
     * @return list<array<string, mixed>>
     */
    private function serializeItems(array $items): array
    {
        return array_map(function (Item $item): array {
            $profile = $item->getProfile();
            $network = $profile?->getNetwork();

            return [
                'id' => $item->getId(),
                'text' => $item->getText(),
                'title' => $item->getTitle(),
                'dateTime' => $item->getDateTime()?->format('d.m.Y H:i'),
                'hidden' => $item->isHidden(),
                'deleted' => $item->isDeleted(),
                'showUrl' => $this->generateUrl('app_item_show', ['id' => $item->getId()]),
                'editUrl' => $this->generateUrl('app_item_edit', ['id' => $item->getId()]),
                'profile' => $profile ? [
                    'id' => $profile->getId(),
                    'identifier' => $profile->getIdentifier(),
                    'showUrl' => $this->generateUrl('app_profile_show', ['id' => $profile->getId()]),
                    'network' => $network ? [
                        'name' => $network->getName(),
                        'icon' => $network->getIcon(),
                        'backgroundColor' => $network->getBackgroundColor(),
                        'textColor' => $network->getTextColor(),
                    ] : null,
                ] : null,
            ];
        }, $items);
    }

    #[Route('/{id}', name: 'app_item_show', requirements: ['id' => '\d+'])]
    public function show(Item $item): Response
    {
        return $this->render('item/show.html.twig', [
            'item' => $item,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_item_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Item $item, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ItemType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Item wurde aktualisiert.');

            return $this->redirectToRoute('app_item_show', ['id' => $item->getId()]);
        }

        return $this->render('item/edit.html.twig', [
            'item' => $item,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/toggle-hidden', name: 'app_item_toggle_hidden', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleHidden(Request $request, Item $item, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('toggle-item', $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $item->setHidden(!$item->isHidden());
        $em->flush();

        return new JsonResponse([
            'hidden' => $item->isHidden(),
        ]);
    }

    #[Route('/{id}/toggle-deleted', name: 'app_item_toggle_deleted', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleDeleted(Request $request, Item $item, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('toggle-item', $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $item->setDeleted(!$item->isDeleted());
        $em->flush();

        return new JsonResponse([
            'deleted' => $item->isDeleted(),
        ]);
    }
}
