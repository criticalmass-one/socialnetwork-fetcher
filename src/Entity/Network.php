<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\NetworkRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'network')]
#[ORM\Entity(repositoryClass: NetworkRepository::class)]
class Network
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 64)]
    private ?string $icon = null;

    #[ORM\Column(type: 'string', length: 7)]
    private ?string $backgroundColor = null;

    #[ORM\Column(type: 'string', length: 7)]
    private ?string $textColor = null;

    #[ORM\Column(type: 'string', length: 512)]
    private ?string $profileUrlPattern = null;

    public function getId(): ?int
    {
        return $this->id;
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
