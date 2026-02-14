<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: 'App\Repository\AdminDeletionRepository')]
class AdminDeletion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sourceUser = null;
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $timestamp = null;
    #[ORM\Column(type: 'string', length: 255)]
    private ?string $reason = null;
    #[ORM\OneToOne(targetEntity: Post::class, inversedBy: 'adminDeletion', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Post $post = null;
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getSourceUser(): ?User
    {
        return $this->sourceUser;
    }
    public function setSourceUser(?User $sourceUser): self
    {
        $this->sourceUser = $sourceUser;

        return $this;
    }
    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }
    public function setTimestamp(\DateTimeInterface $timestamp): self
    {
        $this->timestamp = $timestamp;

        return $this;
    }
    public function getReason(): ?string
    {
        return $this->reason;
    }
    public function setReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }
    public function getPost(): ?Post
    {
        return $this->post;
    }
    public function setPost(?Post $post): self
    {
        $this->post = $post;

        return $this;
    }
}
