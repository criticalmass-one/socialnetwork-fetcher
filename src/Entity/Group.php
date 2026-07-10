<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\GroupRepository;
use App\State\ClientScopedGroupProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'profile_group')]
#[ORM\UniqueConstraint(name: 'uniq_group_client_name', columns: ['client_id', 'name'])]
#[ORM\Entity(repositoryClass: GroupRepository::class)]
#[ApiResource(
    shortName: 'Group',
    operations: [
        new GetCollection(
            description: 'Returns all groups owned by the authenticated client, ordered by name. Slim group:read group — pass through the single GET for embedded member profiles.',
        ),
        new Get(
            description: 'Returns a single group with its member profiles embedded.',
            normalizationContext: ['groups' => ['group:read', 'group:detail']],
        ),
        new Post(
            description: 'Creates a new group owned by the authenticated client. Pass profiles as a list of profile IRIs; they must already be linked to the client.',
            processor: ClientScopedGroupProcessor::class,
        ),
        new Put(
            description: 'Replaces an existing group (full replacement, including the profiles list).',
            processor: ClientScopedGroupProcessor::class,
        ),
        new Patch(
            description: 'Partial update of an existing group.',
            processor: ClientScopedGroupProcessor::class,
        ),
        new Delete(
            description: 'Deletes the group (hard delete). Member profiles are untouched.',
            processor: ClientScopedGroupProcessor::class,
        ),
    ],
    description: 'A named bundle of profiles, scoped to one API client. Profiles can be in multiple groups. Use /api/groups/{id}/items for the aggregated chronological timeline and /api/feeds/groups/{id}.rss for an RSS feed.',
    normalizationContext: ['groups' => ['group:read']],
    denormalizationContext: ['groups' => ['group:write']],
    order: ['name' => 'ASC'],
    paginationItemsPerPage: 50,
)]
#[ApiFilter(SearchFilter::class, properties: [
    'name' => 'partial',
    'profiles' => 'exact',
])]
#[ApiFilter(OrderFilter::class, properties: ['name', 'createdAt'])]
class Group
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['group:read'])]
    #[ApiProperty(description: 'Unique identifier of the group.', readable: true, writable: false)]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Human-readable name of the group. Must be unique within the client.', example: 'Klima')]
    private ?string $name = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(name: 'client_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[ApiProperty(description: 'The owning client. Set automatically from the Bearer token; not writable via the API.', readable: false, writable: false)]
    private ?Client $client = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Optional free-text description.')]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Optional badge color (#rrggbb).', example: '#6366f1')]
    private ?string $color = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    #[Groups(['group:read'])]
    #[ApiProperty(description: 'Timestamp when this group was created.', readable: true, writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'public_page_enabled', type: 'boolean', options: ['default' => false])]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Whether the public feed page for this group is reachable at /p/{publicSlug}. A slug is generated automatically the first time this is enabled.', example: false)]
    private bool $publicPageEnabled = false;

    #[ORM\Column(name: 'public_slug', type: 'string', length: 32, nullable: true, unique: true)]
    #[Groups(['group:read'])]
    #[ApiProperty(description: 'Unguessable URL token of the public page (path /p/{publicSlug}). Generated server-side; not writable. Use the regenerate-slug action to rotate it.', readable: true, writable: false)]
    private ?string $publicSlug = null;

    #[ORM\Column(name: 'public_password_hash', type: 'string', nullable: true)]
    #[ApiProperty(readable: false, writable: false)]
    private ?string $publicPasswordHash = null;

    #[ORM\Column(name: 'public_title', type: 'string', length: 120, nullable: true)]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Optional heading shown on the public page. Falls back to the group name when empty.')]
    private ?string $publicTitle = null;

    #[ORM\Column(name: 'public_description', type: 'text', nullable: true)]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Optional subtitle shown on the public page.')]
    private ?string $publicDescription = null;

    #[ORM\Column(name: 'show_photos', type: 'boolean', options: ['default' => true])]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Whether photos are shown on the public page.', example: true)]
    private bool $showPhotos = true;

    #[ORM\Column(name: 'show_videos', type: 'boolean', options: ['default' => true])]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Whether videos are shown on the public page.', example: true)]
    private bool $showVideos = true;

    #[ORM\Column(name: 'show_transcript', type: 'boolean', options: ['default' => false])]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Whether video transcripts are shown on the public page.', example: false)]
    private bool $showTranscript = false;

    #[ORM\Column(name: 'show_captions', type: 'boolean', options: ['default' => true])]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'Whether the post text/caption is shown on the public page.', example: true)]
    private bool $showCaptions = true;

    #[ORM\Column(name: 'time_window_days', type: 'integer', nullable: true, options: ['default' => 30])]
    #[Groups(['group:read', 'group:write'])]
    #[ApiProperty(description: 'How many days back the public page shows items. Null shows all items.', example: 30)]
    private ?int $timeWindowDays = 30;

    /**
     * Not persisted; filled at serialization time by GroupPublicUrlNormalizer.
     */
    #[Groups(['group:read'])]
    #[ApiProperty(description: 'Absolute URL of the public page, or null when disabled. Built from publicSlug against the current host.', readable: true, writable: false)]
    private ?string $publicUrl = null;

    /** @var Collection<int, Profile> */
    #[ORM\ManyToMany(targetEntity: Profile::class)]
    #[ORM\JoinTable(name: 'profile_group_profile')]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'profile_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['group:detail', 'group:write'])]
    #[ApiProperty(description: 'Profiles that belong to this group. Pass as a list of profile IRIs, e.g. ["/api/profiles/42", "/api/profiles/43"]. Only included on the single-resource GET.')]
    private Collection $profiles;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->profiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(Client $client): self
    {
        $this->client = $client;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /** @return Collection<int, Profile> */
    public function getProfiles(): Collection
    {
        return $this->profiles;
    }

    public function addProfile(Profile $profile): self
    {
        if (!$this->profiles->contains($profile)) {
            $this->profiles->add($profile);
        }

        return $this;
    }

    public function removeProfile(Profile $profile): self
    {
        $this->profiles->removeElement($profile);

        return $this;
    }

    #[Groups(['group:read'])]
    #[ApiProperty(description: 'Number of profiles in this group. Computed; readable in collection responses where the full profile list is omitted.', readable: true, writable: false)]
    public function getProfileCount(): int
    {
        return $this->profiles->count();
    }

    public function isPublicPageEnabled(): bool
    {
        return $this->publicPageEnabled;
    }

    public function setPublicPageEnabled(bool $publicPageEnabled): self
    {
        $this->publicPageEnabled = $publicPageEnabled;

        return $this;
    }

    public function getPublicSlug(): ?string
    {
        return $this->publicSlug;
    }

    public function setPublicSlug(?string $publicSlug): self
    {
        $this->publicSlug = $publicSlug;

        return $this;
    }

    public function getPublicPasswordHash(): ?string
    {
        return $this->publicPasswordHash;
    }

    public function setPublicPasswordHash(?string $publicPasswordHash): self
    {
        $this->publicPasswordHash = $publicPasswordHash;

        return $this;
    }

    /**
     * Set the public-page password from a plain-text value. An empty string
     * clears the password (page becomes freely accessible). Passing null leaves
     * the current hash untouched — used so that "field absent" on a PATCH does
     * not wipe an existing password.
     */
    #[Groups(['group:write'])]
    #[ApiProperty(description: 'Write-only. Plain-text password protecting the public page. Send an empty string to remove the password. The stored hash is never returned.')]
    public function setPublicPassword(?string $plain): self
    {
        if ($plain === null) {
            return $this;
        }

        $this->publicPasswordHash = $plain === ''
            ? null
            : password_hash($plain, PASSWORD_DEFAULT);

        return $this;
    }

    public function verifyPublicPassword(string $plain): bool
    {
        return $this->publicPasswordHash !== null
            && password_verify($plain, $this->publicPasswordHash);
    }

    #[Groups(['group:read'])]
    #[ApiProperty(description: 'Whether the public page is protected by a password.', readable: true, writable: false)]
    public function isPublicPasswordProtected(): bool
    {
        return $this->publicPasswordHash !== null;
    }

    public function getPublicTitle(): ?string
    {
        return $this->publicTitle;
    }

    public function setPublicTitle(?string $publicTitle): self
    {
        $this->publicTitle = $publicTitle;

        return $this;
    }

    public function getPublicDescription(): ?string
    {
        return $this->publicDescription;
    }

    public function setPublicDescription(?string $publicDescription): self
    {
        $this->publicDescription = $publicDescription;

        return $this;
    }

    public function isShowPhotos(): bool
    {
        return $this->showPhotos;
    }

    public function setShowPhotos(bool $showPhotos): self
    {
        $this->showPhotos = $showPhotos;

        return $this;
    }

    public function isShowVideos(): bool
    {
        return $this->showVideos;
    }

    public function setShowVideos(bool $showVideos): self
    {
        $this->showVideos = $showVideos;

        return $this;
    }

    public function isShowTranscript(): bool
    {
        return $this->showTranscript;
    }

    public function setShowTranscript(bool $showTranscript): self
    {
        $this->showTranscript = $showTranscript;

        return $this;
    }

    public function isShowCaptions(): bool
    {
        return $this->showCaptions;
    }

    public function setShowCaptions(bool $showCaptions): self
    {
        $this->showCaptions = $showCaptions;

        return $this;
    }

    public function getTimeWindowDays(): ?int
    {
        return $this->timeWindowDays;
    }

    public function setTimeWindowDays(?int $timeWindowDays): self
    {
        $this->timeWindowDays = $timeWindowDays;

        return $this;
    }

    public function getPublicUrl(): ?string
    {
        return $this->publicUrl;
    }

    /**
     * Effective heading for the public page: the explicit public title, or the
     * group name as a fallback.
     */
    public function getPublicHeading(): string
    {
        return $this->publicTitle ?? $this->name ?? '';
    }
}
