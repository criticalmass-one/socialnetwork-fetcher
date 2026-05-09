<?php declare(strict_types=1);

namespace App\RssApp;

use App\Entity\Profile;
use Psr\Log\LoggerInterface;

class FeedRegistrar
{
    public const RSS_APP_NETWORKS = ['instagram_profile', 'facebook_profile', 'thread'];

    public function __construct(
        private readonly RssAppInterface $rssApp,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function registerIfNeeded(Profile $profile): bool
    {
        $networkIdentifier = $profile->getNetwork()?->getIdentifier();

        if (!in_array($networkIdentifier, self::RSS_APP_NETWORKS, true)) {
            return false;
        }

        $additionalData = $profile->getAdditionalData() ?? [];

        if (isset($additionalData['rss_feed_id'])) {
            return false;
        }

        try {
            $feedData = $this->rssApp->createFeed($profile->getIdentifier());
            $additionalData['rss_feed_id'] = $feedData['id'];
            $profile->setAdditionalData($additionalData);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('RSS.app feed registration failed for {identifier}: {message}', [
                'identifier' => $profile->getIdentifier(),
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
