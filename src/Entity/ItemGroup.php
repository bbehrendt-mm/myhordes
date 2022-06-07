<?php

namespace App\Entity;

use App\Interfaces\NamedEntity;
use App\Interfaces\RandomGroup;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ItemGroupRepository")
 */
class ItemGroup implements RandomGroup
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=64)
     */
    private $name;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ItemGroupEntry", mappedBy="itemGroup", orphanRemoval=true, cascade={"persist", "detach"}, fetch="EAGER")
     */
    private $entries;

    public function __construct()
    {
        $this->entries = new ArrayCollection();
    }

    public function __clone()
    {
        if ($this->id) {
            $this->id = null;
            $cache = new ArrayCollection();
            foreach ($this->getEntries() as $entry)
                $cache->add( clone $entry );
            $this->entries = $cache;
        }
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

    /**
     * @return Collection|ItemGroupEntry[]
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(ItemGroupEntry $entry): self
    {
        if (!$this->entries->contains($entry)) {
            $this->entries[] = $entry;
            $entry->setItemGroup($this);
        }

        return $this;
    }

    public function findEntry( string $item_prototype_name ): ?ItemGroupEntry {
        foreach ($this->entries as $entry)
            /** @var ItemGroupEntry $entry */
            if ($entry->getPrototype()->getName() === $item_prototype_name)
                return $entry;
        return null;
    }

    public function toArray(): array {
        $array = [];
        foreach ($this->entries as $entry)
            /** @var ItemGroupEntry $entry */
            $array[ $entry->getPrototype()->getName() ] = $entry;
        return $array;
    }

    public function removeEntry(ItemGroupEntry $entry): self
    {
        if ($this->entries->contains($entry)) {
            $this->entries->removeElement($entry);
            // set the owning side to null (unless already changed)
            if ($entry->getItemGroup() === $this) {
                $entry->setItemGroup(null);
            }
        }

        return $this;
    }
}
