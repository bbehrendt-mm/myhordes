<?php

namespace App\Entity;

use App\Repository\OfficialGroupRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=OfficialGroupRepository::class)
 */
class OfficialGroup
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=5)
     */
    private $lang;

    /**
     * @ORM\Column(type="text")
     */
    private $description;

    /**
     * @ORM\Column(type="blob")
     */
    private $icon;

    /**
     * @ORM\Column(type="boolean")
     */
    private $anon;

    /**
     * @ORM\OneToOne(targetEntity=UserGroup::class, cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $usergroup;

    /**
     * @ORM\Column(type="string", length=32)
     */
    private $avatarName;

    /**
     * @ORM\Column(type="string", length=9)
     */
    private $avatarExt;

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
}
