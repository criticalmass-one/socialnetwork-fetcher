<?php declare(strict_types=1);

namespace App\Model;

use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\ExclusionPolicy("all")
 */
class SocialNetworkFeedItem
{
    /**
     * @JMS\Type("int")
     * @JMS\Expose
     */
    protected ?int $id = null;

    /**
     * @JMS\Type("int")
     * @JMS\Expose
     */
    protected ?int $socialNetworkProfileId = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose
     */
    protected ?string $uniqueIdentifier = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose
     */
    protected ?string $permalink = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose
     */
    protected ?string $title = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose
     */
    protected ?string $text = null;

    /**
     * @JMS\Type("DateTime<'U'>")
     * @JMS\Expose
     */
    protected ?\DateTime $dateTime = null;

    /**
     * @JMS\Type("bool")
     * @JMS\Expose
     */
    protected ?bool $hidden = false;

    /**
     * @JMS\Type("bool")
     * @JMS\Expose
     */
    protected ?bool $deleted = false;

    /**
     * @JMS\Type("DateTime<'U'>")
     * @JMS\Expose
     */
    protected ?\DateTime $createdAt = null;

    /**
     * @JMS\Type("string")
     * @JMS\Expose
     */
    protected ?string $raw = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): SocialNetworkFeedItem
    {
        $this->id = $id;

        return $this;
    }

    public function getSocialNetworkProfileId(): int
    {
        return $this->socialNetworkProfileId;
    }

    public function setSocialNetworkProfileId(int $socialNetworkProfile): SocialNetworkFeedItem
    {
        $this->socialNetworkProfileId = $socialNetworkProfile;

        return $this;
    }

    public function getUniqueIdentifier(): string
    {
        return $this->uniqueIdentifier;
    }

    public function setUniqueIdentifier(string $uniqueIdentifier): SocialNetworkFeedItem
    {
        $this->uniqueIdentifier = $uniqueIdentifier;

        return $this;
    }

    public function getPermalink(): string
    {
        return $this->permalink;
    }

    public function setPermalink(string $permalink): SocialNetworkFeedItem
    {
        $this->permalink = $permalink;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): SocialNetworkFeedItem
    {
        $this->title = $title;

        return $this;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): SocialNetworkFeedItem
    {
        $this->text = $text;

        return $this;
    }

    public function getDateTime(): \DateTime
    {
        return $this->dateTime;
    }

    public function setDateTime(\DateTime $dateTime): SocialNetworkFeedItem
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function getHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): SocialNetworkFeedItem
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function getDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): SocialNetworkFeedItem
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): SocialNetworkFeedItem
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getRaw(): ?string
    {
        return $this->raw;
    }

    public function setRaw(string $raw): SocialNetworkFeedItem
    {
        $this->raw = $raw;
        
        return $this;
    }

}
