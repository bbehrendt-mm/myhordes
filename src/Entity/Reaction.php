<?php

namespace App\Entity;

use App\Repository\ReactionRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[ORM\Entity(repositoryClass: ReactionRepository::class)]
#[UniqueConstraint(name: 'reaction_unique', columns: ['parent', 'user_id'])]
class Reaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Emotes $emote = null;

    #[ORM\ManyToOne(inversedBy: 'reactions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ReactionSet $parent = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getEmote(): ?Emotes
    {
        return $this->emote;
    }

    public function setEmote(?Emotes $emote): static
    {
        $this->emote = $emote;

        return $this;
    }

    public function getParent(): ?ReactionSet
    {
        return $this->parent;
    }

    public function setParent(?ReactionSet $parent): static
    {
        $this->parent = $parent;

        return $this;
    }
}
