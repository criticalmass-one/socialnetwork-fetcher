<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon\Model;

use JMS\Serializer\Annotation as Serializer;

class Status
{
    private string $id;
    /**
     * @Serializer\Type('DateTime<"U">')
     */
    private \DateTime $createdAt;
    private string $uri;
    private string $content;
}
