<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserPendingValidationRepository")
 * @UniqueEntity("pkey")
 * @Table(uniqueConstraints={@UniqueConstraint(name="pkey_unique",columns={"pkey"})})
 */
class UserPendingValidation
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=16)
     */
    private $pkey;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\User", inversedBy="pendingValidation", cascade={"persist"})
     */
    private $user;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPkey(): ?string
    {
        return $this->pkey;
    }

    public function setPkey(string $pkey): self
    {
        $this->pkey = $pkey;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
}
