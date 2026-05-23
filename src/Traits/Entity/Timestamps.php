<?php

namespace App\Traits\Entity;

use Doctrine\ORM\Mapping as ORM;

trait Timestamps
{
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function lifeCycle_prePersist() : void
    {
        $this->setCreatedAt(new \DateTimeImmutable())->setUpdatedAt(new \DateTimeImmutable());
    }

    #[ORM\PreUpdate]
    public function lifeCycle_preUpdate() : void
    {
        $this->setUpdatedAt(new \DateTimeImmutable());
    }
}
