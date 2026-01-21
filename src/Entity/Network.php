<?php declare(strict_types=1);

namespace App\Entity;

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
        new GetCollection(),
        new Get(),
        new Post(),
        new Put(),
    ],
    normalizationContext: ['groups' => ['network:read']],
    denormalizationContext: ['groups' => ['network:write']],
)]
class Network
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['network:read', 'profile:read'])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 32, unique: true)]
    #[Groups(['network:read', 'network:write', 'profile:read'])]
    private ?string $identifier = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['network:read', 'network:write', 'profile:read'])]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Groups(['network:read', 'network:write'])]
    private ?string $icon = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['network:read', 'network:write'])]
    private ?string $backgroundColor = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['network:read', 'network:write'])]
    private ?string $textColor = null;

    #[ORM\Column(type: 'string', length: 512)]
    #[Groups(['network:read', 'network:write'])]
    private ?string $profileUrlPattern = null;

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

    public function isValidProfileUrl(string $url): bool
    {
        if ($this->profileUrlPattern === null) {
            return false;
        }

        return preg_match($this->profileUrlPattern, $url) === 1;
    }
}
