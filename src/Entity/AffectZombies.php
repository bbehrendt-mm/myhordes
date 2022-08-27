<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: 'App\Repository\AffectZombiesRepository')]
#[UniqueEntity('name')]
#[Table]
#[UniqueConstraint(name: 'affect_zombies_name_unique', columns: ['name'])]
class AffectZombies
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 64)]
    private $name;
    #[ORM\Column(type: 'integer')]
    private $min;
    #[ORM\Column(type: 'integer')]
    private $max;
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
    public function getMin(): ?int
    {
        return $this->min;
    }
    public function setMin(int $min): self
    {
        $this->min = $min;

        return $this;
    }
    public function getMax(): ?int
    {
        return $this->max;
    }
    public function setMax(int $max): self
    {
        $this->max = $max;

        return $this;
    }
}
