<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use App\State\ClientScopedProfileProcessor;
use App\State\ClientScopedProfileProvider;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'profile')]
#[ORM\UniqueConstraint(name: 'uniq_profile_network_identifier', columns: ['network_id', 'identifier'])]
#[ORM\Entity(repositoryClass: \App\Repository\ProfileRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: ClientScopedProfileProvider::class,
            description: 'Returns all profiles linked to the authenticated client. Soft-deleted profiles are excluded.',
        ),
        new Get(
            provider: ClientScopedProfileProvider::class,
            description: 'Returns a single profile by ID. Returns 404 if the profile is not linked to the authenticated client.',
        ),
        new Post(
            processor: ClientScopedProfileProcessor::class,
            description: 'Creates a new profile or links an existing one to the authenticated client. If a profile with the same network and identifier already exists, it is linked (idempotent). If the existing profile was soft-deleted, it is reactivated and re-registered at RSS.app if applicable.',
        ),
        new Delete(
            processor: ClientScopedProfileProcessor::class,
            description: 'Unlinks a profile from the authenticated client. If no other client references the profile, it is soft-deleted (deleted=true, deletedAt set) and its RSS.app feed is removed. Profiles and items are never physically deleted.',
        ),
        new Put(
            description: 'Updates an existing profile.',
        ),
    ],
    description: 'A social network profile (e.g. a Mastodon account, Instagram page). Profiles are scoped to the authenticated API client.',
    normalizationContext: ['groups' => ['profile:read']],
    denormalizationContext: ['groups' => ['profile:write']],
)]
#[ApiFilter(SearchFilter::class, properties: ['network' => 'exact'])]
class Profile
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[Groups(['profile:read', 'item:read'])]
    #[ApiProperty(description: 'Unique identifier of the profile.', readable: true, writable: false)]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['profile:read', 'profile:write', 'item:read'])]
    #[ApiProperty(description: 'Network-specific identifier, typically a URL. Example: "https://mastodon.social/@username" for Mastodon or "username.bsky.social" for Bluesky.')]
    private ?string $identifier = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['profile:read', 'profile:write'])]
    #[ApiProperty(description: 'Human-readable display name for the profile. Falls back to identifier if not set.')]
    private ?string $title = null;

    #[ORM\ManyToOne(targetEntity: Network::class)]
    #[ORM\JoinColumn(name: 'network_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['profile:read', 'profile:write'])]
    #[ApiProperty(description: 'The social network this profile belongs to. Pass as IRI, e.g. "/api/networks/1".')]
    private ?Network $network = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['profile:read'])]
    #[ApiProperty(description: 'Timestamp when the profile was first created.', readable: true, writable: false)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['profile:read'])]
    #[ApiProperty(description: 'Timestamp of the last successful feed fetch for this profile.', readable: true, writable: false)]
    private ?\DateTimeImmutable $lastFetchSuccessDateTime = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['profile:read'])]
    #[ApiProperty(description: 'Timestamp of the last failed feed fetch attempt.', readable: true, writable: false)]
    private ?\DateTimeImmutable $lastFetchFailureDateTime = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['profile:read'])]
    #[ApiProperty(description: 'Error message from the last failed fetch attempt, if any.', readable: true, writable: false)]
    private ?string $lastFetchFailureError = null;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['profile:read', 'profile:write'])]
    #[ApiProperty(description: 'Whether this profile is automatically included in scheduled feed fetches. Defaults to true.')]
    private bool $autoFetch = true;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['profile:read', 'profile:write'])]
    #[ApiProperty(description: 'Whether to fetch and store the raw source HTML/data alongside the parsed content.')]
    private bool $fetchSource = false;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['profile:read', 'profile:write'])]
    #[ApiProperty(description: 'Arbitrary JSON data for network-specific configuration (e.g. RSS.app feed ID).')]
    private ?string $additionalData = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['profile:read'])]
    #[ApiProperty(description: 'Whether this profile has been soft-deleted. Soft-deleted profiles are excluded from collection responses.', readable: true, writable: false)]
    private bool $deleted = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['profile:read'])]
    #[ApiProperty(description: 'Timestamp when the profile was soft-deleted, if applicable.', readable: true, writable: false)]
    private ?\DateTimeImmutable $deletedAt = null;

    /** @var Collection<int, Client> */
    #[ORM\ManyToMany(targetEntity: Client::class, mappedBy: 'profiles')]
    private Collection $clients;

    public function __construct()
    {
        $this->clients = new ArrayCollection();
    }

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->title ?? $this->identifier ?? '';
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

    public function isFetchSource(): bool
    {
        return $this->fetchSource;
    }

    public function setFetchSource(bool $fetchSource): self
    {
        $this->fetchSource = $fetchSource;

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

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): self
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /** @return Collection<int, Client> */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function getClientCount(): int
    {
        return $this->clients->count();
    }
}
