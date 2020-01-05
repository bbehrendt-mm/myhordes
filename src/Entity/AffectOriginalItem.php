<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\AffectOriginalItemRepository")
 * @UniqueEntity("name")
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="name_unique",columns={"name"})
 * })
 */
class AffectOriginalItem
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $name;

    /**
     * @ORM\Column(type="boolean")
     */
    private $consume;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ItemPrototype")
     */
    private $morph;

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

    public function getConsume(): ?bool
    {
        return $this->consume;
    }

    public function setConsume(bool $consume): self
    {
        $this->consume = $consume;

        return $this;
    }

    public function getMorph(): ?ItemPrototype
    {
        return $this->morph;
    }

    public function setMorph(?ItemPrototype $morph): self
    {
        $this->morph = $morph;

        return $this;
    }
}
