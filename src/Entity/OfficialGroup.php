<?php

namespace App\Entity;

use App\Enum\OfficialGroupSemantic;
use App\Repository\OfficialGroupRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OfficialGroupRepository::class)]
class OfficialGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;
    #[ORM\Column(type: 'string', length: 5)]
    private string $lang;
    #[ORM\Column(type: 'text')]
    private string $description;
    #[ORM\Column(type: 'blob')]
    private $icon;
    #[ORM\Column(type: 'boolean')]
    private $anon;
    #[ORM\OneToOne(targetEntity: UserGroup::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private UserGroup $usergroup;
    #[ORM\Column(type: 'string', length: 32)]
    private string $avatarName;
    #[ORM\Column(type: 'string', length: 9)]
    private string $avatarExt;
    #[ORM\Column(type: 'integer', enumType: OfficialGroupSemantic::class)]
    private OfficialGroupSemantic $semantic = OfficialGroupSemantic::None;

    #[ORM\Column]
    private bool $ticketStyleReadMarkers = false;
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getLang(): ?string
    {
        return $this->lang;
    }
    public function setLang(string $lang): self
    {
        $this->lang = $lang;

        return $this;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
    public function getIcon()
    {
        return $this->icon;
    }
    public function setIcon($icon): self
    {
        $this->icon = $icon;

        return $this;
    }
    public function getAnon(): ?bool
    {
        return $this->anon;
    }
    public function setAnon(bool $anon): self
    {
        $this->anon = $anon;

        return $this;
    }
    public function getUsergroup(): ?UserGroup
    {
        return $this->usergroup;
    }
    public function setUsergroup(UserGroup $usergroup): self
    {
        $this->usergroup = $usergroup;

        return $this;
    }
    public function getAvatarName(): ?string
    {
        return $this->avatarName;
    }
    public function setAvatarName(string $avatarName): self
    {
        $this->avatarName = $avatarName;

        return $this;
    }
    public function getAvatarExt(): ?string
    {
        return $this->avatarExt;
    }
    public function setAvatarExt(string $avatarExt): self
    {
        $this->avatarExt = $avatarExt;

        return $this;
    }
    public function getSemantic(): ?OfficialGroupSemantic
    {
        return $this->semantic;
    }
    public function setSemantic(OfficialGroupSemantic $semantic): self
    {
        $this->semantic = $semantic;

        return $this;
    }

    public function isTicketStyleReadMarkers(): bool
    {
        return $this->ticketStyleReadMarkers;
    }

    public function setTicketStyleReadMarkers(bool $ticketStyleReadMarkers): static
    {
        $this->ticketStyleReadMarkers = $ticketStyleReadMarkers;

        return $this;
    }
}
