<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Group;
use App\Entity\Network;
use App\Entity\Profile;
use App\Form\ProfileType;
use App\FeedFetcher\FeedFetcher;
use App\FeedFetcher\FetchInfo;
use App\FeedFetcher\FetchResult;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\Model\Profile as ModelProfile;
use App\Profile\IdentifierChangeException;
use App\Profile\IdentifierChangeResult;
use App\Profile\IdentifierChanger;
use App\Profile\NetworkDetector;
use App\Repository\GroupRepository;
use App\Repository\ItemRepository;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use App\RssApp\FeedRegistrar;
use App\RssApp\RegistrationResult;
use App\RssApp\RssAppInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profiles')]
class ProfileController extends AbstractController
{
    private const PROFILES_PER_PAGE = 50;

    #[Route('', name: 'app_profile_index')]
    public function index(Request $request, ProfileRepository $profileRepository, NetworkRepository $networkRepository, ItemRepository $itemRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $search = trim($request->query->getString('search', ''));
        $networkIds = array_map('intval', (array) $request->query->all('networks'));

        if ($networkIds === [] && $request->query->has('network')) {
            $networkIds = [$request->query->getInt('network')];
        }
        $status = $request->query->getString('status', '');

        $total = $profileRepository->countFiltered($networkIds, $search, $status);
        $pages = max(1, (int) ceil($total / self::PROFILES_PER_PAGE));
        $page = min($page, $pages);
        $profiles = $profileRepository->findPaginated($page, self::PROFILES_PER_PAGE, $networkIds, $search, $status);

        $profileIds = array_map(static fn ($p) => $p->getId(), $profiles);
        $itemCounts = $itemRepository->countByProfileIds($profileIds);

        if ($request->headers->get('X-Requested-With') === 'XMLHttpRequest') {
            return new JsonResponse([
                'html' => $this->renderView('profile/_partials/_profile_table_body.html.twig', [
                    'profiles' => $profiles,
                    'itemCounts' => $itemCounts,
                ]),
                'paginationHtml' => $this->renderView('_partials/_pagination.html.twig', [
                    'page' => $page,
                    'pages' => $pages,
                ]),
                'page' => $page,
                'pages' => $pages,
                'total' => $total,
                'status' => $status,
            ]);
        }

        return $this->render('profile/index.html.twig', [
            'profiles' => $profiles,
            'itemCounts' => $itemCounts,
            'networks' => $networkRepository->findBy([], ['name' => 'ASC']),
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'search' => $search,
            'selectedNetworks' => $networkIds,
            'selectedStatus' => $status,
        ]);
    }

    #[Route('/new', name: 'app_profile_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        FeedRegistrar $feedRegistrar,
        ProfileRepository $profileRepository,
        NetworkDetector $networkDetector,
    ): Response {
        $profile = new Profile();
        $profile->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(ProfileType::class, $profile, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $networkError = false;

            // Network is optional in the form: derive it from the identifier
            // unless the user picked one manually.
            if ($profile->getNetwork() === null) {
                $detection = $networkDetector->detect((string) $profile->getIdentifier());

                if ($detection->isDetected()) {
                    $profile->setNetwork($detection->network);
                } else {
                    $form->get('network')->addError(new FormError($detection->isAmbiguous()
                        ? sprintf(
                            'Der Identifier passt auf mehrere Netzwerke (%s). Bitte wähle das Netzwerk manuell.',
                            implode(', ', array_map(static fn (Network $n): string => (string) $n->getName(), $detection->candidates)),
                        )
                        : 'Das Netzwerk konnte nicht aus dem Identifier ermittelt werden. Bitte prüfe die URL oder wähle das Netzwerk manuell.'));
                    $networkError = true;
                }
            }

            if (!$networkError) {
                // The id column is not auto-generated (profiles imported from
                // criticalmass.in keep their id), so assign the next free one.
                $profile->setId($profileRepository->findNextFreeId());
                $em->persist($profile);
                $em->flush();

                $result = $feedRegistrar->registerIfNeeded($profile);
                $em->flush();

                $this->addFlash('success', $this->buildCreationFlashMessage($result));

                return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
            }
        }

        return $this->render('profile/new.html.twig', [
            'form' => $form,
        ]);
    }

    private function buildCreationFlashMessage(RegistrationResult $result): string
    {
        if (!$result->registered) {
            return 'Profil wurde erstellt.';
        }

        if ($result->linkedToExistingFeed) {
            return sprintf(
                'Profil wurde erstellt, mit bestehendem RSS.app-Feed verknüpft und %d Item%s importiert.',
                $result->importedItems,
                $result->importedItems === 1 ? '' : 's',
            );
        }

        return 'Profil wurde erstellt und bei RSS.app registriert.';
    }

    #[Route('/{id}', name: 'app_profile_show', requirements: ['id' => '\d+'])]
    public function show(Profile $profile, ItemRepository $itemRepository, GroupRepository $groupRepository): Response
    {
        $clientScope = $this->clientScope();
        $currentGroups = $groupRepository->findByProfile($profile, $clientScope);
        $availableGroups = $groupRepository->findAvailableForProfile($profile, $clientScope);

        $itemCount = $itemRepository->count(['profile' => $profile]);

        return $this->render('profile/show.html.twig', [
            'profile' => $profile,
            'itemCount' => $itemCount,
            'currentGroups' => $currentGroups,
            'availableGroups' => $availableGroups,
        ]);
    }

    #[Route('/{id}/groups/add', name: 'app_profile_groups_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addToGroups(
        Request $request,
        Profile $profile,
        GroupRepository $groupRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('profile-groups-add-' . $profile->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        $groupIds = array_map('intval', (array) $request->request->all('groupIds'));
        if ($groupIds === []) {
            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        $clientScope = $this->clientScope();
        $added = 0;

        foreach ($groupIds as $groupId) {
            if ($groupId <= 0) {
                continue;
            }
            $group = $groupRepository->find($groupId);
            if ($group === null) {
                continue;
            }
            // For client-token users: refuse cross-tenant. Admin can add a
            // profile to any group; tenancy is enforced at the read side
            // (API client scope only sees groups it owns).
            if ($clientScope !== null) {
                if ($group->getClient()?->getId() !== $clientScope->getId()) {
                    continue;
                }
                if (!$profile->getClients()->contains($clientScope)) {
                    $this->addFlash('warning', 'Profil ist nicht mit deinem Client verknüpft.');
                    continue;
                }
            }
            $group->addProfile($profile);
            $added++;
        }

        $em->flush();

        if ($added > 0) {
            $this->addFlash('success', sprintf('Profil wurde zu %d Gruppe%s hinzugefügt.', $added, $added === 1 ? '' : 'n'));
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    #[Route('/{id}/groups/{groupId}/remove', name: 'app_profile_group_remove', requirements: ['id' => '\d+', 'groupId' => '\d+'], methods: ['POST'])]
    public function removeFromGroup(
        Request $request,
        Profile $profile,
        int $groupId,
        GroupRepository $groupRepository,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('profile-group-remove-' . $profile->getId() . '-' . $groupId, $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        $group = $groupRepository->find($groupId);
        if ($group === null) {
            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        $clientScope = $this->clientScope();
        if ($clientScope !== null && $group->getClient()?->getId() !== $clientScope->getId()) {
            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        $group->removeProfile($profile);
        $em->flush();

        $this->addFlash('success', sprintf('Profil wurde aus Gruppe „%s" entfernt.', $group->getName() ?? '?'));

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    private function clientScope(): ?Client
    {
        $user = $this->getUser();
        return $user instanceof Client ? $user : null;
    }

    #[Route('/{id}/edit', name: 'app_profile_edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, Profile $profile, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProfileType::class, $profile);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Profil wurde aktualisiert.');

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        return $this->render('profile/edit.html.twig', [
            'profile' => $profile,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/change-identifier', name: 'app_profile_change_identifier', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changeIdentifier(Request $request, Profile $profile, IdentifierChanger $identifierChanger): Response
    {
        if (!$this->isCsrfTokenValid('change-identifier-' . $profile->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        try {
            $result = $identifierChanger->change($profile, $request->request->getString('identifier'));

            $this->addFlash(
                $result->relinkError !== null ? 'warning' : 'success',
                $this->buildIdentifierChangeFlashMessage($result),
            );
        } catch (IdentifierChangeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    private function buildIdentifierChangeFlashMessage(IdentifierChangeResult $result): string
    {
        if (!$result->changed) {
            return 'Identifier unverändert.';
        }

        if (!$result->rssAppApplicable) {
            return 'Identifier wurde aktualisiert.';
        }

        if ($result->relinkError !== null) {
            return sprintf(
                'Identifier wurde aktualisiert, aber die RSS.app-Neuverknüpfung ist fehlgeschlagen: %s. Bitte manuell bei RSS.app registrieren.',
                $result->relinkError,
            );
        }

        return sprintf(
            'Identifier wurde aktualisiert und %s RSS.app-Feed %s (%d Item%s importiert).',
            $result->linkedToExistingFeed ? 'mit einem bestehenden' : 'ein neuer',
            $result->linkedToExistingFeed ? 'verknüpft' : 'angelegt',
            $result->importedItems,
            $result->importedItems === 1 ? '' : 's',
        );
    }

    #[Route('/{id}/delete', name: 'app_profile_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Profile $profile, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-profile-' . $profile->getId(), $request->request->getString('_token'))) {
            $em->remove($profile);
            $em->flush();

            $this->addFlash('success', 'Profil wurde gelöscht.');
        }

        return $this->redirectToRoute('app_profile_index');
    }

    #[Route('/{id}/rssapp-register', name: 'app_profile_rssapp_register', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rssappRegister(Request $request, Profile $profile, RssAppInterface $rssApp, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('rssapp-' . $profile->getId(), $request->request->getString('_token'))) {
            try {
                $feedData = $rssApp->createFeed($profile->getIdentifier());

                $profile->setRssAppFeedId($feedData['id']);

                $em->flush();

                $this->addFlash('success', 'Feed wurde bei RSS.app registriert.');
            } catch (\Throwable $e) {
                $this->addFlash('danger', sprintf('Registrierung bei RSS.app fehlgeschlagen: %s', $e->getMessage()));
            }
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    #[Route('/{id}/rssapp-delete', name: 'app_profile_rssapp_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rssappDelete(Request $request, Profile $profile, RssAppInterface $rssApp, EntityManagerInterface $em): Response
    {
        $this->unlinkRssAppFeed($request, $profile, $rssApp, $em);

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    #[Route('/{id}/rssapp-delete-from-list', name: 'app_rssapp_profile_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rssappDeleteFromList(Request $request, Profile $profile, RssAppInterface $rssApp, EntityManagerInterface $em): Response
    {
        $this->unlinkRssAppFeed($request, $profile, $rssApp, $em);

        return $this->redirectToRoute('app_rssapp_profile_index');
    }

    private function unlinkRssAppFeed(Request $request, Profile $profile, RssAppInterface $rssApp, EntityManagerInterface $em): void
    {
        if (!$this->isCsrfTokenValid('rssapp-' . $profile->getId(), $request->request->getString('_token'))) {
            return;
        }

        $feedId = $profile->getRssAppFeedId();

        if ($feedId === null) {
            return;
        }

        try {
            $rssApp->deleteFeed($feedId);
        } catch (\Throwable $e) {
            $this->addFlash('danger', sprintf('Löschen bei RSS.app fehlgeschlagen: %s', $e->getMessage()));

            return;
        }

        $profile->setRssAppFeedId(null);

        $em->flush();

        $this->addFlash('success', 'Feed wurde von RSS.app entfernt.');
    }

    #[Route('/{id}/toggle-{field}', name: 'app_profile_toggle', requirements: ['id' => '\d+', 'field' => 'autoFetch|fetchSource|savePhotos|saveVideos|transcribeVideos'], methods: ['POST'])]
    public function toggle(Request $request, Profile $profile, string $field, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('toggle-profile-' . $profile->getId(), $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $setter = 'set' . ucfirst($field);
        $getter = match ($field) {
            'autoFetch' => 'isAutoFetch',
            'fetchSource' => 'isFetchSource',
            'savePhotos' => 'isSavePhotos',
            'saveVideos' => 'isSaveVideos',
            'transcribeVideos' => 'isTranscribeVideos',
        };

        $newValue = !$profile->$getter();
        $profile->$setter($newValue);
        $em->flush();

        return new JsonResponse([$field => $newValue]);
    }

    #[Route('/{id}/fetch', name: 'app_profile_fetch', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function fetch(Request $request, Profile $profile, FeedFetcher $feedFetcher, FeedItemPersisterInterface $feedItemPersister, EntityManagerInterface $em): Response
    {
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if (!$this->isCsrfTokenValid('fetch-profile-' . $profile->getId(), $request->request->getString('_token'))) {
            if ($isAjax) {
                return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
            }

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        $modelProfile = new ModelProfile();
        $modelProfile->setId($profile->getId());
        $modelProfile->setIdentifier($profile->getIdentifier());
        $modelProfile->setNetwork($profile->getNetwork()->getIdentifier());
        $modelProfile->setFetchSource($profile->isFetchSource());

        $fetcher = null;
        foreach ($feedFetcher->getNetworkFetcherList() as $networkFetcher) {
            if ($networkFetcher->supports($modelProfile)) {
                $fetcher = $networkFetcher;
                break;
            }
        }

        if (!$fetcher) {
            if ($isAjax) {
                return new JsonResponse([
                    'error' => sprintf('Kein Fetcher für Netzwerk "%s" verfügbar.', $profile->getNetwork()->getIdentifier()),
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->addFlash('warning', sprintf('Kein Fetcher für Netzwerk "%s" verfügbar.', $profile->getNetwork()->getIdentifier()));

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        $fetcherName = (new \ReflectionClass($fetcher))->getShortName();

        try {
            $fetchInfo = new FetchInfo();
            $feedItemList = $fetcher->fetch($modelProfile, $fetchInfo);

            $fetchResult = new FetchResult();
            $fetchResult->setProfile($modelProfile)->setCounterFetched(count($feedItemList));

            if ($feedItemPersister instanceof \App\FeedItemPersister\DoctrineFeedItemPersister) {
                $feedItemPersister->resetCounters();
            }

            $feedItemPersister->persistFeedItemList($feedItemList, $fetchResult)->flush();

            $profile->setLastFetchSuccessDateTime(new \DateTimeImmutable());
            $profile->setLastFetchFailureDateTime(null);
            $profile->setLastFetchFailureError(null);
            $em->flush();

            if ($isAjax) {
                $newCount = 0;
                $duplicateCount = 0;

                if ($feedItemPersister instanceof \App\FeedItemPersister\DoctrineFeedItemPersister) {
                    $newCount = $feedItemPersister->getNewCount();
                    $duplicateCount = $feedItemPersister->getDuplicateCount();
                }

                return new JsonResponse([
                    'success' => true,
                    'fetcher' => $fetcherName,
                    'fetched' => count($feedItemList),
                    'new' => $newCount,
                    'duplicates' => $duplicateCount,
                    'lastFetchDateTime' => $profile->getLastFetchSuccessDateTime()->format('d.m.Y H:i'),
                    'lastFetchDateTimeFull' => $profile->getLastFetchSuccessDateTime()->format('d.m.Y H:i:s'),
                ]);
            }

            $this->addFlash('success', sprintf('%d Items wurden importiert.', count($feedItemList)));
        } catch (\Exception $e) {
            $profile->setLastFetchFailureDateTime(new \DateTimeImmutable());
            $profile->setLastFetchFailureError($e->getMessage());
            $em->flush();

            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'fetcher' => $fetcherName,
                    'error' => $e->getMessage(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->addFlash('danger', sprintf('Fehler beim Import: %s', $e->getMessage()));
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }
}
