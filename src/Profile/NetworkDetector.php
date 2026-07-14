<?php declare(strict_types=1);

namespace App\Profile;

use App\Entity\Network;
use App\Repository\NetworkRepository;

/**
 * Determines a profile's network from its identifier by matching it against
 * each network's profileUrlPattern. The catch-all "homepage" network (which
 * matches any URL) is only used as a fallback when no more specific network
 * matches, so a real Instagram/Facebook/... URL resolves to its actual
 * network rather than to Homepage.
 */
class NetworkDetector
{
    private const FALLBACK_IDENTIFIER = 'homepage';

    public function __construct(private readonly NetworkRepository $networkRepository)
    {
    }

    public function detect(string $identifier): NetworkDetectionResult
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return NetworkDetectionResult::none();
        }

        $matches = array_values(array_filter(
            $this->networkRepository->findAll(),
            static fn (Network $network): bool => $network->isValidProfileUrl($identifier),
        ));

        $specific = array_values(array_filter(
            $matches,
            static fn (Network $network): bool => $network->getIdentifier() !== self::FALLBACK_IDENTIFIER,
        ));

        if (count($specific) === 1) {
            return NetworkDetectionResult::detected($specific[0]);
        }

        if (count($specific) > 1) {
            return NetworkDetectionResult::ambiguous($specific);
        }

        foreach ($matches as $match) {
            if ($match->getIdentifier() === self::FALLBACK_IDENTIFIER) {
                return NetworkDetectionResult::detected($match);
            }
        }

        return NetworkDetectionResult::none();
    }
}
