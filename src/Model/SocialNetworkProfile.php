<?php declare(strict_types=1);

namespace App\Model;

class SocialNetworkProfile
{
    protected int $id;
    protected ?string $identifier = null;
    protected string $network;
    private ?\DateTime $createdAt = null;
    protected bool $autoPublish = true;
    protected ?\DateTime $lastFetchSuccessDateTime = null;
    protected ?\DateTime $lastFetchFailureDateTime = null;
    protected ?string $lastFetchFailureError = null;
    protected bool $autoFetch = true;
    protected ?string $additionalData = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): SocialNetworkProfile
    {
        $this->id = $id;

        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): SocialNetworkProfile
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getNetwork(): ?string
    {
        return $this->network;
    }

    public function setNetwork($network): SocialNetworkProfile
    {
        $this->network = $network;

        return $this;
    }

    public function setMainNetwork(bool $mainNetwork): SocialNetworkProfile
    {
        $this->mainNetwork = $mainNetwork;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isAutoPublish(): bool
    {
        return $this->autoPublish;
    }

    public function setAutoPublish(bool $autoPublish): SocialNetworkProfile
    {
        $this->autoPublish = $autoPublish;

        return $this;
    }

    public function getLastFetchSuccessDateTime(): ?\DateTimeInterface
    {
        return $this->lastFetchSuccessDateTime;
    }

    public function setLastFetchSuccessDateTime(?\DateTimeInterface $lastFetchSuccessDateTime): self
    {
        $this->lastFetchSuccessDateTime = $lastFetchSuccessDateTime;

        return $this;
    }

    public function getLastFetchFailureDateTime(): ?\DateTimeInterface
    {
        return $this->lastFetchFailureDateTime;
    }

    public function setLastFetchFailureDateTime(?\DateTimeInterface $lastFetchFailureDateTime): self
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

    public function getAdditionalData(): ?string
    {
        return $this->additionalData;
    }

    public function setAdditionalData(?string $additionalData): self
    {
        $this->additionalData = $additionalData;

        return $this;
    }
}
