<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\RequirementRepository")
 * @UniqueEntity("name")
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="name_unique",columns={"name"})
 * })
 */
class Requirement
{
    const HideOnFail  = 0;
    const CrossOnFail = 1;
    const MessageOnFail = 2;

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
     * @ORM\ManyToOne(targetEntity="App\Entity\RequireStatus")
     */
    private $statusRequirement;

    /**
     * @ORM\Column(type="smallint")
     */
    private $failureMode;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $failureText;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\RequireItem")
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

    public function getStatusRequirement(): ?RequireStatus
    {
        return $this->statusRequirement;
    }

    public function setStatusRequirement(?RequireStatus $statusRequirement): self
    {
        $this->statusRequirement = $statusRequirement;

        return $this;
    }

    public function getFailureMode(): ?int
    {
        return $this->failureMode;
    }

    public function setFailureMode(int $failureMode): self
    {
        $this->failureMode = $failureMode;

        return $this;
    }

    public function getFailureText(): ?string
    {
        return $this->failureText;
    }

    public function setFailureText(?string $failureText): self
    {
        $this->failureText = $failureText;

        return $this;
    }

    public function getItem(): ?RequireItem
    {
        return $this->item;
    }

    public function setItem(?RequireItem $item): self
    {
        $this->item = $item;

        return $this;
    }
}
