<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'profile')]
#[ORM\UniqueConstraint(name: 'uniq_profile_network_identifier', columns: ['network_id', 'identifier'])]
#[ORM\Entity(repositoryClass: \App\Repository\ProfileRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
    ],
    normalizationContext: ['groups' => ['profile:read']],
    denormalizationContext: ['groups' => ['profile:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['network' => 'exact'])]
class Profile
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[Groups(['profile:read', 'item:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['profile:read', 'profile:write', 'item:read'])]
    private ?string $identifier = null;

    #[ORM\ManyToOne(targetEntity: Network::class)]
    #[ORM\JoinColumn(name: 'network_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['profile:read', 'profile:write'])]
    private ?Network $network = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['profile:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['profile:read', 'profile:write'])]
    private bool $autoPublish = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['profile:read'])]
    private ?\DateTimeImmutable $lastFetchSuccessDateTime = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['profile:read'])]
    private ?\DateTimeImmutable $lastFetchFailureDateTime = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['profile:read'])]
    private ?string $lastFetchFailureError = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['profile:read', 'profile:write'])]
    private bool $autoFetch = true;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['profile:read', 'profile:write'])]
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

    public function getNetwork(): ?Network
    {
        return $this->network;
    }

    public function setNetwork(Network $network): self
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
