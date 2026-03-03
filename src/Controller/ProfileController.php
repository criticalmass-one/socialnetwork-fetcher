<?php declare(strict_types=1);

namespace App\Controller;

use App\Entity\Profile;
use App\Form\ProfileType;
use App\FeedFetcher\FeedFetcher;
use App\FeedFetcher\FetchInfo;
use App\FeedFetcher\FetchResult;
use App\FeedItemPersister\FeedItemPersisterInterface;
use App\Model\Profile as ModelProfile;
use App\Repository\ItemRepository;
use App\Repository\ProfileRepository;
use App\RssApp\RssAppInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/profiles')]
class ProfileController extends AbstractController
{
    #[Route('', name: 'app_profile_index')]
    public function index(ProfileRepository $profileRepository): Response
    {
        return $this->render('profile/index.html.twig', [
            'profiles' => $profileRepository->findBy([], ['identifier' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'app_profile_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $profile = new Profile();
        $profile->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(ProfileType::class, $profile, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($profile);
            $em->flush();

            $this->addFlash('success', 'Profil wurde erstellt.');

            return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
        }

        return $this->render('profile/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_profile_show', requirements: ['id' => '\d+'])]
    public function show(Profile $profile, ItemRepository $itemRepository): Response
    {
        $itemCount = $itemRepository->count(['profile' => $profile]);

        return $this->render('profile/show.html.twig', [
            'profile' => $profile,
            'itemCount' => $itemCount,
        ]);
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
            $feedData = $rssApp->createFeed($profile->getIdentifier());

            $additionalData = $profile->getAdditionalData() ?? [];
            $additionalData['rss_feed_id'] = $feedData['id'];
            $profile->setAdditionalData($additionalData);

            $em->flush();

            $this->addFlash('success', 'Feed wurde bei RSS.app registriert.');
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    #[Route('/{id}/rssapp-delete', name: 'app_profile_rssapp_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rssappDelete(Request $request, Profile $profile, RssAppInterface $rssApp, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('rssapp-' . $profile->getId(), $request->request->getString('_token'))) {
            $additionalData = $profile->getAdditionalData() ?? [];

            if (isset($additionalData['rss_feed_id'])) {
                $rssApp->deleteFeed($additionalData['rss_feed_id']);

                unset($additionalData['rss_feed_id']);
                $profile->setAdditionalData($additionalData);

                $em->flush();

                $this->addFlash('success', 'Feed wurde von RSS.app entfernt.');
            }
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $profile->getId()]);
    }

    #[Route('/{id}/toggle-{field}', name: 'app_profile_toggle', requirements: ['id' => '\d+', 'field' => 'autoFetch|fetchSource'], methods: ['POST'])]
    public function toggle(Request $request, Profile $profile, string $field, EntityManagerInterface $em): JsonResponse
    {
        if (!$this->isCsrfTokenValid('toggle-profile-' . $profile->getId(), $request->request->getString('_token'))) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $setter = 'set' . ucfirst($field);
        $getter = match ($field) {
            'autoFetch' => 'isAutoFetch',
            'fetchSource' => 'isFetchSource',
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
