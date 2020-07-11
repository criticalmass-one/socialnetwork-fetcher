<?php declare(strict_types=1);

namespace App\FeedItemPersister;

use App\FeedFetcher\FetchResult;
use App\Model\SocialNetworkFeedItem;

interface FeedItemPersisterInterface
{
    public function persistFeedItemList(array $feedItemList, ?FetchResult $fetchResult): FeedItemPersisterInterface;

    public function persistFeedItem(SocialNetworkFeedItem $feedItem, ?FetchResult $fetchResult): FeedItemPersisterInterface;

    public function flush(): FeedItemPersisterInterface;
}