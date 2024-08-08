<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon\Model;

class Account
{
    public function __construct(
        private readonly string $hostname,
        private readonly string $username
    )
    {

    }

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
