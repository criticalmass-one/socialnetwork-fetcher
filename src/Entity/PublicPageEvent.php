<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\PublicPageEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A single tracked event on a group's public page: either a page view or a
 * click on an outbound link. One row per event with its timestamp, so
 * admin-only statistics can be aggregated over time.
 */
#[ORM\Table(name: 'public_page_event')]
#[ORM\Index(name: 'idx_ppe_group_type_time', columns: ['group_id', 'type', 'occurred_at'])]
#[ORM\Entity(repositoryClass: PublicPageEventRepository::class)]
class PublicPageEvent
{
    public const TYPE_VIEW = 'view';
    public const TYPE_CLICK = 'click';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Group::class)]
    #[ORM\JoinColumn(name: 'group_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Group $group = null;

    #[ORM\Column(type: 'string', length: 16)]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $url = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $occurredAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): ?Group
    {
        return $this->group;
    }

    public function setGroup(Group $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getOccurredAt(): ?\DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeImmutable $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }
}
