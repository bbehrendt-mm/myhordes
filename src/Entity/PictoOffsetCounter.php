<?php

namespace App\Entity;

use App\Repository\PictoOffsetCounterRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use function ArrayHelpers\array_get;
use function ArrayHelpers\array_set;

#[ORM\Entity(repositoryClass: PictoOffsetCounterRepository::class)]
#[Table]
#[UniqueConstraint(name: 'poc_assoc_unique', columns: ['user_id','season_id'])]
class PictoOffsetCounter
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Season $season = null;

    #[ORM\Column]
    private array $data = [];

    public function getId(): ?int
    {
        return $this->id;
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

    public function getSeason(): ?Season
    {
        return $this->season;
    }

    public function setSeason(?Season $season): static
    {
        $this->season = $season;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function setPictoCount(PictoPrototype $picto, int $count): static {
        $data = $this->getData() ?? [];
        array_set( $data, "picto.{$picto->getId()}", $count );
        return $this->setData($data);
    }

    public function getPictoCount(PictoPrototype $picto): int {
        return array_get( $this->getData() ?? [], "picto.{$picto->getId()}", 0 );
    }
}
