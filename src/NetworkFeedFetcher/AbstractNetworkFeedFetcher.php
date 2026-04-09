<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher;

use App\Model\Profile;
use Psr\Log\LoggerInterface;

abstract class AbstractNetworkFeedFetcher implements NetworkFeedFetcherInterface
{
    public function __construct(
        protected readonly LoggerInterface $logger
    ) {

    }

    public function supports(Profile $profile): bool
    {
        return $this->supportsNetwork($profile->getNetwork());
    }

    public function supportsNetwork(string $network): bool
    {
        return $this->getNetworkIdentifier() === $network;
    }

    public function getNetworkIdentifier(): string
    {
        $fqcn = get_class($this);
        $parts = explode('\\', $fqcn);
        $classname = array_pop($parts);
        $feedFetcherNetwork = str_replace('FeedFetcher', '', $classname);

        return strtolower($feedFetcherNetwork);
    }

    protected function markAsFailed(Profile $profile, string $errorMessage): Profile
    {
        $profile
            ->setLastFetchFailureDateTime(new \DateTime())
            ->setLastFetchFailureError($errorMessage);

        $this
            ->logger
            ->notice($errorMessage);

        return $profile;
    }
}
