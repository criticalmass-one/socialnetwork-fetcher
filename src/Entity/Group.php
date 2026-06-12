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
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

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
#[UniqueEntity(fields: ['client', 'name'], message: 'Eine Gruppe mit diesem Namen existiert für diesen Client bereits.')]
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
    #[Assert\NotBlank(message: 'Bitte einen Namen für die Gruppe angeben.')]
    #[Assert\Length(max: 64)]
    #[ApiProperty(description: 'Human-readable name of the group. Must be unique within the client.', example: 'Klima')]
    private ?string $name = null;

    // Kein Assert\NotNull hier: Beim API-POST setzt der ClientScopedGroupProcessor
    // den Client erst nach der Validierung. Das Web-Formular erzwingt die
    // Auswahl über einen Feld-Constraint im GroupType.
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
}
