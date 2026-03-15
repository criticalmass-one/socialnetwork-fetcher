<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use ApiPlatform\OpenApi\Model\Parameter;
use App\State\ClientScopedItemProvider;
use App\State\TimelineProvider;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'item')]
#[ORM\Index(columns: ['date_time'], name: 'item_date_time_index')]
#[ORM\Index(columns: ['created_at'], name: 'item_created_at_index')]
#[ORM\Entity(repositoryClass: \App\Repository\ItemRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            provider: ClientScopedItemProvider::class,
            description: 'Returns feed items for profiles linked to the authenticated client. Ordered by dateTime descending. Filter by profile using ?profile=<id>.',
        ),
        new GetCollection(
            uriTemplate: '/timeline',
            provider: TimelineProvider::class,
            description: 'Chronological timeline of all feed items across the authenticated client\'s profiles. Defaults to the last 24 hours, max 100 items. Use query parameters to customize: ?limit=50&since=2025-01-01T00:00:00Z&until=2025-01-31T23:59:59Z&network=mastodon',
            openapi: new OpenApiOperation(
                summary: 'Get client timeline',
                parameters: [
                    new Parameter(name: 'limit', in: 'query', description: 'Maximum number of items to return (default: 100, max: 500).', schema: ['type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 500]),
                    new Parameter(name: 'since', in: 'query', description: 'Return items published after this timestamp (default: 24 hours ago). ISO 8601 format.', schema: ['type' => 'string', 'format' => 'date-time']),
                    new Parameter(name: 'until', in: 'query', description: 'Return items published before this timestamp. ISO 8601 format.', schema: ['type' => 'string', 'format' => 'date-time']),
                    new Parameter(name: 'network', in: 'query', description: 'Filter by network identifier (e.g. "mastodon", "bluesky", "instagram_profile").', schema: ['type' => 'string']),
                ],
            ),
            paginationEnabled: false,
        ),
        new Get(
            provider: ClientScopedItemProvider::class,
            description: 'Returns a single feed item by ID. Returns 404 if the item belongs to a profile not linked to the authenticated client.',
        ),
        new Post(
            description: 'Creates a new feed item.',
        ),
        new Put(
            description: 'Updates an existing feed item.',
        ),
    ],
    description: 'A feed item (post, tweet, etc.) fetched from a social network profile. Items are scoped to profiles linked to the authenticated API client.',
    normalizationContext: ['groups' => ['item:read']],
    denormalizationContext: ['groups' => ['item:write']],
    order: ['dateTime' => 'DESC'],
    paginationItemsPerPage: 50,
)]
#[ApiFilter(SearchFilter::class, properties: ['profile' => 'exact'])]
#[ApiFilter(OrderFilter::class, properties: ['dateTime', 'createdAt'])]
class Item
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['item:read'])]
    #[ApiProperty(description: 'Unique identifier of the feed item.', readable: true, writable: false)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class)]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'The profile this item belongs to. Pass as IRI, e.g. "/api/profiles/42". Items are only visible if the profile is linked to the authenticated client.')]
    private ?Profile $profile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Network-specific unique identifier for this item (e.g. tweet ID, Mastodon status ID). Used for deduplication during feed fetching.')]
    private ?string $uniqueIdentifier = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Direct URL to the original post on the social network.')]
    private ?string $permalink = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Title of the feed item, if available (e.g. for RSS/blog posts). May be null for social media posts.')]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: false)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'The main text content of the feed item.')]
    private ?string $text = null;

    #[ORM\Column(name: 'date_time', type: 'datetime_immutable', nullable: false)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Publication date and time of the feed item on the social network.')]
    private ?\DateTimeImmutable $dateTime = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Whether this item is hidden from public display.')]
    private bool $hidden = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Whether this item has been marked as deleted.')]
    private bool $deleted = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    #[Groups(['item:read'])]
    #[ApiProperty(description: 'Timestamp when this item was first imported into the system.', readable: true, writable: false)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Raw JSON response from the social network API for this item.')]
    private ?string $raw = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Raw HTML source of the original page, if fetchSource was enabled on the profile.')]
    private ?string $rawSource = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    #[ApiProperty(description: 'Parsed/processed version of the source content.')]
    private ?string $parsedSource = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(Profile $profile): self
    {
        $this->profile = $profile;

        return $this;
    }

    public function getUniqueIdentifier(): ?string
    {
        return $this->uniqueIdentifier;
    }

    public function setUniqueIdentifier(string $uniqueIdentifier): self
    {
        $this->uniqueIdentifier = $uniqueIdentifier;

        return $this;
    }

    public function getPermalink(): ?string
    {
        return $this->permalink;
    }

    public function setPermalink(?string $permalink): self
    {
        $this->permalink = $permalink;

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

    public function getText(): ?string
    {
        return $this->text;
    }

    public function setText(string $text): self
    {
        $this->text = $text;

        return $this;
    }

    public function getDateTime(): ?\DateTimeImmutable
    {
        return $this->dateTime;
    }

    public function setDateTime(\DateTimeImmutable $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): self
    {
        $this->hidden = $hidden;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function setRaw(?string $raw): self
    {
        $this->raw = $raw;

        return $this;
    }

    public function getRawSource(): ?string
    {
        return $this->rawSource;
    }

    public function setRawSource(?string $rawSource): self
    {
        $this->rawSource = $rawSource;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }
}
