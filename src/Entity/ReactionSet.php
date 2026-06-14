<?php

namespace App\Entity;

use App\Repository\ReactionSetRepository;
use App\Traits\Entity\DoctrineExtensions;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ReactionSetRepository::class)]
class ReactionSet extends Morph
{
    use DoctrineExtensions;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(nullable: true)]
    private ?array $config = null;

    /**
     * @var Collection<int, Reaction>
     */
    #[ORM\OneToMany(targetEntity: Reaction::class, mappedBy: 'parent', fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $reactions;

    public function __construct()
    {
        $this->reactions = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): static
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @return ArrayCollection<int, Reaction>|PersistentCollection<int, Reaction>
     */
    public function getReactions(): ArrayCollection|PersistentCollection
    {
        return $this->reactions;
    }

    public function getReactionFor(User $user): ?Reaction
    {
        return $this->getReactions()->matching(
            Criteria::create(true)
                ->where( Criteria::expr()->eq('owner', $user))
        )->first() ?: null;
    }

    public function addReaction(Reaction $reaction): static
    {
        if (!$this->reactions->contains($reaction)) {
            $this->reactions->add($reaction);
            $reaction->setParent($this);
        }

        return $this;
    }

    public function removeReaction(Reaction $reaction): static
    {
        if ($this->reactions->removeElement($reaction)) {
            // set the owning side to null (unless already changed)
            if ($reaction->getParent() === $this) {
                $reaction->setParent(null);
            }
        }

        return $this;
    }
}
