<?php

namespace App\Entity;

use App\Repository\TownAdminUserRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\UniqueConstraint;

#[ORM\Entity(repositoryClass: TownAdminUserRepository::class)]
#[UniqueConstraint(name: 'town_admin_unique', columns: ['town', 'user'])]
class TownAdminUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne()]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Town $town = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?bool $fullAccess = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTown(): ?Town
    {
        return $this->town;
    }

    public function setTown(?Town $town): static
    {
        $this->town = $town;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function isFullAccess(): ?bool
    {
        return $this->fullAccess;
    }

    public function setFullAccess(bool $fullAccess): static
    {
        $this->fullAccess = $fullAccess;

        return $this;
    }
}
