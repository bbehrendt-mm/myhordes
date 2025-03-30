<?php


namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: 'App\Repository\EmotesRepository')]
#[UniqueEntity('tag')]
#[Table]
#[UniqueConstraint(name: 'tag_unique', columns: ['tag'])]
class Emotes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 32)]
    private string $tag;
    #[ORM\Column(type: 'string', length: 64)]
    private string $path;
    #[ORM\Column(type: 'boolean')]
    private bool $isActive;
    #[ORM\Column(type: 'boolean', nullable: true)]
    private bool $requiresUnlock;
    #[ORM\Column(type: 'integer')]
    private int $orderIndex;
    #[ORM\Column(type: 'boolean')]
    private bool $i18n = false;

    #[ORM\Column(type: Types::JSON)]
    private ?array $groups = [];

    public function getOrderIndex(): ?int {
        return $this->orderIndex;
    }
    public function getId(): ?int {
        return $this->id;
    }
    public function getTag(): ?string {
        return $this->tag;
    }
    public function getPath(): ?string {
        return $this->path;
    }
    public function setOrderIndex(int $value): self {
        $this->orderIndex = $value;

        return $this;
    }
    public function setTag(string $value): self {
        $this->tag = $value;

        return $this;
    }
    public function setPath(string $value): self {
        $this->path = $value;

        return $this;
    }
    public function setIsActive(bool $value): self {
        $this->isActive = $value;

        return $this;
    }
    public function setRequiresUnlock(bool $value): self {
        $this->requiresUnlock = $value;

        return $this;
    }
    public function getI18n(): ?bool
    {
        return $this->i18n;
    }
    public function setI18n(bool $i18n): self
    {
        $this->i18n = $i18n;

        return $this;
    }

    public function getGroups(): ?array
    {
        return $this->groups;
    }

    public function setGroups(array $groups): static
    {
        $this->groups = $groups;

        return $this;
    }
}