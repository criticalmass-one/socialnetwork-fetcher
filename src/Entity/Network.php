<?php declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\Repository\NetworkRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Table(name: 'network')]
#[ORM\Entity(repositoryClass: NetworkRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            description: 'Returns all available social networks (e.g. mastodon, bluesky, instagram_profile).',
        ),
        new Get(
            description: 'Returns a single network by ID.',
        ),
        new Post(
            description: 'Registers a new social network.',
        ),
        new Put(
            description: 'Updates an existing network.',
        ),
    ],
    description: 'A social network type (e.g. Mastodon, Bluesky, Instagram). Networks define the identifier pattern and fetch schedule. Use the network IRI when creating profiles.',
    normalizationContext: ['groups' => ['network:read']],
    denormalizationContext: ['groups' => ['network:write']],
)]
class Network
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['network:read', 'profile:read'])]
    #[ApiProperty(description: 'Unique identifier of the network.', readable: true, writable: false)]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    #[Groups(['network:read', 'network:write', 'profile:read'])]
    #[ApiProperty(description: 'Machine-readable identifier for the network (e.g. "mastodon", "bluesky", "instagram_profile", "facebook_profile", "thread", "homepage").', example: 'mastodon')]
    private ?string $identifier = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['network:read', 'network:write', 'profile:read'])]
    #[ApiProperty(description: 'Human-readable display name of the network.', example: 'Mastodon')]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['network:read', 'network:write'])]
    #[ApiProperty(description: 'CSS class or icon identifier for UI rendering.', example: 'fa-mastodon')]
    private ?string $icon = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['network:read', 'network:write'])]
    #[ApiProperty(description: 'Background color for UI rendering (hex or CSS color name).', example: '#6364FF')]
    private ?string $backgroundColor = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['network:read', 'network:write'])]
    #[ApiProperty(description: 'Text color for UI rendering (hex or CSS color name).', example: '#ffffff')]
    private ?string $textColor = null;

    #[ORM\Column(type: 'string', length: 512)]
    #[Groups(['network:read', 'network:write'])]
    #[ApiProperty(description: 'Regex pattern to validate profile URLs/identifiers for this network.')]
    private ?string $profileUrlPattern = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    #[Groups(['network:read', 'network:write'])]
    #[ApiProperty(description: 'Cron expression defining the fetch schedule for this network (e.g. "*/15 * * * *" for every 15 minutes). Null means no automatic fetching.', example: '*/15 * * * *')]
    private ?string $cronExpression = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): self
    {
        $this->identifier = $identifier;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }

    public function setBackgroundColor(string $backgroundColor): self
    {
        $this->backgroundColor = $backgroundColor;

        return $this;
    }

    public function getTextColor(): ?string
    {
        return $this->textColor;
    }

    public function setTextColor(string $textColor): self
    {
        $this->textColor = $textColor;

        return $this;
    }

    public function getProfileUrlPattern(): ?string
    {
        return $this->profileUrlPattern;
    }

    public function setProfileUrlPattern(string $profileUrlPattern): self
    {
        $this->profileUrlPattern = $profileUrlPattern;

        return $this;
    }

    public function getCronExpression(): ?string
    {
        return $this->cronExpression;
    }

    public function setCronExpression(?string $cronExpression): self
    {
        $this->cronExpression = $cronExpression;

        return $this;
    }

    public function isValidProfileUrl(string $url): bool
    {
        if ($this->profileUrlPattern === null) {
            return false;
        }

        return preg_match($this->profileUrlPattern, $url) === 1;
    }
}
