<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CitizenRepository")
 */
class Citizen
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="boolean")
     */
    private $alive = true;

    /**
     * @ORM\Column(type="smallint")
     */
    private $ap = 6;

    /**
     * @ORM\Column(type="boolean")
     */
    private $active = true;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="citizens")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\CitizenStatus")
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CitizenProfession")
     * @ORM\JoinColumn(nullable=false)
     */
    private $profession;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Inventory", inversedBy="citizen", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $inventory;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Town", inversedBy="citizens")
     * @ORM\JoinColumn(nullable=false)
     */
    private $town;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\CitizenHome", inversedBy="citizen", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $home;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\WellCounter", mappedBy="citizen", cascade={"persist", "remove"})
     */
    private $wellCounter;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zone", inversedBy="citizens")
     */
    private $zone;

    public function __construct()
    {
        $this->status = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlive(): ?bool
    {
        return $this->alive;
    }

    public function setAlive(bool $alive): self
    {
        $this->alive = $alive;

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

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection|CitizenStatus[]
     */
    public function getStatus(): Collection
    {
        return $this->status;
    }

    public function addStatus(CitizenStatus $status): self
    {
        if (!$this->status->contains($status)) {
            $this->status[] = $status;
        }

        return $this;
    }

    public function removeStatus(CitizenStatus $status): self
    {
        if ($this->status->contains($status)) {
            $this->status->removeElement($status);
        }

        return $this;
    }

    public function getProfession(): ?CitizenProfession
    {
        return $this->profession;
    }

    public function setProfession(?CitizenProfession $profession): self
    {
        $this->profession = $profession;

        return $this;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(Inventory $inventory): self
    {
        $this->inventory = $inventory;

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

    public function getHome(): ?CitizenHome
    {
        return $this->home;
    }

    public function setHome(CitizenHome $home): self
    {
        $this->home = $home;

        return $this;
    }

    public function getWellCounter(): ?WellCounter
    {
        return $this->wellCounter;
    }

    public function setWellCounter(WellCounter $wellCounter): self
    {
        $this->wellCounter = $wellCounter;

        // set the owning side of the relation if necessary
        if ($wellCounter->getCitizen() !== $this) {
            $wellCounter->setCitizen($this);
        }

        return $this;
    }

    public function getZone(): ?Zone
    {
        return $this->zone;
    }

    public function setZone(?Zone $zone): self
    {
        $this->zone = $zone;

        return $this;
    }
}
