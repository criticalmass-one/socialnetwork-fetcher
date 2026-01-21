<?php declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'social_network_profile')]
#[ORM\UniqueConstraint(name: 'uniq_social_network_profile_network_identifier', columns: ['network', 'identifier'])]
#[ORM\Entity(repositoryClass: \App\Repository\SocialNetworkProfileRepository::class)]
class SocialNetworkProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $identifier = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $network = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $autoPublish = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastFetchSuccessDateTime = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastFetchFailureDateTime = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastFetchFailureError = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $autoFetch = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $additionalData = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(?string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getNetwork(): ?string
    {
        return $this->network;
    }

    public function setNetwork(?string $network): self
    {
        $this->network = $network;

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

    public function isAutoPublish(): bool
    {
        return $this->autoPublish;
    }

    public function setAutoPublish(bool $autoPublish): self
    {
        $this->autoPublish = $autoPublish;

        return $this;
    }

    public function getLastFetchSuccessDateTime(): ?\DateTimeImmutable
    {
        return $this->lastFetchSuccessDateTime;
    }

    public function setLastFetchSuccessDateTime(?\DateTimeImmutable $dt): self
    {
        $this->lastFetchSuccessDateTime = $dt;

        return $this;
    }

    public function getLastFetchFailureDateTime(): ?\DateTimeImmutable
    {
        return $this->lastFetchFailureDateTime;
    }

    public function setLastFetchFailureDateTime(?\DateTimeImmutable $dt): self
    {
        $this->lastFetchFailureDateTime = $dt;

        return $this;
    }

    public function getLastFetchFailureError(): ?string
    {
        return $this->lastFetchFailureError;
    }

    public function setLastFetchFailureError(?string $error): self
    {
        $this->lastFetchFailureError = $error;

        return $this;
    }

    public function isAutoFetch(): bool
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
        return $this->additionalData ? (array) json_decode($this->additionalData, true) : null;
    }

    public function setAdditionalData(?array $additionalData): self
    {
        $this->additionalData = $additionalData !== null ? json_encode($additionalData) : null;

        return $this;
    }
}
