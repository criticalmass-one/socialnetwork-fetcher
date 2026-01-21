<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'item')]
#[ORM\Index(columns: ['date_time'], name: 'item_date_time_index')]
#[ORM\Index(columns: ['created_at'], name: 'item_created_at_index')]
#[ORM\Entity(repositoryClass: \App\Repository\ItemRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
    ],
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
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Profile::class)]
    #[ORM\JoinColumn(name: 'profile_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['item:read', 'item:write'])]
    private ?Profile $profile = null;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    #[Groups(['item:read', 'item:write'])]
    private ?string $uniqueIdentifier = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    private ?string $permalink = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: false)]
    #[Groups(['item:read', 'item:write'])]
    private ?string $text = null;

    #[ORM\Column(name: 'date_time', type: 'datetime_immutable', nullable: false)]
    #[Groups(['item:read', 'item:write'])]
    private ?\DateTimeImmutable $dateTime = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['item:read', 'item:write'])]
    private bool $hidden = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['item:read', 'item:write'])]
    private bool $deleted = false;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', nullable: false)]
    #[Groups(['item:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['item:read', 'item:write'])]
    private ?string $raw = null;

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
}
