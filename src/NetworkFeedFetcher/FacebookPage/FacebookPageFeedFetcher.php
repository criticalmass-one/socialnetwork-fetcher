<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\FacebookPage;

use App\FeedFetcher\FetchInfo;
use App\NetworkFeedFetcher\AbstractNetworkFeedFetcher;
use App\Model\SocialNetworkProfile;
use Facebook\Facebook;

class FacebookPageFeedFetcher extends AbstractNetworkFeedFetcher
{
    public function getNetworkIdentifier(): string
    {
        return 'facebook_page';
    }

    public function fetch(SocialNetworkProfile $socialNetworkProfile, FetchInfo $fetchInfo): array
    {
        dump($socialNetworkProfile->getIdentifier());

        $fb = new Facebook([
            'app_id' => '216780188685195',
            'app_secret' => '51f8acb3e5d66730fd2f80537a034bf6',
            'default_access_token' => '216780188685195|e30bbde0a3a4dd8626044202f4162159'
        ]);

        $parts = explode('/', $socialNetworkProfile->getIdentifier());
        $lastPart = array_pop($parts);
        $response = $fb->get(sprintf('/%s', $lastPart));
        return [];
    }
}
