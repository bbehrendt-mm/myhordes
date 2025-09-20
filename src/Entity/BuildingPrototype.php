<?php

namespace App\Entity;

use App\Enum\Game\BuildingResourceSetType;
use App\Interfaces\NamedEntity;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: 'App\Repository\BuildingPrototypeRepository')]
#[UniqueEntity('name')]
#[Table]
#[UniqueConstraint(name: 'building_prototype_name_unique', columns: ['name'])]
class BuildingPrototype implements NamedEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;
    #[ORM\Column(type: 'string', length: 64)]
    private ?string $name;
    #[ORM\Column(type: 'string', length: 190)]
    private ?string $label;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description;
    #[ORM\Column(type: 'boolean')]
    private ?bool $temp;
    #[ORM\Column(type: 'string', length: 64)]
    private ?string $icon;
    #[ORM\Column(type: 'integer')]
    private ?int $blueprint;
    #[ORM\Column(type: 'integer')]
    private ?int $defense;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\BuildingPrototype', inversedBy: 'children')]
    private ?BuildingPrototype $parent;
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: 'App\Entity\BuildingPrototype')]
    private Collection $children;
    #[ORM\Column(type: 'integer')]
    private int $maxLevel = 0;
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $upgradeTexts = [];
    #[ORM\Column(type: 'integer')]
    private int $orderBy = 0;
    #[ORM\Column(type: 'integer')]
    private ?int $hp;
    #[ORM\Column(type: 'boolean')]
    private ?bool $impervious;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $zeroLevelText;

    #[ORM\Column]
    private ?bool $hasHardMode = null;

    private ?ArrayCollection $allParents = null;

    /**
     * @var Collection<int, BuildingConstructionResourceSet>
     */
    #[ORM\OneToMany(targetEntity: BuildingConstructionResourceSet::class, mappedBy: 'building', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $resourceSets;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->resourceSets = new ArrayCollection();
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
    public function getLabel(): ?string
    {
        return $this->label;
    }
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
    public function getTemp(): ?bool
    {
        return $this->temp;
    }
    public function setTemp(bool $temp): self
    {
        $this->temp = $temp;

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
    public function getBlueprint(): ?int
    {
        return $this->blueprint;
    }
    public function setBlueprint(int $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }
    public function getDefense(): ?int
    {
        return $this->defense;
    }
    public function setDefense(int $defense): self
    {
        $this->defense = $defense;

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Returns a list of all parent constructions
     * @return Collection<self>
     */
    public function getAllParents(): Collection
    {
        return clone ($this->allParents ??= new ArrayCollection([
            ...( $this->getParent() ? [$this->getParent()] : [] ),
            ...( $this->getParent()?->getAllParents()->toArray() ?? [] )
        ]));
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<self>>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }
    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children[] = $child;
            $child->setParent($this);
        }

        return $this;
    }
    public function removeChild(self $child): self
    {
        if ($this->children->contains($child)) {
            $this->children->removeElement($child);
            // set the owning side to null (unless already changed)
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }
    public function getMaxLevel(): ?int
    {
        return $this->maxLevel;
    }
    public function setMaxLevel(int $maxLevel): self
    {
        $this->maxLevel = $maxLevel;

        return $this;
    }
    public function getUpgradeTexts(): ?array
    {
        return $this->upgradeTexts;
    }
    public function setUpgradeTexts(?array $upgradeTexts): self
    {
        $this->upgradeTexts = $upgradeTexts;

        return $this;
    }
    public function getOrderBy(): ?int
    {
        return $this->orderBy;
    }
    public function setOrderBy(int $orderBy): self
    {
        $this->orderBy = $orderBy;

        return $this;
    }
    public function getHp(): ?int
    {
        return $this->hp;
    }
    public function setHp(int $hp): self
    {
        $this->hp = $hp;

        return $this;
    }
    public function getImpervious(): ?bool
    {
        return $this->impervious;
    }
    public function setImpervious(bool $impervious): self
    {
        $this->impervious = $impervious;

        return $this;
    }
    public function getZeroLevelText(): ?string
    {
        return $this->zeroLevelText;
    }
    public function setZeroLevelText(?string $zeroLevelText): self
    {
        $this->zeroLevelText = $zeroLevelText;

        return $this;
    }
    public static function getTranslationDomain(): ?string
    {
        return 'buildings';
    }

    public function isHasHardMode(): ?bool
    {
        return $this->hasHardMode;
    }

    public function setHasHardMode(bool $hasHardMode): static
    {
        $this->hasHardMode = $hasHardMode;

        return $this;
    }

    /**
     * @return Collection<int, BuildingConstructionResourceSet>|ArrayCollection<int, BuildingConstructionResourceSet>
     */
    public function getResourceSets(): Collection|ArrayCollection
    {
        return $this->resourceSets;
    }

    public function addResourceSet(BuildingConstructionResourceSet $resourceSet): static
    {
        if (!$this->resourceSets->contains($resourceSet)) {
            $this->resourceSets->add($resourceSet);
            $resourceSet->setBuilding($this);
        }

        return $this;
    }

    public function removeResourceSet(BuildingConstructionResourceSet $resourceSet): static
    {
        if ($this->resourceSets->removeElement($resourceSet)) {
            // set the owning side to null (unless already changed)
            if ($resourceSet->getBuilding() === $this) {
                $resourceSet->setBuilding(null);
            }
        }

        return $this;
    }

    public function getResourceSet(
        BuildingResourceSetType $type = BuildingResourceSetType::Default,
        int $level = 0,
        int $repeat = 0,
    ): BuildingConstructionResourceSet
    {
        $set = $this->getResourceSets()
            ->matching(
                Criteria::create()
                    ->where( Criteria::expr()->eq('type', $type) )
                    ->andWhere( Criteria::expr()->lte('level', $level) )
                    ->andWhere( Criteria::expr()->lte('times', $repeat) )
                    ->orderBy([
                        'level' => Order::Descending,
                        'times' => Order::Descending,
                    ])
            )->first();

        if ($type !== BuildingResourceSetType::Default)
            $set ??= $this->getResourceSet( level: $level, repeat: $repeat );

        return $set;
    }
}
