<?php declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Client;
use App\Entity\Profile;
use App\Repository\NetworkRepository;
use App\Repository\ProfileRepository;
use App\RssApp\RssAppInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** @implements ProcessorInterface<Profile, Profile|null> */
class ClientScopedProfileProcessor implements ProcessorInterface
{
    private const RSS_APP_NETWORKS = ['instagram_profile', 'facebook_profile', 'thread'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
        private readonly ProfileRepository $profileRepository,
        private readonly NetworkRepository $networkRepository,
        private readonly RssAppInterface $rssApp,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Profile
    {
        $client = $this->security->getUser();

        if (!$client instanceof Client) {
            throw new BadRequestHttpException('Authentication required.');
        }

        if ($operation instanceof Delete) {
            return $this->handleDelete($client, (int) $uriVariables['id']);
        }

        return $this->handlePost($client, $data);
    }

    private function handlePost(Client $client, Profile $data): Profile
    {
        $network = $data->getNetwork();

        if (!$network) {
            throw new BadRequestHttpException('Network is required.');
        }

        $identifier = $data->getIdentifier();

        if (!$identifier) {
            throw new BadRequestHttpException('Identifier is required.');
        }

        $existingProfile = $this->profileRepository->findOneByNetworkAndIdentifier($network, $identifier);

        if ($existingProfile && !$existingProfile->isDeleted()) {
            $client->addProfile($existingProfile);
            $this->em->flush();

            return $existingProfile;
        }

        if ($existingProfile && $existingProfile->isDeleted()) {
            $existingProfile->setDeleted(false);
            $existingProfile->setDeletedAt(null);
            $client->addProfile($existingProfile);

            $this->registerRssAppIfNeeded($existingProfile);

            $this->em->flush();

            return $existingProfile;
        }

        $data->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($data);
        $client->addProfile($data);

        $this->registerRssAppIfNeeded($data);

        $this->em->flush();

        return $data;
    }

    private function handleDelete(Client $client, int $profileId): null
    {
        $profile = $this->em->getRepository(Profile::class)->find($profileId);

        if (!$profile) {
            throw new NotFoundHttpException('Profile not found.');
        }

        if (!$client->getProfiles()->contains($profile)) {
            throw new NotFoundHttpException('Profile not found.');
        }

        $client->removeProfile($profile);
        $this->em->flush();

        // Refresh to get updated client count
        $this->em->refresh($profile);

        if ($profile->getClientCount() === 0) {
            $profile->setDeleted(true);
            $profile->setDeletedAt(new \DateTimeImmutable());

            $this->deleteRssAppFeedIfNeeded($profile);

            $this->em->flush();
        }

        return null;
    }

    private function registerRssAppIfNeeded(Profile $profile): void
    {
        $networkIdentifier = $profile->getNetwork()?->getIdentifier();

        if (!in_array($networkIdentifier, self::RSS_APP_NETWORKS, true)) {
            return;
        }

        $additionalData = $profile->getAdditionalData() ?? [];

        if (isset($additionalData['rss_feed_id'])) {
            return;
        }

        try {
            $feedData = $this->rssApp->createFeed($profile->getIdentifier());
            $additionalData['rss_feed_id'] = $feedData['id'];
            $profile->setAdditionalData($additionalData);
        } catch (\Throwable) {
            // RSS.app registration failure should not block profile creation
        }
    }

    private function deleteRssAppFeedIfNeeded(Profile $profile): void
    {
        $additionalData = $profile->getAdditionalData() ?? [];

        if (!isset($additionalData['rss_feed_id'])) {
            return;
        }

        try {
            $this->rssApp->deleteFeed($additionalData['rss_feed_id']);
            unset($additionalData['rss_feed_id']);
            $profile->setAdditionalData($additionalData ?: null);
        } catch (\Throwable) {
            // RSS.app deletion failure should not block profile soft-delete
        }
    }
}
