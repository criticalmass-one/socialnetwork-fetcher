<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use App\Form\GroupType;
use App\Repository\ClientRepository;
use App\Repository\GroupRepository;
use App\Repository\ItemRepository;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groups')]
class GroupController extends AbstractController
{
    private const GROUPS_PER_PAGE = 50;
    private const TIMELINE_ITEMS_PER_PAGE = 50;
    private const PICKER_RESULTS = 20;

    #[Route('', name: 'app_group_index', methods: ['GET'])]
    public function index(Request $request, GroupRepository $groupRepository, ClientRepository $clientRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim($request->query->getString('search', ''));

        $clientUser = $this->loggedInClient();
        if ($clientUser !== null) {
            // Client users always see only their own groups; ignore ?client= overrides.
            $client = $clientUser;
            $clientPickerEnabled = false;
        } else {
            $clientId = $request->query->getInt('client', 0) ?: null;
            $client = $clientId ? $clientRepository->find($clientId) : null;
            $clientPickerEnabled = true;
        }

        $total = $groupRepository->countFiltered($client, $search);
        $pages = max(1, (int) ceil($total / self::GROUPS_PER_PAGE));
        $page = min($page, $pages);

        $groups = $groupRepository->findPaginated($page, self::GROUPS_PER_PAGE, $client, $search);

        return $this->render('group/index.html.twig', [
            'groups' => $groups,
            'clients' => $clientPickerEnabled ? $clientRepository->findBy([], ['name' => 'ASC']) : [],
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'search' => $search,
            'selectedClient' => $client,
            'clientPickerEnabled' => $clientPickerEnabled,
        ]);
    }

    #[Route('/new', name: 'app_group_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $group = new Group();

        $clientUser = $this->loggedInClient();
        if ($clientUser !== null) {
            $group->setClient($clientUser);
        }

        $form = $this->createForm(GroupType::class, $group, [
            'lock_client_to' => $clientUser,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($clientUser !== null) {
                // Force-set again in case form data was tampered with.
                $group->setClient($clientUser);
            }
            $em->persist($group);
            $em->flush();

            $this->addFlash('success', sprintf('Gruppe "%s" wurde angelegt. Füge jetzt Profile hinzu.', $group->getName()));

            return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
        }

        return $this->render('group/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_group_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(
        Group $group,
        Request $request,
        ItemRepository $itemRepository,
        NetworkRepository $networkRepository,
    ): Response {
        $this->denyForeignClient($group);
        $page = max(1, $request->query->getInt('page', 1));
        $networkId = $request->query->getInt('network', 0) ?: null;

        $itemCount = $itemRepository->countByGroup($group, $networkId);
        $pages = max(1, (int) ceil($itemCount / self::TIMELINE_ITEMS_PER_PAGE));
        $page = min($page, $pages);

        $items = $itemRepository->findPaginatedByGroup($group, $page, self::TIMELINE_ITEMS_PER_PAGE, $networkId);

        // Collect the distinct networks present in the group's live members, so
        // the network filter offers only what's actually relevant.
        $networks = [];
        foreach ($group->getProfiles() as $profile) {
            if ($profile->isDeleted() || $profile->getNetwork() === null) {
                continue;
            }
            $networks[$profile->getNetwork()->getId()] = $profile->getNetwork();
        }
        usort($networks, static fn ($a, $b): int => strcasecmp($a->getName() ?? '', $b->getName() ?? ''));

        return $this->render('group/show.html.twig', [
            'group' => $group,
            'items' => $items,
            'itemCount' => $itemCount,
            'page' => $page,
            'pages' => $pages,
            'networks' => array_values($networks),
            'selectedNetworkId' => $networkId,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_group_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Group $group, EntityManagerInterface $em): Response
    {
        $this->denyForeignClient($group);

        $form = $this->createForm(GroupType::class, $group, [
            'lock_client_to' => $this->loggedInClient(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Gruppe wurde aktualisiert.');

            return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
        }

        return $this->render('group/edit.html.twig', [
            'group' => $group,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_group_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Group $group, EntityManagerInterface $em): Response
    {
        $this->denyForeignClient($group);

        if (!$this->isCsrfTokenValid('delete-group-' . $group->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Die Aktion konnte nicht ausgeführt werden (ungültiges Sicherheitstoken). Bitte erneut versuchen.');

            return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
        }

        $em->remove($group);
        $em->flush();
        $this->addFlash('success', 'Gruppe wurde gelöscht.');

        return $this->redirectToRoute('app_group_index');
    }

    #[Route('/{id}/profiles/add', name: 'app_group_profile_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addProfile(Request $request, Group $group, ProfileRepository $profileRepository, EntityManagerInterface $em): Response
    {
        $this->denyForeignClient($group);

        if (!$this->isCsrfTokenValid('group-add-profile-' . $group->getId(), $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Die Aktion konnte nicht ausgeführt werden (ungültiges Sicherheitstoken). Bitte erneut versuchen.');

            return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
        }

        $profileIds = array_map('intval', (array) $request->request->all('profileIds'));
        $clientUser = $this->loggedInClient();
        $added = 0;

        foreach ($profileIds as $profileId) {
            if ($profileId <= 0) {
                continue;
            }
            $profile = $profileRepository->find($profileId);
            if ($profile === null || $profile->isDeleted()) {
                continue;
            }
            // Same rule as on the profile show page: admins may group any
            // profile; client-token users only profiles linked to them.
            if ($clientUser !== null && !$profile->getClients()->contains($clientUser)) {
                $this->addFlash('warning', sprintf('Profil "%s" ist nicht mit deinem Client verknüpft und wurde übersprungen.', $profile->getDisplayName()));
                continue;
            }
            $group->addProfile($profile);
            $added++;
        }

        $em->flush();

        if ($added > 0) {
            $this->addFlash('success', sprintf('%d Profil%s zur Gruppe hinzugefügt.', $added, $added === 1 ? '' : 'e'));
        } else {
            $this->addFlash('info', 'Keine Profile ausgewählt.');
        }

        return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
    }

    #[Route('/{id}/profiles/{profileId}/remove', name: 'app_group_profile_remove', requirements: ['id' => '\d+', 'profileId' => '\d+'], methods: ['POST'])]
    public function removeProfile(Request $request, Group $group, int $profileId, ProfileRepository $profileRepository, EntityManagerInterface $em): Response
    {
        $this->denyForeignClient($group);

        if (!$this->isCsrfTokenValid('group-remove-profile-' . $group->getId() . '-' . $profileId, $request->request->getString('_token'))) {
            $this->addFlash('danger', 'Die Aktion konnte nicht ausgeführt werden (ungültiges Sicherheitstoken). Bitte erneut versuchen.');

            return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
        }

        $profile = $profileRepository->find($profileId);
        if ($profile !== null) {
            $group->removeProfile($profile);
            $em->flush();
            $this->addFlash('success', sprintf('Profil "%s" wurde aus der Gruppe entfernt.', $profile->getDisplayName()));
        }

        return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
    }

    private function loggedInClient(): ?Client
    {
        $user = $this->getUser();
        return $user instanceof Client ? $user : null;
    }

    #[Route('/{id}/profiles/search', name: 'app_group_profile_search', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function searchProfiles(Request $request, Group $group, ProfileRepository $profileRepository): Response
    {
        $this->denyForeignClient($group);

        $term = trim($request->query->getString('q', ''));
        $memberIds = array_map(static fn (Profile $p) => $p->getId(), $group->getProfiles()->toArray());

        $profiles = $profileRepository->searchForPicker($term, $memberIds, self::PICKER_RESULTS);

        return $this->json([
            'results' => array_map(static fn (Profile $p) => [
                'id' => $p->getId(),
                'label' => $p->getDisplayName(),
                'identifier' => $p->getIdentifier(),
                'network' => $p->getNetwork()?->getName(),
                'networkIcon' => $p->getNetwork()?->getIcon(),
                'networkBackgroundColor' => $p->getNetwork()?->getBackgroundColor(),
                'networkTextColor' => $p->getNetwork()?->getTextColor(),
            ], $profiles),
            'limit' => self::PICKER_RESULTS,
        ]);
    }

    private function denyForeignClient(Group $group): void
    {
        $client = $this->loggedInClient();
        if ($client !== null && $group->getClient()?->getId() !== $client->getId()) {
            throw new NotFoundHttpException('Group not found.');
        }
    }
}
