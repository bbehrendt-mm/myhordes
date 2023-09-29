<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;

#[ORM\Entity(repositoryClass: 'App\Repository\InventoryRepository')]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\OneToMany(mappedBy: 'inventory', targetEntity: 'App\Entity\Item', cascade: ['persist', 'remove'])]
    #[OrderBy(['essential' => 'DESC', 'prototype' => 'ASC'])]
    private $items;
    #[ORM\OneToOne(mappedBy: 'inventory', targetEntity: 'App\Entity\Citizen', cascade: ['persist', 'remove'])]
    private $citizen;
    #[ORM\OneToOne(mappedBy: 'chest', targetEntity: 'App\Entity\CitizenHome', cascade: ['persist', 'remove'])]
    private $home;
    #[ORM\OneToOne(mappedBy: 'bank', targetEntity: 'App\Entity\Town', cascade: ['persist'])]
    private $town;
    #[ORM\OneToOne(mappedBy: 'floor', targetEntity: 'App\Entity\Zone', cascade: ['persist'])]
    private $zone;
    #[ORM\OneToOne(mappedBy: 'floor', targetEntity: 'App\Entity\RuinZone', cascade: ['persist', 'remove'])]
    private $ruinZone;
    #[ORM\OneToOne(mappedBy: 'roomFloor', targetEntity: RuinZone::class, cascade: ['persist'])]
    private $ruinZoneRoom;
	#[ORM\OneToOne(mappedBy: 'inventory', targetEntity: Building::class, cascade: ['persist'])]
	private $building;
    public function __construct()
    {
        $this->items = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    /**
     * @return Collection|Item[]
     */
    public function getItems(): Collection
    {
        return $this->items;
    }
    public function addItem(Item $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items[] = $item;
            $item->setInventory($this);
        }

        return $this;
    }
    public function removeItem(Item $item): self
    {
        if ($this->items->contains($item)) {
            $this->items->removeElement($item);
            // set the owning side to null (unless already changed)
            if ($item->getInventory() === $this) {
                $item->setInventory(null);
            }
        }

        return $this;
    }
    public function getCitizen(): ?Citizen
    {
        return $this->citizen;
    }
    public function setCitizen(Citizen $citizen): self
    {
        $this->citizen = $citizen;

        // set the owning side of the relation if necessary
        if ($citizen->getInventory() !== $this) {
            $citizen->setInventory($this);
        }

        return $this;
    }
    public function getHome(): ?CitizenHome
    {
        return $this->home;
    }
    public function setHome(CitizenHome $home): self
    {
        $this->home = $home;

        // set the owning side of the relation if necessary
        if ($home->getChest() !== $this) {
            $home->setChest($this);
        }

        return $this;
    }
    public function getTown(): ?Town
    {
        return $this->town;
    }
    public function setTown(Town $town): self
    {
        $this->town = $town;

        // set the owning side of the relation if necessary
        if ($town->getBank() !== $this) {
            $town->setBank($this);
        }

        return $this;
    }
    public function getZone(): ?Zone
    {
        return $this->zone;
    }
    public function setZone(Zone $zone): self
    {
        $this->zone = $zone;

        // set the owning side of the relation if necessary
        if ($zone->getFloor() !== $this) {
            $zone->setFloor($this);
        }

        return $this;
    }
    public function getRuinZone(): ?RuinZone
    {
        return $this->ruinZone;
    }
    public function setRuinZone(RuinZone $zone): self
    {
        $this->ruinZone = $zone;

        // set the owning side of the relation if necessary
        if ($zone->getFloor() !== $this) {
            $zone->setFloor($this);
        }

        return $this;
    }
    public function getRuinZoneRoom(): ?RuinZone
    {
        return $this->ruinZoneRoom;
    }
    public function setRuinZoneRoom(?RuinZone $ruinZoneRoom): self
    {
        $this->ruinZoneRoom = $ruinZoneRoom;

        // set (or unset) the owning side of the relation if necessary
        $newRoomFloor = null === $ruinZoneRoom ? null : $this;
        if ($ruinZoneRoom->getRoomFloor() !== $newRoomFloor) {
            $ruinZoneRoom->setRoomFloor($newRoomFloor);
        }

        return $this;
    }

	public function getBuilding(): Building {
		return $this->building;
	}

	/**
	 * @param mixed $building
	 */
	public function setBuilding(Building $building): self {
		$this->building = $building;

		// set the owning side of the relation if necessary
		if ($building->getInventory() !== $this) {
			$building->setInventory($this);
		}

		return $this;
	}
}
