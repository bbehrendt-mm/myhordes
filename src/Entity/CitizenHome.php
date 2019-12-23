<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CitizenHomeRepository")
 */
class CitizenHome
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Inventory", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $chest;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Citizen", mappedBy="home", cascade={"persist", "remove"})
     */
    private $citizen;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChest(): ?Inventory
    {
        return $this->chest;
    }

    public function setChest(Inventory $chest): self
    {
        $this->chest = $chest;

        return $this;
    }

    public function getCitizen(): ?Citizen
    {
        return $this->citizen;
    }

    public function setCitizen(Citizen $citizen): self
    {
        $this->citizen = $citizen;

        // set the owning side of the relation if necessary
        if ($citizen->getHome() !== $this) {
            $citizen->setHome($this);
        }

        return $this;
    }
}
