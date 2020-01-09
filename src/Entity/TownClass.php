<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TownClassRepository")
 * @UniqueEntity("name")
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="name_unique",columns={"name"})
 * })
 */
class TownClass
{

    const EASY = 'small';
    const HARD = 'panda';

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private $name;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $label;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Town", mappedBy="type", orphanRemoval=true)
     */
    private $towns;

    /**
     * @ORM\Column(type="integer")
     */
    private $wellMin;

    /**
     * @ORM\Column(type="integer")
     */
    private $wellMax;

    /**
     * @ORM\Column(type="integer")
     */
    private $mapMin;

    /**
     * @ORM\Column(type="integer")
     */
    private $mapMax;

    /**
     * @ORM\Column(type="integer")
     */
    private $ruinsMin;

    /**
     * @ORM\Column(type="integer")
     */
    private $ruinsMax;

    public function __construct()
    {
        $this->towns = new ArrayCollection();
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

    /**
     * @return Collection|Town[]
     */
    public function getTowns(): Collection
    {
        return $this->towns;
    }

    public function addTown(Town $town): self
    {
        if (!$this->towns->contains($town)) {
            $this->towns[] = $town;
            $town->setType($this);
        }

        return $this;
    }

    public function removeTown(Town $town): self
    {
        if ($this->towns->contains($town)) {
            $this->towns->removeElement($town);
            // set the owning side to null (unless already changed)
            if ($town->getType() === $this) {
                $town->setType(null);
            }
        }

        return $this;
    }

    public function getWellMin(): ?int
    {
        return $this->wellMin;
    }

    public function setWellMin(int $wellMin): self
    {
        $this->wellMin = $wellMin;

        return $this;
    }

    public function getWellMax(): ?int
    {
        return $this->wellMax;
    }

    public function setWellMax(int $wellMax): self
    {
        $this->wellMax = $wellMax;

        return $this;
    }

    public function getMapMin(): ?int
    {
        return $this->mapMin;
    }

    public function setMapMin(int $mapMin): self
    {
        $this->mapMin = $mapMin;

        return $this;
    }

    public function getMapMax(): ?int
    {
        return $this->mapMax;
    }

    public function setMapMax(int $mapMax): self
    {
        $this->mapMax = $mapMax;

        return $this;
    }

    public function getRuinsMin(): ?int
    {
        return $this->ruinsMin;
    }

    public function setRuinsMin(int $ruinsMin): self
    {
        $this->ruinsMin = $ruinsMin;

        return $this;
    }

    public function getRuinsMax(): ?int
    {
        return $this->ruinsMax;
    }

    public function setRuinsMax(int $ruinsMax): self
    {
        $this->ruinsMax = $ruinsMax;

        return $this;
    }
}
