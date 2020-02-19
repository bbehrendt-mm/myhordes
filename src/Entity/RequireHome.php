<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RequireHomeRepository")
 * @UniqueEntity("name")
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="name_unique",columns={"name"})
 * })
 */
class RequireHome
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
     * @ORM\Column(type="integer", nullable=true)
     */
    private $minLevel;

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

    public function getMinLevel(): ?int
    {
        return $this->minLevel;
    }

    public function setMinLevel(?int $minLevel): self
    {
        $this->minLevel = $minLevel;

        return $this;
    }
}
