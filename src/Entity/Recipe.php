<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: 'App\Repository\RecipeRepository')]
#[UniqueEntity('name')]
#[Table]
#[UniqueConstraint(name: 'recipe_name_unique', columns: ['name'])]
class Recipe
{
    const int WorkshopType = 1;
    const int WorkshopTypeShamanSpecific = 2;
    const int WorkshopTypeTechSpecific = 3;
    const int ManualOutside = 11;
    const int ManualInside = 12;
    const int ManualAnywhere = 13;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;
    #[ORM\Column(type: 'string', length: 32)]
    private ?string $name;
    #[ORM\Column(type: 'integer')]
    private ?int $type;
    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $action;
    #[ORM\ManyToOne(targetEntity: ItemGroup::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?ItemGroup $source;
    #[ORM\ManyToMany(targetEntity: ItemPrototype::class)]
    private Collection $provoking;
    #[ORM\ManyToOne(targetEntity: ItemGroup::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?ItemGroup $result;
    #[ORM\ManyToOne(targetEntity: PictoPrototype::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?PictoPrototype $pictoPrototype;
    #[ORM\ManyToMany(targetEntity: ItemPrototype::class)]
    #[ORM\JoinTable(name: 'recipe_keep_item_prototype')]
    private Collection $keep;
    #[ORM\Column(type: 'boolean')]
    private bool $stealthy = false;
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $tooltip_string;

    #[ORM\Column]
    private bool $multiOut = false;

    #[ORM\Column]
    private int $additionalAP = 0;

    #[ORM\Column(length: 190, nullable: true)]
    private ?string $forcedErrorMessage = null;

    public function __construct()
    {
        $this->provoking = new ArrayCollection();
        $this->keep = new ArrayCollection();
    }
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
    public function getType(): ?int
    {
        return $this->type;
    }
    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }
    public function getAction(): ?string
    {
      return $this->action;
    }
    public function setAction(string $action): self
    {
      $this->action = $action;

      return $this;
    }
    public function getSource(): ?ItemGroup
    {
        return $this->source;
    }
    public function setSource(?ItemGroup $source): self
    {
        $this->source = $source;

        return $this;
    }
    /**
     * @return Collection<ItemPrototype>
     */
    public function getProvoking(): Collection
    {
        return $this->provoking;
    }
    public function addProvoking(ItemPrototype $provoking): self
    {
        if (!$this->provoking->contains($provoking)) {
            $this->provoking[] = $provoking;
        }

        return $this;
    }
    public function removeProvoking(ItemPrototype $provoking): self
    {
        if ($this->provoking->contains($provoking)) {
            $this->provoking->removeElement($provoking);
        }

        return $this;
    }
    public function getResult(): ?ItemGroup
    {
        return $this->result;
    }
    public function setResult(?ItemGroup $result): self
    {
        $this->result = $result;

        return $this;
    }
    public function getPictoPrototype(): ?PictoPrototype
    {
        return $this->pictoPrototype;
    }
    public function setPictoPrototype(?PictoPrototype $pictoPrototype): self
    {
        $this->pictoPrototype = $pictoPrototype;

        return $this;
    }
    /**
     * @return Collection<ItemPrototype>
     */
    public function getKeep(): Collection
    {
        return $this->keep;
    }
    public function addKeep(ItemPrototype $keep): self
    {
        if (!$this->keep->contains($keep)) {
            $this->keep[] = $keep;
        }

        return $this;
    }
    public function removeKeep(ItemPrototype $keep): self
    {
        if ($this->keep->contains($keep)) {
            $this->keep->removeElement($keep);
        }

        return $this;
    }
    public function getStealthy(): ?bool
    {
        return $this->stealthy;
    }
    public function setStealthy(bool $stealthy): self
    {
        $this->stealthy = $stealthy;

        return $this;
    }
    public function getTooltipString(): ?string
    {
        return $this->tooltip_string;
    }
    public function setTooltipString(?string $tooltip_string): self
    {
        $this->tooltip_string = $tooltip_string;

        return $this;
    }

    public function isMultiOut(): ?bool
    {
        return $this->multiOut;
    }

    public function setMultiOut(bool $multiOut): static
    {
        $this->multiOut = $multiOut;

        return $this;
    }

    public function getAdditionalAP(): int
    {
        return $this->additionalAP;
    }

    public function setAdditionalAP(int $additionalAP): static
    {
        $this->additionalAP = $additionalAP;

        return $this;
    }

    public function getForcedErrorMessage(): ?string
    {
        return $this->forcedErrorMessage;
    }

    public function setForcedErrorMessage(?string $forcedErrorMessage): static
    {
        $this->forcedErrorMessage = $forcedErrorMessage;

        return $this;
    }
}
