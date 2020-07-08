<?php declare(strict_types=1);

namespace App\Model;

use JMS\Serializer\Annotation as JMS;

/**
 * @JMS\ExclusionPolicy("all")
 */
class SocialNetworkFeedItem //implements Crawlable
{
    /**
     * @JMS\Expose
     */
    protected $id;

    /**
     * @JMS\Expose
     * @JMS\Type("Relation<App\Model\SocialNetworkProfile>")
     */
    protected $socialNetworkProfile;

    /**
     * @JMS\Expose
     */
    protected $uniqueIdentifier;

    /**
     * @JMS\Expose
     */
    protected $permalink;

    /**
     * @JMS\Expose
     */
    protected $title;

    /**
     * @JMS\Expose
     */
    protected $text;

    /**
     * @JMS\Expose
     * @JMS\Type("DateTime<'U'>")
     */
    protected $dateTime;

    /**
     * @JMS\Expose
     * @JMS\Type("bool")
     */
    protected $hidden = false;

    /**
     * @JMS\Expose
     * @JMS\Type("bool")
     */
    protected $deleted = false;

    /**
     * @JMS\Expose
     * @JMS\Type("DateTime<'U'>")
     */
    protected $createdAt;

    /**
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

    public function getSocialNetworkProfile(): SocialNetworkProfile
    {
        return $this->socialNetworkProfile;
    }

    public function setSocialNetworkProfile(SocialNetworkProfile $socialNetworkProfile): SocialNetworkFeedItem
    {
        $this->socialNetworkProfile = $socialNetworkProfile;

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
