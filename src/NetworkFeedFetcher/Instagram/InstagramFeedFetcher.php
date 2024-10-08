<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Instagram;

use App\FeedFetcher\FetchInfo;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\Model\SocialNetworkProfile;
use InstagramScraper\Exception\InstagramNotFoundException;
use InstagramScraper\Instagram;
use InstagramScraper\Model\Media;
use Psr\Log\LoggerInterface;

class InstagramFeedFetcher extends AbstractNetworkFeedFetcher
{
    protected Instagram $instagram;

    public function __construct(LoggerInterface $logger, string $instagramScraperProxyServerAddress, string $instagramScraperProxyServerPort)
    {
        $this->instagram = new \InstagramScraper\Instagram();

        if ($instagramScraperProxyServerAddress) {
            Instagram::setProxy([
                'address' => $instagramScraperProxyServerAddress,
                'port' => $instagramScraperProxyServerPort,
                'tunnel' => true,
                'timeout' => 30,
            ]);
        }

        parent::__construct($logger);
    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        $username = Screenname::extractScreenname($socialNetworkProfile);

        if (!$username || !Screenname::isValidScreenname($username)) {
            $this->markAsFailed($socialNetworkProfile, sprintf('Skipping %s cause it is not a valid instagram username.', $username));
        }

        $this->logger->info(sprintf('Now quering @%s', $username));

        $additionalData = json_decode($socialNetworkProfile->getAdditionalData(), true);

        /*        if (array_key_exists('lastMediaId', $additionalData)) {
                    $lastFetchedMediaId = $additionalData['lastMediaId'];
                } else {
                    $lastFetchedMediaId = '';
                }*/

        // @todo fix last media id somehow
        $lastFetchedMediaId = '';
        
        try {
            $mediaList = $this->instagram->getMedias($username, $fetchInfo->getCount() ?? 100, $lastFetchedMediaId);
        } catch (InstagramNotFoundException $exception) {
            $this->markAsFailed($socialNetworkProfile, $exception->getMessage());
        }

        if (!isset($mediaList) || 0 === count($mediaList)) {
            return [];
        }

        $lastMediaId = null;

        /** @var Media $media */
        foreach ($mediaList as $media) {
            if (!$lastMediaId || $lastMediaId < $media->getId()) {
                $lastMediaId = $media->getId();
            }

            $feedItem = MediaConverter::convert($socialNetworkProfile, $media);

            if ($feedItem) {
                $this->logger->info(sprintf('Parsed and added instagram photo #%s', $feedItem->getUniqueIdentifier()));

                $feedItemList[] = $feedItem;

                if ($lastMediaId) {
                    $socialNetworkProfile->setAdditionalData(json_encode(['lastMediaId' => $lastMediaId]));
                }
            }
        }

        return $feedItemList;
    }

    public function getNetworkIdentifier(): string
    {
        return 'instagram_profile';
    }
}
