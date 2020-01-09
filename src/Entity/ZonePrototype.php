<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ZonePrototypeRepository")
 */
class ZonePrototype
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $label;

    /**
     * @ORM\Column(type="integer")
     */
    private $campingLevel;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ItemGroup", cascade={"persist","remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $drops;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCampingLevel(): ?int
    {
        return $this->campingLevel;
    }

    public function setCampingLevel(int $campingLevel): self
    {
        $this->campingLevel = $campingLevel;

        return $this;
    }

    public function getDrops(): ?ItemGroup
    {
        return $this->drops;
    }

    public function setDrops(?ItemGroup $drops): self
    {
        $this->drops = $drops;

        return $this;
    }
}
