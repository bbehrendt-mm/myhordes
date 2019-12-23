<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ItemRepository")
 */
class Item
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
    private $broken;

    /**
     * @ORM\Column(type="boolean")
     */
    private $poison;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ItemPrototype")
     * @ORM\JoinColumn(nullable=false)
     */
    private $prototype;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Inventory", inversedBy="items")
     * @ORM\JoinColumn(nullable=false)
     */
    private $inventory;

    public function getBroken(): ?bool
    {
        return $this->broken;
    }

    public function setBroken(bool $broken): self
    {
        $this->broken = $broken;

        return $this;
    }

    public function getPoison(): ?bool
    {
        return $this->poison;
    }

    public function setPoison(bool $poison): self
    {
        $this->poison = $poison;

        return $this;
    }

    public function getPrototype(): ?ItemPrototype
    {
        return $this->prototype;
    }

    public function setPrototype(?ItemPrototype $prototype): self
    {
        $this->prototype = $prototype;

        return $this;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(?Inventory $inventory): self
    {
        $this->inventory = $inventory;

        return $this;
    }
}
