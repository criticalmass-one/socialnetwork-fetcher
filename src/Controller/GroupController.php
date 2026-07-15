<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Profile;
use App\Form\GroupType;
use App\Group\PublicSlugGenerator;
use App\Repository\ClientRepository;
use App\Repository\GroupRepository;
use App\Repository\ItemRepository;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/groups')]
class GroupController extends AbstractController
{
    private const GROUPS_PER_PAGE = 50;
    private const TIMELINE_ITEMS_PER_PAGE = 50;

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
    public function new(Request $request, EntityManagerInterface $em, PublicSlugGenerator $slugGenerator): Response
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
            if ($error = $this->reconcileGroupProfilesWithClient($group)) {
                $form->addError(new \Symfony\Component\Form\FormError($error));
            } else {
                $this->applyPublicPageSettings($group, $form, $slugGenerator);
                $em->persist($group);
                $em->flush();

                $this->addFlash('success', sprintf('Gruppe "%s" wurde angelegt.', $group->getName()));

                return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
            }
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
        \App\PublicPage\PublicPageAnalytics $analytics,
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

        // Public-page statistics are admin-only.
        $stats = $this->isGranted('ROLE_ADMIN') ? $analytics->summary($group) : null;

        return $this->render('group/show.html.twig', [
            'group' => $group,
            'items' => $items,
            'itemCount' => $itemCount,
            'page' => $page,
            'pages' => $pages,
            'networks' => array_values($networks),
            'selectedNetworkId' => $networkId,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_group_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Group $group, EntityManagerInterface $em, PublicSlugGenerator $slugGenerator): Response
    {
        $this->denyForeignClient($group);

        $form = $this->createForm(GroupType::class, $group, [
            'lock_client_to' => $this->loggedInClient(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($error = $this->reconcileGroupProfilesWithClient($group)) {
                $form->addError(new \Symfony\Component\Form\FormError($error));
            } else {
                $this->applyPublicPageSettings($group, $form, $slugGenerator);
                $em->flush();

                $this->addFlash('success', 'Gruppe wurde aktualisiert.');

                return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
            }
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

        if ($this->isCsrfTokenValid('delete-group-' . $group->getId(), $request->request->getString('_token'))) {
            $em->remove($group);
            $em->flush();
            $this->addFlash('success', 'Gruppe wurde gelöscht.');
        }

        return $this->redirectToRoute('app_group_index');
    }

    #[Route('/{id}/regenerate-slug', name: 'app_group_regenerate_slug', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function regenerateSlug(Request $request, Group $group, EntityManagerInterface $em, PublicSlugGenerator $slugGenerator): Response
    {
        $this->denyForeignClient($group);

        if ($this->isCsrfTokenValid('group-regenerate-slug-' . $group->getId(), $request->request->getString('_token'))) {
            $group->setPublicSlug($slugGenerator->generate());
            $em->flush();
            $this->addFlash('success', 'Der öffentliche Link wurde neu erzeugt. Der alte Link ist nicht mehr gültig.');
        }

        return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
    }

    #[Route('/{id}/profiles/add', name: 'app_group_profile_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addProfile(Request $request, Group $group, ProfileRepository $profileRepository, EntityManagerInterface $em): Response
    {
        $this->denyForeignClient($group);

        if ($this->isCsrfTokenValid('group-add-profile-' . $group->getId(), $request->request->getString('_token'))) {
            $profileIds = array_map('intval', (array) $request->request->all('profileIds'));

            foreach ($profileIds as $profileId) {
                if ($profileId <= 0) {
                    continue;
                }
                $profile = $profileRepository->find($profileId);
                if ($profile === null) {
                    continue;
                }
                $client = $group->getClient();
                if ($client !== null && !$profile->getClients()->contains($client)) {
                    // Client-token users may only add their own profiles; admins
                    // pull any profile in and it is auto-linked to the client.
                    if ($this->loggedInClient() !== null) {
                        $this->addFlash('warning', sprintf('Profil "%s" ist nicht mit dem Client der Gruppe verknüpft und wurde übersprungen.', $profile->getDisplayName()));
                        continue;
                    }
                    $client->addProfile($profile);
                }
                $group->addProfile($profile);
            }

            $em->flush();
            $this->addFlash('success', 'Profile wurden zur Gruppe hinzugefügt.');
        }

        return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
    }

    #[Route('/{id}/profiles/{profileId}/remove', name: 'app_group_profile_remove', requirements: ['id' => '\d+', 'profileId' => '\d+'], methods: ['POST'])]
    public function removeProfile(Request $request, Group $group, int $profileId, ProfileRepository $profileRepository, EntityManagerInterface $em): Response
    {
        $this->denyForeignClient($group);

        if ($this->isCsrfTokenValid('group-remove-profile-' . $group->getId() . '-' . $profileId, $request->request->getString('_token'))) {
            $profile = $profileRepository->find($profileId);
            if ($profile !== null) {
                $group->removeProfile($profile);
                $em->flush();
                $this->addFlash('success', sprintf('Profil "%s" wurde aus der Gruppe entfernt.', $profile->getDisplayName()));
            }
        }

        return $this->redirectToRoute('app_group_show', ['id' => $group->getId()]);
    }

    /**
     * Apply the unmapped public-page password fields and ensure an enabled page
     * has a slug. An empty password field leaves an existing password untouched;
     * the "remove" checkbox clears it.
     */
    private function applyPublicPageSettings(Group $group, FormInterface $form, PublicSlugGenerator $slugGenerator): void
    {
        if ($form->has('removePublicPassword') && $form->get('removePublicPassword')->getData() === true) {
            $group->setPublicPasswordHash(null);
        } elseif ($form->has('publicPassword')) {
            $newPassword = (string) $form->get('publicPassword')->getData();
            if ($newPassword !== '') {
                $group->setPublicPassword($newPassword);
            }
        }

        if ($group->isPublicPageEnabled() && $group->getPublicSlug() === null) {
            $group->setPublicSlug($slugGenerator->generate());
        }
    }

    private function loggedInClient(): ?Client
    {
        $user = $this->getUser();
        return $user instanceof Client ? $user : null;
    }

    private function denyForeignClient(Group $group): void
    {
        $client = $this->loggedInClient();
        if ($client !== null && $group->getClient()?->getId() !== $client->getId()) {
            throw new NotFoundHttpException('Group not found.');
        }
    }

    /** @return string|null  Error message if invalid, null if OK */
    /**
     * Keeps the invariant that a group only contains profiles of its client.
     * Admins may pull in any profile — it is auto-linked to the group's client
     * on save. Client-token users stay restricted to their own profiles, so a
     * client cannot grab a foreign profile by putting it into a group. Returns
     * an error message for the restricted case, or null when reconciled.
     */
    private function reconcileGroupProfilesWithClient(Group $group): ?string
    {
        $client = $group->getClient();
        if ($client === null) {
            return null;
        }

        $isAdmin = $this->loggedInClient() === null;

        foreach ($group->getProfiles() as $profile) {
            if ($profile->getClients()->contains($client)) {
                continue;
            }

            if ($isAdmin) {
                $client->addProfile($profile);
                continue;
            }

            return sprintf(
                'Profil "%s" ist nicht mit dem Client "%s" verknüpft. Verknüpfe es zuerst auf der Profil-Detailseite oder über die API.',
                $profile->getDisplayName(),
                $client->getName(),
            );
        }

        return null;
    }
}
