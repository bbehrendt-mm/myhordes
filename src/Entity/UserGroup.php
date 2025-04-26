<?php

namespace App\Entity;

use App\Repository\UserGroupRepository;
use ArrayHelpers\Arr;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
class UserGroup
{
    const int GroupTypeDefault = 0;
    const int GroupTypeDefaultUserGroup = 1;
    const int GroupTypeDefaultElevatedGroup = 2;
    const int GroupTypeDefaultModeratorGroup = 3;
    const int GroupTypeDefaultAdminGroup = 4;
    const int GroupTypeDefaultOracleGroup = 5;
    const int GroupTypeDefaultAnimactorGroup = 6;
    const int GroupTypeDefaultDevGroup = 7;
    const int GroupTypeDefaultArtisticGroup = 8;
    const int GroupTownInhabitants = 10;
    const int GroupTownAnimaction = 11;
    const int GroupSmallCoalition = 101;
    const int GroupMessageGroup = 201;
    const int GroupOfficialGroup = 301;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 190)]
    private $name;
    #[ORM\Column(type: 'integer')]
    private $type = self::GroupTypeDefault;
    #[ORM\Column(type: 'integer', nullable: true)]
    private $ref1;
    #[ORM\Column(type: 'integer', nullable: true)]
    private $ref2;
    #[ORM\OneToOne(mappedBy: 'userGroup', targetEntity: Shoutbox::class, cascade: ['persist', 'remove'])]
    private $shoutbox;
    #[ORM\Column(type: 'integer', nullable: true)]
    private $ref3;

    #[ORM\Column(nullable: true)]
    private ?array $properties = null;
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
    public function getType(): ?int
    {
        return $this->type;
    }
    public function setType(int $type): self
    {
        $this->type = $type;

        return $this;
    }
    public function getRef1(): ?int
    {
        return $this->ref1;
    }
    public function setRef1(?int $ref1): self
    {
        $this->ref1 = $ref1;

        return $this;
    }
    public function getRef2(): ?int
    {
        return $this->ref2;
    }
    public function setRef2(?int $ref2): self
    {
        $this->ref2 = $ref2;

        return $this;
    }
    public function getEntries(): ?Shoutbox
    {
        return $this->entries;
    }
    public function setEntries(Shoutbox $entries): self
    {
        $this->entries = $entries;

        // set the owning side of the relation if necessary
        if ($entries->getUserGroup() !== $this) {
            $entries->setUserGroup($this);
        }

        return $this;
    }
    public function getRef3(): ?int
    {
        return $this->ref3;
    }
    public function setRef3(?int $ref3): self
    {
        $this->ref3 = $ref3;

        return $this;
    }

    public function getProperties(): ?array
    {
        return $this->properties;
    }

    public function setProperties(?array $properties): static
    {
        $this->properties = $properties;

        return $this;
    }

    public function getProperty(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->properties ?? [], $key, $default);
    }

    public function setProperty(string $key, mixed $value): static
    {
        $p = $this->getProperties() ?? [];
        Arr::set($p, $key, $value);
        return $this->setProperties( $p );
    }
}
