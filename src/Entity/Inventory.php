<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;
use Doctrine\ORM\PersistentCollection;

#[ORM\Entity(repositoryClass: 'App\Repository\InventoryRepository')]
class Inventory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;
    #[ORM\OneToMany(targetEntity: Item::class, mappedBy: 'inventory', cascade: ['persist', 'remove'])]
    #[OrderBy(['essential' => 'DESC', 'prototype' => 'ASC'])]
    private Collection $items;
    #[ORM\OneToOne(targetEntity: Citizen::class, mappedBy: 'inventory', cascade: ['persist', 'remove'])]
    private ?Citizen $citizen = null;
    #[ORM\OneToOne(targetEntity: CitizenHome::class, mappedBy: 'chest', cascade: ['persist', 'remove'])]
    private ?CitizenHome $home = null;
    #[ORM\OneToOne(targetEntity: Town::class, mappedBy: 'bank', cascade: ['persist'])]
    private ?Town $town;
    #[ORM\OneToOne(targetEntity: Zone::class, mappedBy: 'floor', cascade: ['persist'])]
    private ?Zone $zone = null;
    #[ORM\OneToOne(targetEntity: RuinZone::class, mappedBy: 'floor', cascade: ['persist', 'remove'])]
    private ?RuinZone $ruinZone = null;
    #[ORM\OneToOne(targetEntity: RuinZone::class, mappedBy: 'roomFloor', cascade: ['persist'])]
    private ?RuinZone $ruinZoneRoom = null;
    #[ORM\OneToOne(targetEntity: Building::class, mappedBy: 'inventory', cascade: ['persist', 'remove'])]
    private ?Building $building = null;


    public function __construct()
    {
        $this->items = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return ArrayCollection<Item>|PersistentCollection<Item>
     */
    public function getItems(): ArrayCollection|PersistentCollection
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

    public function getBuilding(): ?Building
    {
        return $this->building;
    }

    public function setBuilding(?Building $building): static
    {
        $this->building = $building;

        return $this;
    }

    public function hasAnyItem(string ...$item_names): bool
    {
        return !empty(array_intersect( $item_names, $this->getItems()->map( fn(Item $i) => $i->getPrototype()->getName() )->toArray() ));
    }

    public function hasAllItems(string ...$item_names): bool
    {
        return count(array_intersect( $item_names, $this->getItems()->map( fn(Item $i) => $i->getPrototype()->getName() )->toArray() )) === count($item_names);
    }

    public function findTown(): ?Town {
        return $this->getTown() ??
            $this->getCitizen()?->getTown() ??
            $this->getZone()?->getTown() ??
            $this->getHome()?->getCitizen()?->getTown() ??
            $this->getRuinZone()?->getZone()?->getTown() ??
            $this->getRuinZoneRoom()?->getZone()?->getTown() ??
            $this->getBuilding()?->getTown() ??
            null;
    }
}
