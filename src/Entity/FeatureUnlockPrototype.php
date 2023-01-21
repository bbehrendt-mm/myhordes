<?php

namespace App\Entity;

use App\Repository\FeatureUnlockPrototypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: FeatureUnlockPrototypeRepository::class)]
#[UniqueEntity('name')]
#[Table]
#[UniqueConstraint(name: 'feature_unlock_name_unique', columns: ['name'])]
class FeatureUnlockPrototype
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 32)]
    private $name;
    #[ORM\Column(type: 'string', length: 32)]
    private $icon;
    #[ORM\Column(type: 'text')]
    private $description;
    #[ORM\Column(type: 'string', length: 190)]
    private $label;

    #[ORM\Column]
    private bool $chargedByUse = false;

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
    public function getIcon(): ?string
    {
        return $this->icon;
    }
    public function setIcon(string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
    public function getLabel(): ?string
    {
        return $this->label;
    }
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function isChargedByUse(): bool
    {
        return $this->chargedByUse;
    }

    public function setChargedByUse(bool $chargedByUse): self
    {
        $this->chargedByUse = $chargedByUse;

        return $this;
    }
}
