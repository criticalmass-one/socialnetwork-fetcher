<?php declare(strict_types=1);

namespace App\Profile;

use App\Entity\Profile;
use App\Repository\ProfileRepository;
use App\RssApp\FeedRegistrar;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Changes the network identifier of an existing profile and, for RSS.app-based
 * networks, re-links its RSS.app feed to the new identifier (see
 * {@see FeedRegistrar::relinkRssAppFeed()}).
 *
 * The profile row and all of its already-imported items are preserved, so a
 * username change (e.g. a renamed Instagram account) keeps the full history in
 * the feeds app while future fetches follow the new identifier.
 */
class IdentifierChanger
{
    public function __construct(
        private readonly ProfileRepository $profileRepository,
        private readonly FeedRegistrar $feedRegistrar,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @throws IdentifierChangeException when the identifier is empty, invalid for
     *                                   the network, or already taken by another
     *                                   profile in the same network.
     */
    public function change(Profile $profile, string $newIdentifier): IdentifierChangeResult
    {
        $newIdentifier = trim($newIdentifier);

        if ($newIdentifier === '') {
            throw new IdentifierChangeException('Der Identifier darf nicht leer sein.');
        }

        $network = $profile->getNetwork();

        if ($network !== null
            && $network->getProfileUrlPattern() !== null
            && !$network->isValidProfileUrl($newIdentifier)
        ) {
            throw new IdentifierChangeException(sprintf(
                '„%s" ist kein gültiger Identifier für das Netzwerk %s.',
                $newIdentifier,
                $network->getName() ?? $network->getIdentifier() ?? '',
            ));
        }

        if ($newIdentifier === $profile->getIdentifier()) {
            return IdentifierChangeResult::unchanged();
        }

        if ($network !== null) {
            $existing = $this->profileRepository->findOneByNetworkAndIdentifier($network, $newIdentifier);

            if ($existing !== null && $existing->getId() !== $profile->getId()) {
                throw new IdentifierChangeException(sprintf(
                    'Es existiert bereits ein Profil mit dem Identifier „%s" in diesem Netzwerk.',
                    $newIdentifier,
                ));
            }
        }

        $profile->setIdentifier($newIdentifier);

        $result = $this->feedRegistrar->relinkRssAppFeed($profile);

        $this->em->flush();

        return $result;
    }
}
