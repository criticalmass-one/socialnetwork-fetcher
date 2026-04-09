<?php declare(strict_types=1);

namespace App\Model;

class Item
{
    protected ?int $id = null;
    protected ?int $profileId = null;
    protected ?string $uniqueIdentifier = null;
    protected ?string $permalink = null;
    protected ?string $title = null;
    protected ?string $text = null;
    protected ?\DateTime $dateTime = null;
    protected ?bool $hidden = false;
    protected ?bool $deleted = false;
    protected ?\DateTime $createdAt = null;
    protected ?string $raw = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): Item
    {
        $this->id = $id;

        return $this;
    }

    public function getProfileId(): int
    {
        return $this->profileId;
    }

    public function setProfileId(int $profileId): Item
    {
        $this->profileId = $profileId;

        return $this;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->uniqueIdentifier;
    }

    public function setUniqueIdentifier(string $uniqueIdentifier): Item
    {
        $this->uniqueIdentifier = $uniqueIdentifier;

        return $this;
    }

    public function getPermalink(): string
    {
        return $this->permalink;
    }

    public function setPermalink(string $permalink): Item
    {
        $this->permalink = $permalink;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): Item
    {
        $this->title = $title;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): Item
    {
        $this->text = $text;

        return $this;
    }

    public function getDateTime(): \DateTime
    {
        return $this->dateTime;
    }

    public function setDateTime(\DateTime $dateTime): Item
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): Item
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): Item
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): Item
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function setRaw(string $raw): Item
    {
        $this->raw = $raw;

        return $this;
    }
}
