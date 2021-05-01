<?php

namespace App\Entity;

use App\Repository\UserSponsorshipRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;

/**
 * @ORM\Entity(repositoryClass=UserSponsorshipRepository::class)
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="user_sponsored_unique",columns={"user_id"}),
 * })
 */
class UserSponsorship
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity=User::class)
     * @ORM\JoinColumn(nullable=false)
     */
    private $sponsor;

    /**
     * @ORM\OneToOne(targetEntity=User::class, cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(type="integer")
     */
    private $countedSoulPoints;

    /**
     * @ORM\Column(type="integer")
     */
    private $countedHeroExp;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSponsor(): ?User
    {
        return $this->sponsor;
    }

    public function setSponsor(?User $sponsor): self
    {
        $this->sponsor = $sponsor;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getCountedSoulPoints(): ?int
    {
        return $this->countedSoulPoints;
    }

    public function setCountedSoulPoints(int $countedSoulPoints): self
    {
        $this->countedSoulPoints = $countedSoulPoints;

        return $this;
    }

    public function getCountedHeroExp(): ?int
    {
        return $this->countedHeroExp;
    }

    public function setCountedHeroExp(int $countedHeroExp): self
    {
        $this->countedHeroExp = $countedHeroExp;

        return $this;
    }
}
