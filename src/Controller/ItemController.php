<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Item;
use App\Form\ItemType;
use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/items')]
class ItemController extends AbstractController
{
    private const ITEMS_PER_PAGE = 50;

    #[Route('', name: 'app_item_index')]
    public function index(
        Request $request,
        ItemRepository $itemRepository,
        ProfileRepository $profileRepository,
    ): Response {
        $profileId = $request->query->getInt('profile');
        $profile = $profileId ? $profileRepository->find($profileId) : null;
        $page = max(1, $request->query->getInt('page', 1));

        $criteria = $profile ? ['profile' => $profile] : [];
        $total = $itemRepository->count($criteria);
        $pages = max(1, (int) ceil($total / self::ITEMS_PER_PAGE));
        $page = min($page, $pages);

        $items = $itemRepository->findBy(
            $criteria,
            ['dateTime' => 'DESC'],
            self::ITEMS_PER_PAGE,
            ($page - 1) * self::ITEMS_PER_PAGE,
        );

        return $this->render('item/index.html.twig', [
            'items' => $items,
            'profile' => $profile,
            'profiles' => $profileRepository->findBy([], ['identifier' => 'ASC']),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ]);
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
        if (!$this->isCsrfTokenValid('toggle-item-' . $item->getId(), $request->request->getString('_token'))) {
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
        if (!$this->isCsrfTokenValid('toggle-item-' . $item->getId(), $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $item->setDeleted(!$item->isDeleted());
        $em->flush();

        return new JsonResponse([
            'deleted' => $item->isDeleted(),
        ]);
    }
}
