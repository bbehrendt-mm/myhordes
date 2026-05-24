<?php

namespace App\Entity;

use App\Repository\AnnouncementRepository;
use App\Traits\Entity\DoctrineExtensions;
use App\Traits\Entity\LinksMorph;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;

#[ORM\Entity(repositoryClass: AnnouncementRepository::class)]
class Announcement
{
    use LinksMorph, DoctrineExtensions;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;
    #[ORM\Column(type: 'datetime')]
    private ?DateTimeInterface $timestamp = null;
    #[ORM\Column(type: 'text')]
    private ?string $text = null;
    #[ORM\ManyToMany(targetEntity: User::class, fetch: 'EXTRA_LAZY')]
    private Collection $readBy;
    #[ORM\Column(type: 'string', length: 190)]
    private ?string $title = null;
    #[ORM\Column(type: 'string', length: 8)]
    private ?string $lang = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $validated = true;

    #[ORM\ManyToOne]
    private ?User $validatedBy = null;

    protected static array $morphsTo = [
        ReactionSet::class,
    ];

    public function __construct()
    {
        $this->readBy = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getSender(): ?User
    {
        return $this->sender;
    }
    public function setSender(?User $sender): self
    {
        $this->sender = $sender;

        return $this;
    }
    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }
    public function setTimestamp(\DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;

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
    /**
     * @return ArrayCollection<int,User>|PersistentCollection<int,User>
     */
    public function getReadBy(): ArrayCollection|PersistentCollection
    {
        return $this->readBy;
    }
    public function addReadBy(User $readBy): self
    {
        if (!$this->readBy->contains($readBy)) {
            $this->readBy[] = $readBy;
        }

        return $this;
    }
    public function removeReadBy(User $readBy): self
    {
        $this->readBy->removeElement($readBy);

        return $this;
    }
    public function getTitle(): ?string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
    public function getLang(): ?string
    {
        return $this->lang;
    }
    public function setLang(string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }

    public function isValidated(): ?bool
    {
        return $this->validated;
    }

    public function setValidated(bool $validated): self
    {
        $this->validated = $validated;

        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): self
    {
        $this->validatedBy = $validatedBy;

        return $this;
    }
}
