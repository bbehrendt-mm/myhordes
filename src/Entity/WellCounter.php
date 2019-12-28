<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\WellCounterRepository")
 */
class WellCounter
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="smallint")
     */
    private $taken = 0;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Citizen", inversedBy="wellCounter", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $citizen;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTaken(): ?int
    {
        return $this->taken;
    }

    public function setTaken(int $taken): self
    {
        $this->taken = $taken;

        return $this;
    }

    public function getCitizen(): ?Citizen
    {
        return $this->citizen;
    }

    public function setCitizen(Citizen $citizen): self
    {
        $this->citizen = $citizen;

        return $this;
    }
}
