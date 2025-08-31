<?php

namespace App\Entity;

use App\Enum\Game\BuildingResourceSetType;
use App\Repository\BuildingConstructionResourceSetRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BuildingConstructionResourceSetRepository::class)]
class BuildingConstructionResourceSet
{
    #[ORM\ManyToOne(inversedBy: 'resourceSets')]
    #[ORM\JoinColumn(nullable: false)]
    #[ORM\Id]
    private ?BuildingPrototype $building = null;

    #[ORM\Column(enumType: BuildingResourceSetType::class)]
    #[ORM\Id]
    private ?BuildingResourceSetType $type = null;

    #[ORM\Column]
    #[ORM\Id]
    private int $level = 0;

    #[ORM\Column]
    #[ORM\Id]
    private int $times = 0;

    #[ORM\Column]
    private int $ap = 0;

    #[ORM\ManyToOne]
    private ?ItemGroup $resources = null;

    public function getBuilding(): ?BuildingPrototype
    {
        return $this->building;
    }

    public function setBuilding(?BuildingPrototype $building): static
    {
        $this->building = $building;

        return $this;
    }

    public function getType(): ?BuildingResourceSetType
    {
        return $this->type;
    }

    public function setType(BuildingResourceSetType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getTimes(): int
    {
        return $this->times;
    }

    public function setTimes(int $times): static
    {
        $this->times = $times;

        return $this;
    }

    public function getAp(): int
    {
        return $this->ap;
    }

    public function setAp(int $ap): static
    {
        $this->ap = $ap;

        return $this;
    }

    public function getResources(): ?ItemGroup
    {
        return $this->resources;
    }

    public function setResources(?ItemGroup $resources): static
    {
        $this->resources = $resources;

        return $this;
    }
}
