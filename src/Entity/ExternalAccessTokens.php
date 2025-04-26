<?php

namespace App\Entity;

use App\Enum\Configuration\ExternalTokenType;
use App\Repository\ExternalAccessTokensRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExternalAccessTokensRepository::class)]
#[ORM\Index(name: 'eat_type', columns: ['type'])]
#[ORM\Index(name: 'eat_env', columns: ['env'])]
#[ORM\Index(name: 'eat_access', columns: ['type','env','active'])]
#[ORM\UniqueConstraint(name: 'eat_key', columns: ['type','env','token'])]
class ExternalAccessTokens
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16, enumType: ExternalTokenType::class)]
    private ?ExternalTokenType $type = null;

    #[ORM\Column]
    private ?bool $active = null;

    #[ORM\Column(length: 255)]
    private ?string $token = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $expires = null;

    #[ORM\Column(length: 16)]
    private ?string $env = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?ExternalTokenType
    {
        return $this->type;
    }

    public function setType(ExternalTokenType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getExpires(): ?\DateTimeInterface
    {
        return $this->expires;
    }

    public function setExpires(\DateTimeInterface $expires): static
    {
        $this->expires = $expires;

        return $this;
    }

    public function getEnv(): ?string
    {
        return $this->env;
    }

    public function setEnv(string $env): static
    {
        $this->env = $env;

        return $this;
    }
}
