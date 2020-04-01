<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\TrashCounterRepository")
 */
class TrashCounter
{
    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $taken = 0;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Citizen", mappedBy="trashCounter", cascade={"persist", "remove"})
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

    public function setCitizen(?Citizen $citizen): self
    {
        $this->citizen = $citizen;

        // set (or unset) the owning side of the relation if necessary
        $newTrashCounter = null === $citizen ? null : $this;
        if ($citizen->getTrashCounter() !== $newTrashCounter) {
            $citizen->setTrashCounter($newTrashCounter);
        }

        return $this;
    }
}
