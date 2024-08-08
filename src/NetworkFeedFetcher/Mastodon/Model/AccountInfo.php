<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon\Model;

class AccountInfo
{
    private string $id;
    private string $username;
    private string $displayName;

    public function setId(string $id): AccountInfo
    {
        $this->id = $id;

        return $this;
    }

    public function setUsername(string $username): AccountInfo
    {
        $this->username = $username;

        return $this;
    }

    public function setDisplayName(string $displayName): AccountInfo
    {
        $this->displayName = $displayName;

        return $this;
    }

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