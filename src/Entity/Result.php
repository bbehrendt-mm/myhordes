<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ResultRepository")
 * @UniqueEntity("name")
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="name_unique",columns={"name"})
 * })
 */
class Result
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
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectAP")
     */
    private $ap;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectStatus")
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectOriginalItem")
     */
    private $item;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectItemSpawn")
     */
    private $spawn;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectItemConsume")
     */
    private $consume;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectResultGroup")
     */
    private $resultGroup;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectZombies")
     */
    private $zombies;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\AffectBlueprint")
     */
    private $blueprint;

    /**
     * @ORM\Column(type="boolean", nullable=true)
     */
    private $rolePlayerText;

    public function __construct()
    {
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

    public function getAp(): ?AffectAP
    {
        return $this->ap;
    }

    public function setAp(?AffectAP $ap): self
    {
        $this->ap = $ap;

        return $this;
    }

    public function getStatus(): ?AffectStatus
    {
        return $this->status;
    }

    public function setStatus(?AffectStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getItem(): ?AffectOriginalItem
    {
        return $this->item;
    }

    public function setItem(?AffectOriginalItem $item): self
    {
        $this->item = $item;

        return $this;
    }

    public function getSpawn(): ?AffectItemSpawn
    {
        return $this->spawn;
    }

    public function setSpawn(?AffectItemSpawn $spawn): self
    {
        $this->spawn = $spawn;

        return $this;
    }

    public function getConsume(): ?AffectItemConsume
    {
        return $this->consume;
    }

    public function setConsume(?AffectItemConsume $consume): self
    {
        $this->consume = $consume;

        return $this;
    }

    public function getResultGroup(): ?AffectResultGroup
    {
        return $this->resultGroup;
    }

    public function setResultGroup(?AffectResultGroup $resultGroup): self
    {
        $this->resultGroup = $resultGroup;

        return $this;
    }

    public function getZombies(): ?AffectZombies
    {
        return $this->zombies;
    }

    public function setZombies(?AffectZombies $zombies): self
    {
        $this->zombies = $zombies;

        return $this;
    }

    public function getBlueprint(): ?AffectBlueprint
    {
        return $this->blueprint;
    }

    public function setBlueprint(?AffectBlueprint $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    public function getRolePlayerText(): ?bool
    {
        return $this->rolePlayerText;
    }

    public function setRolePlayerText(?bool $rolePlayerText): self
    {
        $this->rolePlayerText = $rolePlayerText;

        return $this;
    }
}
