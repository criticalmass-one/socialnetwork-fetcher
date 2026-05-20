<?php declare(strict_types=1);

namespace App\Model;

class Profile
{
    protected int $id;
    protected ?string $identifier = null;
    protected string $network;
    private ?\DateTimeImmutable $createdAt = null;
    protected ?\DateTimeImmutable $lastFetchSuccessDateTime = null;
    protected ?\DateTimeImmutable $lastFetchFailureDateTime = null;
    protected ?string $lastFetchFailureError = null;
    protected bool $autoFetch = true;
    protected bool $fetchSource = false;
    protected bool $savePhotos = false;
    protected bool $saveVideos = false;
    protected ?string $additionalData = null;
    protected ?string $rssAppFeedId = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): Profile
    {
        $this->id = $id;

        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): Profile
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getNetwork(): ?string
    {
        return $this->network;
    }

    public function setNetwork($network): Profile
    {
        $this->network = $network;

        return $this;
    }

    public function setMainNetwork(bool $mainNetwork): Profile
    {
        $this->mainNetwork = $mainNetwork;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastFetchSuccessDateTime(): ?\DateTimeImmutable
    {
        return $this->lastFetchSuccessDateTime;
    }

    public function setLastFetchSuccessDateTime(?\DateTimeImmutable $lastFetchSuccessDateTime): self
    {
        $this->lastFetchSuccessDateTime = $lastFetchSuccessDateTime;

        return $this;
    }

    public function getLastFetchFailureDateTime(): ?\DateTimeImmutable
    {
        return $this->lastFetchFailureDateTime;
    }

    public function setLastFetchFailureDateTime(?\DateTimeImmutable $lastFetchFailureDateTime): self
    {
        $this->lastFetchFailureDateTime = $lastFetchFailureDateTime;

        return $this;
    }

    public function getLastFetchFailureError(): ?string
    {
        return $this->lastFetchFailureError;
    }

    public function setLastFetchFailureError(?string $lastFetchFailureError): self
    {
        $this->lastFetchFailureError = $lastFetchFailureError;

        return $this;
    }

    public function getAutoFetch(): ?bool
    {
        return $this->autoFetch;
    }

    public function setAutoFetch(bool $autoFetch): self
    {
        $this->autoFetch = $autoFetch;

        return $this;
    }

    public function isFetchSource(): bool
    {
        return $this->fetchSource;
    }

    public function setFetchSource(bool $fetchSource): self
    {
        $this->fetchSource = $fetchSource;

        return $this;
    }

    public function isSavePhotos(): bool
    {
        return $this->savePhotos;
    }

    public function setSavePhotos(bool $savePhotos): self
    {
        $this->savePhotos = $savePhotos;

        return $this;
    }

    public function isSaveVideos(): bool
    {
        return $this->saveVideos;
    }

    public function setSaveVideos(bool $saveVideos): self
    {
        $this->saveVideos = $saveVideos;

        return $this;
    }

    public function getAdditionalData(): ?string
    {
        return $this->additionalData;
    }

    public function setAdditionalData(null|string|array $additionalData): self
    {
        if (is_array($additionalData)) {
            $additionalData = json_encode($additionalData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $this->additionalData = $additionalData;

        return $this;
    }

    public function getRssAppFeedId(): ?string
    {
        return $this->rssAppFeedId;
    }

    public function setRssAppFeedId(?string $rssAppFeedId): self
    {
        $this->rssAppFeedId = $rssAppFeedId;

        return $this;
    }
}
