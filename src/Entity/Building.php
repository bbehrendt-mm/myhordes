<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Entity(repositoryClass="App\Repository\BuildingRepository")
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="town_unique",columns={"prototype_id","town_id"})
 * })
 */
class Building
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BuildingPrototype")
     * @ORM\JoinColumn(nullable=false)
     */
    private $prototype;

    /**
     * @ORM\Column(type="boolean")
     */
    private $complete = false;

    /**
     * @ORM\Column(type="integer")
     */
    private $ap = 0;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Town", inversedBy="buildings")
     * @ORM\JoinColumn(nullable=false)
     */
    private $town;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrototype(): BuildingPrototype
    {
        return $this->prototype;
    }

    public function setPrototype(?BuildingPrototype $prototype): self
    {
        $this->prototype = $prototype;

        return $this;
    }

    public function getComplete(): ?bool
    {
        return $this->complete;
    }

    public function setComplete(bool $complete): self
    {
        $this->complete = $complete;

        return $this;
    }

    public function getAp(): ?int
    {
        return $this->ap;
    }

    public function setAp(int $ap): self
    {
        $this->ap = $ap;

        return $this;
    }

    public function getTown(): ?Town
    {
        return $this->town;
    }

    public function setTown(?Town $town): self
    {
        $this->town = $town;

        return $this;
    }
}
