<?php declare(strict_types=1);

namespace App\NetworkFeedFetcher\Mastodon\Model;

class Status
{
    private string $id;
    private \DateTime $createdAt;
    private string $url;
    private string $content;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): Status
    {
        $this->id = $id;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): Status
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): Status
    {
        $this->url = $url;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): Status
    {
        $this->content = $content;

        return $this;
    }
}
