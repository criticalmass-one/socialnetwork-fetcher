<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon\Model;

class AccountInfo
{
    private string $id;
    private string $username;
    private string $displayName;


    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }


}