<?php

namespace App\Entity;

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
}
