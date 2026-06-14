<?php

namespace App\Entity;

use App\Enum\AutomaticAccountMarkerType;
use App\Repository\AutomaticAccountMarkerRepository;
use Carbon\Carbon;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AutomaticAccountMarkerRepository::class)]
#[ORM\Index(name: 'aam_type', columns: ['type'])]
#[ORM\Index(name: 'aam_expires_at', columns: ['expires_at'])]
class AutomaticAccountMarker
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'automaticAccountMarkers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $expires_at = null;

    #[ORM\Column(length: 32, enumType: AutomaticAccountMarkerType::class)]
    private ?AutomaticAccountMarkerType $type = null;

    #[ORM\Column]
    private bool $enabled = true;

    #[ORM\ManyToOne]
    private ?TownRankingProxy $town = null;

    public function getId(): ?Uuid
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expires_at;
    }

    public function setExpiresAt(\DateTimeImmutable $expires_at): static
    {
        $this->expires_at = $expires_at;

        return $this;
    }

    public function getType(): ?AutomaticAccountMarkerType
    {
        return $this->type;
    }

    public function setType(AutomaticAccountMarkerType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getTown(): ?TownRankingProxy
    {
        return $this->town;
    }

    public function setTown(?TownRankingProxy $town): static
    {
        $this->town = $town;

        return $this;
    }

    public function setDefaultsFor(AutomaticAccountMarkerType $type, ?Carbon $fromDate = null, bool $enabled = true): static {
        $fromDate ??= Carbon::now();

        return $this
            ->setType( $type )
            ->setCreatedAt( $fromDate->toDateTimeImmutable() )
            ->setExpiresAt( $fromDate->clone()->addDays( $type->delayInDays() )->toDateTimeImmutable() )
            ->setEnabled( $enabled );
    }
}
