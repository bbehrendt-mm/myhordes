<?php

namespace App\Entity;

use App\Enum\UserSetting;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\Comparison;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: 'App\Repository\UserRepository')]
#[UniqueEntity('email')]
#[UniqueEntity('name')]
#[Table(name: '`user`')]
#[UniqueConstraint(name: 'email_unique', columns: ['email'])]
#[UniqueConstraint(name: 'user_name_unique', columns: ['name'])]
#[UniqueConstraint(name: 'user_twinoid_unique', columns: ['twinoid_id'])]
#[UniqueConstraint(name: 'user_etwin_unique', columns: ['eternal_id'])]
class User implements UserInterface, EquatableInterface, PasswordAuthenticatedUserInterface
{
    const USER_LEVEL_BASIC  =  0;
    const USER_LEVEL_CROW   =  3;
    const USER_LEVEL_ADMIN  =  4;
    const USER_LEVEL_SUPER  =  5;

    const USER_ROLE_ORACLE = 1 << 0;
    const USER_ROLE_ANIMAC = 1 << 1;
    const USER_ROLE_TEAM   = 1 << 2;

    const USER_ROLE_LIMIT_MODERATION = 1 << 10;

    const PRONOUN_NONE = 0;
    const PRONOUN_MALE = 1;
    const PRONOUN_FEMALE = 2;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 16)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 190)]
    private ?string $email = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $pass = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $validated = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: 'App\Entity\UserPendingValidation', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private ?UserPendingValidation $pendingValidation = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: 'App\Entity\Citizen')]
    private Collection $citizens;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: 'App\Entity\AdminBan')]
    private Collection $bannings;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: 'App\Entity\FoundRolePlayText', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $foundTexts;

    #[ORM\Column(type: 'integer')]
    private int $soulPoints = 0;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: 'App\Entity\Picto', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $pictos;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: 'App\Entity\Award', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $awards;

    #[ORM\Column(type: 'string', length: 32)]
    private string $externalId = '';

    #[ORM\OneToOne(targetEntity: 'App\Entity\Avatar', cascade: ['persist', 'remove'])]
    private ?Avatar $avatar = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(type: 'smallint')]
    private int $rightsElevation = 0;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: CitizenRankingProxy::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $pastLifes;

    #[ORM\Column(type: 'integer')]
    private int $heroDaysSpent = 0;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $twinoidID = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: TwinoidImport::class, cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $twinoidImports;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $importedSoulPoints = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $importedHeroDaysSpent = null;

    #[ORM\ManyToOne(targetEntity: Changelog::class)]
    private ?Changelog $latestChangelog = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ConnectionIdentifier::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $connectionIdentifiers;

    #[ORM\ManyToMany(targetEntity: ConnectionWhitelist::class, mappedBy: 'users', cascade: ['persist', 'remove'], fetch: 'EXTRA_LAZY')]
    private Collection $connectionWhitelists;

    #[ORM\Column(type: 'string', length: 190, nullable: true)]
    private ?string $eternalID = null;

    #[ORM\Column(type: 'string', length: 32, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $lastActionTimestamp = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?DateTimeInterface $deleteAfter = null;

    #[ORM\Column(type: 'integer')]
    private int $checkInt = 0;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ForumThreadSubscription::class, fetch: 'EXTRA_LAZY', orphanRemoval: true)]
    private Collection $forumThreadSubscriptions;

    #[ORM\OneToOne(targetEntity: Award::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Award $activeTitle = null;

    #[ORM\OneToOne(targetEntity: Award::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Award $activeIcon = null;

    #[ORM\Column(type: 'array', nullable: true)]
    private ?array $nameHistory = [];

    #[ORM\Column(type: 'date', nullable: true)]
    private ?DateTimeInterface $lastNameChange = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pendingEmail = null;

    #[ORM\Column(type: 'integer')]
    private int $roleFlag = 0;

    #[ORM\ManyToMany(targetEntity: User::class, fetch: 'EXTRA_LAZY')]
    private Collection $friends;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $settings = [];

    #[ORM\ManyToMany(targetEntity: Forum::class, fetch: 'EXTRA_LAZY')]
    private Collection $mutedForums;

    #[ORM\Column(length: 3, nullable: true)]
    private ?string $adminLang = null;

    public function __construct()
    {
        $this->citizens = new ArrayCollection();
        $this->foundTexts = new ArrayCollection();
        $this->pictos = new ArrayCollection();
        $this->bannings = new ArrayCollection();
        $this->pastLifes = new ArrayCollection();
        $this->twinoidImports = new ArrayCollection();
        $this->connectionIdentifiers = new ArrayCollection();
        $this->connectionWhitelists = new ArrayCollection();
        $this->forumThreadSubscriptions = new ArrayCollection();
        $this->friends = new ArrayCollection();
        $this->mutedForums = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }
    public function getUsername(): ?string
    {
        return $this->name;
    }
    public function getUserIdentifier(): string {
        return $this->getUsername();
    }
    public function getName(): ?string
    {
        return $this->displayName ?? $this->name;
    }
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }
    public function getValidated(): ?bool
    {
        return $this->validated;
    }
    public function setValidated(bool $validated): self
    {
        $this->validated = $validated;

        return $this;
    }
    public function getPendingValidation(): ?UserPendingValidation
    {
        return $this->pendingValidation;
    }
    public function setPendingValidation(?UserPendingValidation $pendingValidation): self
    {
        $this->pendingValidation = $pendingValidation;

        // set (or unset) the owning side of the relation if necessary
        $newUser = null === $pendingValidation ? null : $this;
        if ($pendingValidation && $pendingValidation->getUser() !== $newUser) {
            $pendingValidation->setUser($newUser);
        }

        return $this;
    }
    public function isDisabled(): bool {
        return $this->pass === null && $this->getEternalID() === null;
    }
    /**
     * @inheritDoc
     */
    public function getRoles(): array
    {
        $roles = [];
        if ($this->isDisabled()) return $roles;

        if     ($this->rightsElevation >= self::USER_LEVEL_SUPER)  $roles[] = 'ROLE_SUPER';
        elseif ($this->rightsElevation >= self::USER_LEVEL_ADMIN)  $roles[] = 'ROLE_ADMIN';
        elseif ($this->rightsElevation >= self::USER_LEVEL_CROW)   $roles[] = 'ROLE_CROW';

        if ($this->hasRoleFlag( self::USER_ROLE_ORACLE )) $roles[] = 'ROLE_ORACLE';
        if ($this->hasRoleFlag( self::USER_ROLE_ANIMAC )) $roles[] = 'ROLE_ANIMAC';
        if ($this->hasRoleFlag( self::USER_ROLE_TEAM ))   $roles[] = 'ROLE_TEAM';

        if (strstr($this->email, "@localhost") === "@localhost") $roles[] = 'ROLE_DUMMY';
        if ($this->email === 'crow') $roles[] = 'ROLE_CROW';
        if ($this->email === 'anim') $roles[] = 'ROLE_ANIMAC';

        if ($this->validated) $roles[] = 'ROLE_USER';
        else $roles[] = 'ROLE_REGISTERED';

        if ($this->getEternalID()) $roles[] = 'ROLE_ETERNAL';

        return array_unique($roles);
    }
    /**
     * @inheritDoc
     */
    public function getPassword(): ?string
    {
        return $this->pass;
    }
    public function setPassword(?string $pass): self {
        $this->pass = $pass;
        return $this;
    }
    /**
     * @inheritDoc
     */
    public function eraseCredentials() {}
    /**
     * @inheritDoc
     */
    public function isEqualTo(UserInterface $user): bool {
        if (!$this->getPassword() === null && $this->eternalID === null) return false;

        /** @var User $user */
        if (!is_a($user, self::class) || (!$user->getPassword() === null && $user->eternalID === null))
            return false;

        $b1 =
            $this->getUsername() === $user->getUsername() &&
            $this->getPassword() === $user->getPassword() &&
            $this->getRoles() === $user->getRoles() &&
            $this->checkInt === $user->checkInt;
        if ($user instanceof User) {
            return $b1 &&
                $this->getRightsElevation() === $user->getRightsElevation();
        } else return $b1;
    }

    public function getActiveCitizen(): ?Citizen {
        return $this->getCitizens()->matching( (new Criteria())
               ->where( new Comparison( 'active', Comparison::EQ, true )  )
        )->first() ?: null;
    }

    public function getCitizenFor(Town $town): ?Citizen {
        return $this->getCitizens()->matching( (new Criteria())
            ->where( new Comparison( 'town', Comparison::EQ, $town )  )
        )->first() ?: null;
    }

    /**
     * @return ArrayCollection<Citizen>|PersistentCollection<Citizen>
     */
    public function getCitizens(): ArrayCollection|PersistentCollection
    {
        return $this->citizens;
    }

    public function addCitizen(Citizen $citizen): self
    {
        if (!$this->citizens->contains($citizen)) {
            $this->citizens[] = $citizen;
            $citizen->setUser($this);
        }

        return $this;
    }
    public function removeCitizen(Citizen $citizen): self
    {
        if ($this->citizens->contains($citizen)) {
            $this->citizens->removeElement($citizen);
            // set the owning side to null (unless already changed)
            if ($citizen->getUser() === $this) {
                $citizen->setUser(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<FoundRolePlayText>
     */
    public function getFoundTexts(): Collection
    {
        return $this->foundTexts;
    }
    public function addFoundText(FoundRolePlayText $foundText): self
    {
        if (!$this->foundTexts->contains($foundText)) {
            $this->foundTexts[] = $foundText;
        }

        return $this;
    }
    public function removeFoundText(FoundRolePlayText $foundText): self
    {
        if ($this->foundTexts->contains($foundText)) {
            $this->foundTexts->removeElement($foundText);
        }

        return $this;
    }
    public function getAllSoulPoints(): int {
        return ($this->getSoulPoints() ?? 0) + ($this->getImportedSoulPoints() ?? 0);
    }
    public function getSoulPoints(): ?int
    {
        return $this->soulPoints;
    }
    public function setSoulPoints(int $soulPoints): self
    {
        $this->soulPoints = $soulPoints;

        return $this;
    }
    public function getExternalId(): ?string
    {
        return $this->externalId;
    }
    public function setExternalId(string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }
    /**
     * @return Collection<Award>
     */
    public function getAwards(): Collection {
        return $this->awards;
    }
    public function addAward(Award $award): self {
        if(!$this->awards->contains($award)) {
            $this->awards[] = $award;
            $award->setUser($this);
        }

        return $this;
    }
    public function removeAward(Award $award): self {
        if ($this->awards->contains($award)) {
            $this->awards->removeElement($award);
            // set the owning side to null (unless already changed)
            if ($award->getUser() === $this) {
                $award->setUser(null);
            }
        }

        return $this;
    }
    /**
     * @return ArrayCollection<Picto>|PersistentCollection<Picto>
     */
    public function getPictos(): ArrayCollection|PersistentCollection
    {
        return $this->pictos;
    }

    public function addPicto(Picto $picto): self
    {
        if (!$this->pictos->contains($picto)) {
            $this->pictos[] = $picto;
            $picto->setUser($this);
        }

        return $this;
    }
    public function removePicto(Picto $picto): self
    {
        if ($this->pictos->contains($picto)) {
            $this->pictos->removeElement($picto);
            // set the owning side to null (unless already changed)
            if ($picto->getUser() === $this) {
                $picto->setUser(null);
            }
        }

        return $this;
    }
    /**
     * @param int $persisted
     * @param PictoPrototype $prototype
     * @param Town|TownRankingProxy $town
     * @return Picto|null
     */
    public function findPicto( int $persisted, PictoPrototype $prototype, Town|TownRankingProxy $town ): ?Picto {
        return $this->getPictos()->matching( (new Criteria())
            ->where( new Comparison( 'persisted', Comparison::EQ, $persisted )  )
            ->andWhere( new Comparison( 'prototype', Comparison::EQ, $prototype )  )
            ->andWhere( new Comparison( is_a( $town, Town::class ) ? 'town' : 'townEntry', Comparison::EQ, $town )  )
        )->first() ?: null;
    }

    public function getAvatar(): ?Avatar
    {
        return $this->avatar;
    }
    public function setAvatar(?Avatar $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }
    public function getPreferSmallAvatars(): ?bool
    {
        return $this->getSetting( UserSetting::PreferSmallAvatars );
    }
    public function setPreferSmallAvatars(bool $preferSmallAvatars): self
    {
        return $this->setSetting( UserSetting::PreferSmallAvatars, $preferSmallAvatars );
    }
    public function getPostAsDefault(): ?string
    {
        return $this->getSetting( UserSetting::PostAs );
    }
    public function setPostAsDefault(?string $postAsDefault): self
    {
        return $this->setSetting( UserSetting::PostAs, $postAsDefault );
    }
    public function getLanguage(): ?string
    {
        return $this->language;
    }
    public function setLanguage(string $language): self
    {
        $this->language = $language;

        return $this;
    }
    public function getRightsElevation(): ?int
    {
        return $this->rightsElevation;
    }
    public function setRightsElevation(int $rightsElevation): self
    {
        $this->rightsElevation = $rightsElevation;

        return $this;
    }
    /**
     * @return Collection<CitizenRankingProxy>
     */
    public function getPastLifes(): Collection
    {
        return $this->pastLifes;
    }
    public function addPastLife(CitizenRankingProxy $pastLife): self
    {
        if (!$this->pastLifes->contains($pastLife)) {
            $this->pastLifes[] = $pastLife;
            $pastLife->setUser($this);
        }

        return $this;
    }
    public function removePastLife(CitizenRankingProxy $pastLife): self
    {
        if ($this->pastLifes->contains($pastLife)) {
            $this->pastLifes->removeElement($pastLife);
            // set the owning side to null (unless already changed)
            if ($pastLife->getUser() === $this) {
                $pastLife->setUser(null);
            }
        }

        return $this;
    }
    public function getAllHeroDaysSpent(): int
    {
        return ($this->getHeroDaysSpent() ?? 0) + ($this->getImportedHeroDaysSpent() ?? 0);
    }
    public function getHeroDaysSpent(): ?int
    {
        return $this->heroDaysSpent;
    }
    public function setHeroDaysSpent(int $heroDaysSpent): self
    {
        $this->heroDaysSpent = $heroDaysSpent;

        return $this;
    }
    public function getTwinoidID(): ?int
    {
        return $this->twinoidID;
    }
    public function setTwinoidID(?int $twinoidID): self
    {
        $this->twinoidID = $twinoidID;

        return $this;
    }
    /**
     * @return Collection<TwinoidImport>
     */
    public function getTwinoidImports(): Collection
    {
        return $this->twinoidImports;
    }
    public function addTwinoidImport(TwinoidImport $twinoidImport): self
    {
        if (!$this->twinoidImports->contains($twinoidImport)) {
            $this->twinoidImports[] = $twinoidImport;
            $twinoidImport->setUser($this);
        }

        return $this;
    }
    public function removeTwinoidImport(TwinoidImport $twinoidImport): self
    {
        if ($this->twinoidImports->contains($twinoidImport)) {
            $this->twinoidImports->removeElement($twinoidImport);
            // set the owning side to null (unless already changed)
            if ($twinoidImport->getUser() === $this) {
                $twinoidImport->setUser(null);
            }
        }

        return $this;
    }
    public function getImportedSoulPoints(): ?int
    {
        return $this->importedSoulPoints;
    }
    public function setImportedSoulPoints(?int $importedSoulPoints): self
    {
        $this->importedSoulPoints = $importedSoulPoints;

        return $this;
    }
    public function getImportedHeroDaysSpent(): ?int
    {
        return $this->importedHeroDaysSpent;
    }
    public function setImportedHeroDaysSpent(?int $importedHeroDaysSpent): self
    {
        $this->importedHeroDaysSpent = $importedHeroDaysSpent;

        return $this;
    }
    public function getLatestChangelog(): ?Changelog
    {
        return $this->latestChangelog;
    }
    public function setLatestChangelog(?Changelog $latestChangelog): self
    {
        $this->latestChangelog = $latestChangelog;

        return $this;
    }
    /**
     * @return Collection<ConnectionIdentifier>
     */
    public function getConnectionIdentifiers(): Collection
    {
        return $this->connectionIdentifiers;
    }
    public function addConnectionIdentifier(ConnectionIdentifier $connectionIdentifier): self
    {
        if (!$this->connectionIdentifiers->contains($connectionIdentifier)) {
            $this->connectionIdentifiers[] = $connectionIdentifier;
            $connectionIdentifier->setUser($this);
        }

        return $this;
    }
    public function removeConnectionIdentifier(ConnectionIdentifier $connectionIdentifier): self
    {
        if ($this->connectionIdentifiers->contains($connectionIdentifier)) {
            $this->connectionIdentifiers->removeElement($connectionIdentifier);
            // set the owning side to null (unless already changed)
            if ($connectionIdentifier->getUser() === $this) {
                $connectionIdentifier->setUser(null);
            }
        }

        return $this;
    }
    /**
     * @return Collection<ConnectionWhitelist>
     */
    public function getConnectionWhitelists(): Collection
    {
        return $this->connectionWhitelists;
    }
    public function addConnectionWhitelist(ConnectionWhitelist $connectionWhitelist): self
    {
        if (!$this->connectionWhitelists->contains($connectionWhitelist)) {
            $this->connectionWhitelists[] = $connectionWhitelist;
            $connectionWhitelist->addUser($this);
        }

        return $this;
    }
    public function removeConnectionWhitelist(ConnectionWhitelist $connectionWhitelist): self
    {
        if ($this->connectionWhitelists->contains($connectionWhitelist)) {
            $this->connectionWhitelists->removeElement($connectionWhitelist);
            $connectionWhitelist->removeUser($this);
        }

        return $this;
    }
    public function getEternalID(): ?string
    {
        return $this->eternalID;
    }
    public function setEternalID(?string $eternalID): self
    {
        $this->eternalID = $eternalID;

        return $this;
    }
    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }
    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;

        return $this;
    }
    public function getLastActionTimestamp(): ?DateTimeInterface
    {
        return $this->lastActionTimestamp;
    }
    public function setLastActionTimestamp(?DateTimeInterface $lastActionTimestamp): self
    {
        $this->lastActionTimestamp = $lastActionTimestamp;

        return $this;
    }
    public function getDeleteAfter(): ?DateTimeInterface
    {
        return $this->deleteAfter;
    }
    public function setDeleteAfter(?DateTimeInterface $deleteAfter): self
    {
        $this->deleteAfter = $deleteAfter;

        return $this;
    }
    public function getCheckInt(): ?int
    {
        return $this->checkInt;
    }
    public function setCheckInt(int $checkInt): self
    {
        $this->checkInt = $checkInt;

        return $this;
    }
    public function getDisableFx(): ?bool
    {
        return $this->getSetting( UserSetting::DisableEffects );
    }
    public function setDisableFx(bool $disableFx): self
    {
        return $this->setSetting( UserSetting::DisableEffects, $disableFx );
    }
    public function getExpert(): ?bool
    {
        return $this->getSetting( UserSetting::UseExpertMode );
    }
    public function setExpert(bool $expert): self
    {
        return $this->setSetting( UserSetting::UseExpertMode, $expert );
    }
    /**
     * @return Collection<ForumThreadSubscription>
     */
    public function getForumThreadSubscriptions(): Collection
    {
        return $this->forumThreadSubscriptions;
    }
    public function addForumThreadSubscription(ForumThreadSubscription $forumTreadSubscription): self
    {
        if (!$this->forumThreadSubscriptions->contains($forumTreadSubscription)) {
            $this->forumThreadSubscriptions[] = $forumTreadSubscription;
            $forumTreadSubscription->setUser($this);
        }

        return $this;
    }
    public function removeForumThreadSubscription(ForumThreadSubscription $forumThreadSubscription): self
    {
        if ($this->forumThreadSubscriptions->removeElement($forumThreadSubscription)) {
            // set the owning side to null (unless already changed)
            if ($forumThreadSubscription->getUser() === $this) {
                $forumThreadSubscription->setUser(null);
            }
        }

        return $this;
    }
    public function getActiveTitle(): ?Award
    {
        return $this->activeTitle;
    }
    public function setActiveTitle(?Award $activeTitle): self
    {
        $this->activeTitle = $activeTitle;

        return $this;
    }
    public function getActiveIcon(): ?Award
    {
        return $this->activeIcon;
    }
    public function setActiveIcon(?Award $activeIcon): self
    {
        $this->activeIcon = $activeIcon;

        return $this;
    }
    public function getNameHistory(): ?array
    {
        return $this->nameHistory;
    }
    public function setNameHistory(?array $nameHistory): self
    {
        $this->nameHistory = $nameHistory;

        return $this;
    }
    public function getLastNameChange(): ?DateTimeInterface
    {
        return $this->lastNameChange;
    }
    public function setLastNameChange(?DateTimeInterface $lastNameChange): self
    {
        $this->lastNameChange = $lastNameChange;

        return $this;
    }
    public function getUseICU(): ?bool
    {
        return $this->getSetting( UserSetting::UseICU );
    }
    public function setUseICU(bool $UseICU): self
    {
        return $this->setSetting( UserSetting::UseICU, $UseICU );
    }
    public function getPreferredPronoun(): ?int
    {
        return $this->getSetting( UserSetting::PreferredPronoun );
    }
    public function setPreferredPronoun(?int $preferredPronoun): self
    {
        return $this->setSetting( UserSetting::PreferredPronoun, $preferredPronoun );
    }
    public function getOpenModToolsSameWindow(): ?bool
    {
        return $this->getSetting( UserSetting::OpenDashboardInSameWindow );
    }
    public function setOpenModToolsSameWindow(bool $open_mod_tools_same_window): self
    {
        return $this->setSetting( UserSetting::OpenDashboardInSameWindow, $open_mod_tools_same_window );
    }
    public function getPendingEmail(): ?string
    {
        return $this->pendingEmail;
    }
    public function setPendingEmail(?string $pendingEmail): self
    {
        $this->pendingEmail = $pendingEmail;

        return $this;
    }
    public function getRoleFlag(): ?int
    {
        return $this->roleFlag;
    }
    public function setRoleFlag(int $roleFlag): self
    {
        $this->roleFlag = $roleFlag;

        return $this;
    }
    public function addRoleFlag(int $roleFlag): self {
        $this->setRoleFlag( $this->getRoleFlag() | $roleFlag );
        return $this;
    }
    public function removeRoleFlag(int $roleFlag): self {
        $this->setRoleFlag( $this->getRoleFlag() & ~$roleFlag );
        return $this;
    }
    public function hasRoleFlag(int $roleFlag): bool {
        return ($this->getRoleFlag() & $roleFlag) === $roleFlag;
    }
    public function getNoAutoFollowThreads(): ?bool
    {
        return $this->getSetting( UserSetting::NoAutomaticThreadSubscription );
    }
    public function setNoAutoFollowThreads(bool $noAutoFollowThreads): self
    {
        return $this->setSetting( UserSetting::NoAutomaticThreadSubscription, $noAutoFollowThreads );
    }
    /**
     * @return Collection<Citizen>
     */
    public function getFriends(): Collection
    {
        return $this->friends;
    }
    public function addFriend(self $friend): self
    {
        if (!$this->friends->contains($friend)) {
            $this->friends[] = $friend;
        }

        return $this;
    }
    public function removeFriend(self $friend): self
    {
        $this->friends->removeElement($friend);

        return $this;
    }
    public function getClassicBankSort(): ?bool
    {
        return $this->getSetting( UserSetting::ClassicBankSort );
    }
    public function setClassicBankSort(bool $classicBankSort): self
    {
        return $this->setSetting( UserSetting::ClassicBankSort, $classicBankSort );
    }
    public function getNoAutomaticNameManagement(): ?bool
    {
        return $this->getSetting( UserSetting::NoAutomaticNameManagement );
    }
    public function setNoAutomaticNameManagement(bool $noAutomaticNameManagement): self
    {
        return $this->setSetting( UserSetting::NoAutomaticNameManagement, $noAutomaticNameManagement );
    }
    public function getFlag(): ?string
    {
        return $this->getSetting( UserSetting::Flag );
    }
    public function setFlag(?string $flag): self
    {
        return $this->setSetting( UserSetting::Flag, $flag );
    }
    public function getSetting( UserSetting $setting ) {
        return ($this->getSettings() ?? [])[ $setting->value ] ?? $setting->defaultValue();
    }
    public function setSetting( UserSetting $setting, $value ): self {
        $settings = $this->getSettings() ?? [];
        $settings[ $setting->value ] = $value;
        return $this->setSettings( $settings );
    }
    protected function getSettings(): ?array
    {
        return $this->settings;
    }
    protected function setSettings(?array $settings): self
    {
        $this->settings = $settings;

        return $this;
    }
    /**
     * @return Collection<int, Forum>
     */
    public function getMutedForums(): Collection
    {
        return $this->mutedForums;
    }
    public function addMutedForum(Forum $mutedForum): self
    {
        if (!$this->mutedForums->contains($mutedForum)) {
            $this->mutedForums[] = $mutedForum;
        }

        return $this;
    }
    public function removeMutedForum(Forum $mutedForum): self
    {
        $this->mutedForums->removeElement($mutedForum);

        return $this;
    }

    public function getAdminLang(): ?string
    {
        return $this->adminLang;
    }

    public function setAdminLang(?string $adminLang): self
    {
        $this->adminLang = $adminLang;

        return $this;
    }
}
