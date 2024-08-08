<?php declare(strict_types=1);

namespace App\Model;

use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\ExclusionPolicy("all")
 */
class SocialNetworkProfile
{
    /**
     * @JMS\Type("int")
     * @JMS\Expose()
     */
    protected int $id;

    /**
     * @JMS\Type("int")
     * @JMS\Expose()
     */
    protected ?int $cityId = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose()
     */
    protected ?string $identifier = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose()
     */
    protected string $network;

    /**
     * @JMS\Type("DateTime")
     * @JMS\Expose
     */
    private ?\DateTime $createdAt = null;

    /**
     * @JMS\Type("bool")
     * @JMS\Expose
     */
    protected bool $autoPublish = true;

    /**
     * @JMS\Type("DateTime")
     * @JMS\Expose
     */
    protected ?\DateTime $lastFetchSuccessDateTime = null;

    /**
     * @JMS\Type("DateTime")
     * @JMS\Expose
     */
    protected ?\DateTime $lastFetchFailureDateTime = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose
     */
    protected ?string $lastFetchFailureError = null;

    /**
     * @JMS\Type("bool")
     * @JMS\Expose
     */
    protected bool $autoFetch = true;

    /**
     * @JMS\Type("string")
     * @JMS\Expose
     */
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

    public function getCityId(): ?int
    {
        return $this->cityId;
    }

    public function setCityId(?int $cityId): SocialNetworkProfile
    {
        $this->cityId = $cityId;

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

    public function getAdditionalData(): ?array
    {
        return (array)json_decode($this->additionalData ?? '{}');
    }

    public function setAdditionalData(?array $additionalData): self
    {
        $this->additionalData = json_encode($additionalData);

        return $this;
    }
}
