<?php

namespace App\Entity;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\ORMException;

/**
 * @ORM\Entity(repositoryClass="App\Repository\CitizenRepository")
 * @ORM\HasLifecycleCallbacks()
 */
class Citizen
{
    const Thrown = 1;
    const Watered = 2;
    const Cooked = 3;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="boolean")
     */
    private $alive = true;

    /**
     * @ORM\Column(type="smallint")
     */
    private $ap = 6;

    /**
     * @ORM\Column(type="boolean")
     */
    private $active = true;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\User", inversedBy="citizens")
     * @ORM\JoinColumn(nullable=false)
     */
    private $user;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\CitizenStatus")
     */
    private $status;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CitizenProfession")
     * @ORM\JoinColumn(nullable=false)
     */
    private $profession;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\CitizenRole")
     * @ORM\JoinColumn(nullable=true)
     */
    private $roles;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\CitizenVote", orphanRemoval=true)
     * @ORM\JoinColumn(nullable=true)
     */
    private $votes;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Inventory", inversedBy="citizen", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $inventory;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Town", inversedBy="citizens")
     * @ORM\JoinColumn(nullable=false)
     */
    private $town;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\CitizenHome", inversedBy="citizen", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $home;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Zone", inversedBy="citizens")
     */
    private $zone;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DigTimer", mappedBy="citizen", orphanRemoval=true)
     */
    private $digTimers;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\DailyUpgradeVote", mappedBy="citizen", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $dailyUpgradeVote;

    /**
     * @ORM\Column(type="integer")
     */
    private $walkingDistance = 0;

    /**
     * @ORM\Column(type="integer")
     */
    private $survivedDays = 0;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\CauseOfDeath")
     * @ORM\JoinColumn(nullable=false)
     */
    private $causeOfDeath;

    /**
     * @ORM\Column(type="integer")
     */
    private $Bp;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ExpeditionRoute", mappedBy="owner", orphanRemoval=true, cascade={"persist", "remove"})
     */
    private $expeditionRoutes;

    /**
     * @ORM\Column(type="boolean")
     */
    private $banished = false;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Complaint", mappedBy="culprit", orphanRemoval=true)
     */
    private $complaints;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\HeroicActionPrototype")
     */
    private $heroicActions;

    /**
     * @ORM\Column(type="integer")
     */
    private $campingCounter = 0;

    /**
     * @ORM\Column(type="integer")
     */
    private $campingTimestamp = 0;

    /**
     * @ORM\Column(type="float")
     */
    private $campingChance = 0;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ActionCounter", mappedBy="citizen", orphanRemoval=true, cascade={"persist", "remove"})
     */
    private $actionCounters;

    /**
     * @ORM\Column(type="integer")
     */
    private $lastActionTimestamp = 0;

    /**
     * @ORM\Column(type="integer")
     */
    private $pm;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\CitizenEscortSettings", inversedBy="citizen", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $escortSettings;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CitizenEscortSettings", mappedBy="leader")
     */
    private $leadingEscorts;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $lastWords;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private $comment;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $disposed;

    /**
     * @ORM\ManyToMany(targetEntity="App\Entity\Citizen")
     * @ORM\JoinTable(name="citizen_disposed",
     *     joinColumns={@ORM\JoinColumn(name="id", referencedColumnName="id", unique=true)},
     *     inverseJoinColumns={@ORM\JoinColumn(name="disposed_by_id", referencedColumnName="id")})
     */
    private $disposedBy;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\CitizenWatch", mappedBy="citizen", orphanRemoval=true)
     */
    private $citizenWatch;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\BankAntiAbuse", mappedBy="citizen", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $bankAntiAbuse;

    /**
     * @ORM\Column(type="integer")
     */
    private $ghulHunger = 0;

    /**
     * @ORM\OneToMany(targetEntity=PrivateMessageThread::class, mappedBy="recipient", orphanRemoval=true)
     */
    private $privateMessageThreads;

    /**
     * @ORM\OneToOne(targetEntity=CitizenRankingProxy::class, inversedBy="citizen", cascade={"persist"})
     */
    private $rankingEntry;

    /**
     * @ORM\OneToMany(targetEntity=RuinExplorerStats::class, mappedBy="citizen", orphanRemoval=true, cascade={"persist", "remove"})
     */
    private $explorerStats;

    /**
     * @ORM\OneToOne(targetEntity=BuildingVote::class, mappedBy="citizen", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=true, onDelete="SET NULL")
     */
    private $buildingVote;

    /**
     * @ORM\ManyToMany(targetEntity=HelpNotificationMarker::class)
     */
    private $helpNotifications;

    /**
     * @ORM\Column(type="boolean")
     */
    private $hasSeenGazette = false;

    public function __construct()
    {
        $this->status = new ArrayCollection();
        $this->digTimers = new ArrayCollection();
        $this->expeditionRoutes = new ArrayCollection();
        $this->complaints = new ArrayCollection();
        $this->heroicActions = new ArrayCollection();
        $this->actionCounters = new ArrayCollection();
        $this->roles = new ArrayCollection();
        $this->votes = new ArrayCollection();
        $this->leadingEscorts = new ArrayCollection();
        $this->disposedBy = new ArrayCollection();
        $this->citizenWatch = new ArrayCollection();
        $this->privateMessageThreads = new ArrayCollection();
        $this->explorerStats = new ArrayCollection();
        $this->helpNotifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAlive(): ?bool
    {
        return $this->alive;
    }

    public function setAlive(bool $alive): self
    {
        $this->alive = $alive;

        return $this;
    }

    public function getAp(): ?int
    {
        return $this->ap;
    }

    public function setAp(int $ap): self
    {
        $this->ap = $ap;

        return $this;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection|CitizenStatus[]
     */
    public function getStatus(): Collection
    {
        return $this->status;
    }

    public function addStatus(CitizenStatus $status): self
    {
        if (!$this->status->contains($status)) {
            $this->status[] = $status;
        }

        return $this;
    }

    public function removeStatus(CitizenStatus $status): self
    {
        if ($this->status->contains($status)) {
            $this->status->removeElement($status);
        }

        return $this;
    }

    public function getProfession(): ?CitizenProfession
    {
        return $this->profession;
    }

    public function setProfession(?CitizenProfession $profession): self
    {
        $this->profession = $profession;

        return $this;
    }

    /**
     * @return Collection|CitizenRole[]
     */
    public function getRoles(): Collection
    {
        return $this->roles;
    }

    public function addRole(CitizenRole $role): self
    {
        if (!$this->roles->contains($role)) {
            $this->roles[] = $role;
        }

        return $this;
    }

    public function removeRole(CitizenRole $role): self
    {
        if ($this->roles->contains($role)) {
            $this->roles->removeElement($role);
        }

        return $this;
    }

    public function hasRole(string $name): bool {
        foreach ($this->getRoles() as $role)
            if ($role->getName() === $name) return true;
        return false;
    }

    /**
     * @return Collection|CitizenVote[]
     */
    public function getVotes(): Collection
    {
        return $this->votes;
    }

    public function addVote(CitizenVote $vote): self
    {
        if (!$this->votes->contains($vote)) {
            $this->votes[] = $vote;
        }

        return $this;
    }

    public function removeVote(CitizenVote $vote): self
    {
        if ($this->votes->contains($vote)) {
            $this->votes->removeElement($vote);
        }

        return $this;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(Inventory $inventory): self
    {
        $this->inventory = $inventory;

        return $this;
    }

    public function getTown(): ?Town
    {
        return $this->town;
    }

    public function setTown(?Town $town): self
    {
        $this->town = $town;

        return $this;
    }

    public function getHome(): ?CitizenHome
    {
        return $this->home;
    }

    public function setHome(CitizenHome $home): self
    {
        $this->home = $home;

        return $this;
    }

    public function getZone(): ?Zone
    {
        return $this->zone;
    }

    public function setZone(?Zone $zone): self
    {
        $this->zone = $zone;

        return $this;
    }

    /**
     * @return Collection|DigTimer[]
     */
    public function getDigTimers(): Collection
    {
        return $this->digTimers;
    }

    public function addDigTimer(DigTimer $digTimer): self
    {
        if (!$this->digTimers->contains($digTimer)) {
            $this->digTimers[] = $digTimer;
            $digTimer->setCitizen($this);
        }

        return $this;
    }

    public function removeDigTimer(DigTimer $digTimer): self
    {
        if ($this->digTimers->contains($digTimer)) {
            $this->digTimers->removeElement($digTimer);
            // set the owning side to null (unless already changed)
            if ($digTimer->getCitizen() === $this) {
                $digTimer->setCitizen(null);
            }
        }

        return $this;
    }

    public function getCurrentDigTimer(): ?DigTimer {
        if (!$this->getZone()) return null;
        foreach ($this->getDigTimers() as $digTimer)
            if ($digTimer->getZone() === $this->getZone())
                return $digTimer;
        return null;
    }

    public function getDailyUpgradeVote(): ?DailyUpgradeVote
    {
        return $this->dailyUpgradeVote;
    }

    public function setDailyUpgradeVote(?DailyUpgradeVote $dailyUpgradeVote): self
    {
        $this->dailyUpgradeVote = $dailyUpgradeVote;

        // set the owning side of the relation if necessary
        if ($dailyUpgradeVote !== null && $dailyUpgradeVote->getCitizen() !== $this) {
            $dailyUpgradeVote->setCitizen($this);
        }

        return $this;
    }

    public function getWalkingDistance(): ?int
    {
        return $this->walkingDistance;
    }

    public function setWalkingDistance(int $walkingDistance): self
    {
        $this->walkingDistance = $walkingDistance;

        return $this;
    }

    public function getSurvivedDays(): ?int
    {
        return $this->survivedDays;
    }

    public function setSurvivedDays(int $survivedDays): self
    {
        $this->survivedDays = $survivedDays;

        return $this;
    }

    public function getCauseOfDeath(): ?CauseOfDeath
    {
        return $this->causeOfDeath;
    }

    public function setCauseOfDeath(?CauseOfDeath $causeOfDeath): self
    {
        $this->causeOfDeath = $causeOfDeath;

        return $this;
    }

    public function getBp(): ?int
    {
        return $this->Bp;
    }

    public function setBp(int $Bp): self
    {
        $this->Bp = $Bp;

        return $this;
    }

    /**
     * @return Collection|ExpeditionRoute[]
     */
    public function getExpeditionRoutes(): Collection
    {
        return $this->expeditionRoutes;
    }

    public function addExpeditionRoute(ExpeditionRoute $expeditionRoute): self
    {
        if (!$this->expeditionRoutes->contains($expeditionRoute)) {
            $this->expeditionRoutes[] = $expeditionRoute;
            $expeditionRoute->setOwner($this);
        }

        return $this;
    }

    public function removeExpeditionRoute(ExpeditionRoute $expeditionRoute): self
    {
        if ($this->expeditionRoutes->contains($expeditionRoute)) {
            $this->expeditionRoutes->removeElement($expeditionRoute);
            // set the owning side to null (unless already changed)
            if ($expeditionRoute->getOwner() === $this) {
                $expeditionRoute->setOwner(null);
            }
        }

        return $this;
    }

    public function getBanished(): ?bool
    {
        return $this->banished;
    }

    public function setBanished(bool $banished): self
    {
        $this->banished = $banished;

        return $this;
    }

    /**
     * @return Collection|Complaint[]
     */
    public function getComplaints(): Collection
    {
        return $this->complaints;
    }

    public function addComplaint(Complaint $complaint): self
    {
        if (!$this->complaints->contains($complaint)) {
            $this->complaints[] = $complaint;
            $complaint->setCulprit($this);
        }

        return $this;
    }

    public function removeComplaint(Complaint $complaint): self
    {
        if ($this->complaints->contains($complaint)) {
            $this->complaints->removeElement($complaint);
            // set the owning side to null (unless already changed)
            if ($complaint->getCulprit() === $this) {
                $complaint->setCulprit(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|HeroicActionPrototype[]
     */
    public function getHeroicActions(): Collection
    {
        return $this->heroicActions;
    }

    public function addHeroicAction(HeroicActionPrototype $heroicAction): self
    {
        if (!$this->heroicActions->contains($heroicAction)) {
            $this->heroicActions[] = $heroicAction;
        }

        return $this;
    }

    public function removeHeroicAction(HeroicActionPrototype $heroicAction): self
    {
        if ($this->heroicActions->contains($heroicAction)) {
            $this->heroicActions->removeElement($heroicAction);
        }

        return $this;
    }

    public function getCampingCounter(): int
    {
      return $this->campingCounter;
    }

    public function setCampingCounter(int $campingCounter): self
    {
      $this->campingCounter = $campingCounter;

      return $this;
    }

    public function getCampingTimestamp(): int
    {
        return $this->campingTimestamp;
    }

    public function setCampingTimestamp(int $campingTimestamp): self
    {
        $this->campingTimestamp = $campingTimestamp;

        return $this;
    }

    public function getCampingChance(): float
    {
        return $this->campingChance;
    }

    public function setCampingChance(float $campingChance): self
    {
        $this->campingChance = $campingChance;

        return $this;
    }

    /**
     * @return Collection|ActionCounter[]
     */
    public function getActionCounters(): Collection
    {
        return $this->actionCounters;
    }

    public function addActionCounter(ActionCounter $actionCounter): self
    {
        if (!$this->actionCounters->contains($actionCounter)) {
            $this->actionCounters[] = $actionCounter;
            $actionCounter->setCitizen($this);
        }

        return $this;
    }

    public function removeActionCounter(ActionCounter $actionCounter): self
    {
        if ($this->actionCounters->contains($actionCounter)) {
            $this->actionCounters->removeElement($actionCounter);
            // set the owning side to null (unless already changed)
            if ($actionCounter->getCitizen() === $this) {
                $actionCounter->setCitizen(null);
            }
        }

        return $this;
    }

    public function getSpecificActionCounterValue( int $type ): int {
        foreach ($this->getActionCounters() as $c)
            if ($c->getType() === $type) return $c->getCount();
        return 0;
    }

    public function getSpecificActionCounter( int $type ): ActionCounter {
        foreach ($this->getActionCounters() as $c)
            if ($c->getType() === $type) return $c;
        $a = (new ActionCounter())->setType($type);
        $this->addActionCounter($a);
        return $a;
    }

    public function isOnline(): bool {
        $ts = $this->getLastActionTimestamp();
        return $ts ? (time() - $ts) < 300 : false;
    }

    public function isDigging(): bool {
        $zone = $this->getZone();
        if (!$zone) return false;
        foreach ($this->getDigTimers() as $digTimer)
            if ($digTimer->getZone()->getId() === $zone->getId())
                return !$digTimer->getPassive();
        return false;
    }
    
    public function hasDigTimer(): bool {
        $zone = $this->getZone();
        if (!$zone) return false;
        foreach ($this->getDigTimers() as $digTimer)
            if ($digTimer->getZone()->getId() === $zone->getId())
                return true;
        return false;
    }

    public function hasPassiveDigTimer(): bool {
        $zone = $this->getZone();
        if (!$zone) return false;
        foreach ($this->getDigTimers() as $digTimer)
            if ($digTimer->getZone()->getId() === $zone->getId())
                return $digTimer->getPassive();
        return false;
    }

    public function getDigTimeout(): int {
        $zone = $this->getZone();
        if (!$zone) return -1;
        foreach ($this->getDigTimers() as $digTimer)
            if ($digTimer->getZone()->getId() === $zone->getId())
                return $digTimer->getTimestamp()->getTimestamp() - (new DateTime())->getTimestamp();
        return -1;
    }

    public function isCamping(): bool {
        foreach ($this->getStatus() as $status)
            if (in_array( $status->getName(), ['tg_tomb','tg_hide'] ))
                return true;
        return false;
    }

    public function getEscortSettings(): ?CitizenEscortSettings
    {
        return $this->escortSettings;
    }

    public function setEscortSettings(?CitizenEscortSettings $escortSettings): self
    {
        $this->escortSettings = $escortSettings;

        return $this;
    }

    public function getLastActionTimestamp(): int
    {
        return $this->lastActionTimestamp;
    }

    public function setLastActionTimestamp(int $lastActionTimestamp): self
    {
        $this->lastActionTimestamp = $lastActionTimestamp;

        return $this;
    }

    public function getPm(): ?int
    {
        return $this->pm;
    }

    public function setPm(int $pm): self
    {
        $this->pm = $pm;

        return $this;
    }

    /**
     * @return Collection|CitizenEscortSettings[]
     */
    public function getLeadingEscorts(): Collection
    {
        return $this->leadingEscorts;
    }

    public function addLeadingEscort(CitizenEscortSettings $leadingEscort): self
    {
        if (!$this->leadingEscorts->contains($leadingEscort)) {
            $this->leadingEscorts[] = $leadingEscort;
            $leadingEscort->setLeader($this);
        }

        return $this;
    }

    /**
     * @return CitizenEscortSettings[]
     */
    public function getValidLeadingEscorts(): array {
        return array_filter( $this->getLeadingEscorts()->getValues(), function(CitizenEscortSettings $s) {
            return $s->getCitizen()->getZone()->getId() === $s->getLeader()->getZone()->getId();
        } );
    }

    public function removeLeadingEscort(CitizenEscortSettings $leadingEscort): self
    {
        if ($this->leadingEscorts->contains($leadingEscort)) {
            $this->leadingEscorts->removeElement($leadingEscort);
            // set the owning side to null (unless already changed)
            if ($leadingEscort->getLeader() === $this) {
                $leadingEscort->setLeader(null);
            }
        }

        return $this;
    }

    public function getLastWords(): ?string
    {
        return $this->lastWords;
    }

    public function setLastWords(string $lastWords): self
    {
        $this->lastWords = $lastWords;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDisposed(): ?int
    {
        return $this->disposed;
    }

    public function setDisposed(int $disposed): self
    {
        $this->disposed = $disposed;

        return $this;
    }

    /**
     * @return Collection|Citizen[]
     */
    public function getDisposedBy(): Collection
    {
        return $this->disposedBy;
    }

    public function addDisposedBy(Citizen $citizen): self
    {
        if (!$this->disposedBy->contains($citizen)) {
            $this->disposedBy[] = $citizen;
        }

        return $this;
    }

    public function removeDisposedBy(Citizen $citizen): self
    {
        if ($this->disposedBy->contains($citizen)) {
            $this->disposedBy->removeElement($citizen);
        }

        return $this;
    }

    /**
     * @return Collection|CitizenWatch[]
     */
    public function getCitizenWatch()
    {
        return $this->citizenWatch;
    }

    public function addCitizenWatch(?CitizenWatch $citizenWatch): self
    {
        if(!$this->citizenWatch->contains($citizenWatch))
            $this->citizenWatch[] = $citizenWatch;

        return $this;
    }

    public function removeCitizenWatch(?CitizenWatch $citizenWatch): self
    {
        if($this->citizenWatch->contains($citizenWatch))
        $this->citizenWatch->removeElement($citizenWatch);

        return $this;
    }


    public function getBankAntiAbuse(): ?BankAntiAbuse
    {
        return $this->bankAntiAbuse;
    }

    public function setBankAntiAbuse(?BankAntiAbuse $bankAntiAbuse): self
    {
        $this->bankAntiAbuse = $bankAntiAbuse;

        return $this;
    }

    public function getGhulHunger(): ?int
    {
        return $this->ghulHunger;
    }

    public function setGhulHunger(int $ghulHunger): self
    {
        $this->ghulHunger = $ghulHunger;

        return $this;
    }

    /**
     * @return Collection|PrivateMessageThread[]
     */
    public function getPrivateMessageThreads(): Collection
    {
        return $this->privateMessageThreads;
    }

    public function addPrivateMessageThread(PrivateMessageThread $privateMessageThread): self
    {
        if (!$this->privateMessageThreads->contains($privateMessageThread)) {
            $this->privateMessageThreads[] = $privateMessageThread;
            $privateMessageThread->setRecipient($this);
        }

        return $this;
    }

    public function removePrivateMessageThread(PrivateMessageThread $privateMessageThread): self
    {
        if ($this->privateMessageThreads->contains($privateMessageThread)) {
            $this->privateMessageThreads->removeElement($privateMessageThread);
            // set the owning side to null (unless already changed)
            if ($privateMessageThread->getRecipient() === $this) {
                $privateMessageThread->setRecipient(null);
            }
        }

        return $this;
    }

    public function getRankingEntry(): ?CitizenRankingProxy
    {
        return $this->rankingEntry;
    }

    public function setRankingEntry(?CitizenRankingProxy $rankingEntry): self
    {
        $this->rankingEntry = $rankingEntry;

        return $this;
    }

    /**
     * @ORM\PostPersist()
     * @param LifecycleEventArgs $args
     * @throws ORMException
     */
    public function lifeCycle_createCitizenRankingProxy(LifecycleEventArgs $args) {
        $args->getEntityManager()->persist( CitizenRankingProxy::fromCitizen($this) );
        $args->getEntityManager()->flush();
    }

    /**
     * If the citizen is currently exploring a ruin or has explored a ruin at this location today, the relevant
     * RuinExplorerStats object will be returned. Otherwise, null is returned.
     * @return RuinExplorerStats|null
     */
    public function currentExplorerStats(): ?RuinExplorerStats {
        if ($this->getZone())
            foreach ($this->getExplorerStats() as $explorerStat)
                if ($explorerStat->getZone()->getId() === $this->getZone()->getId())
                    return $explorerStat;
        return null;
    }

    /**
     * If the citizen is currently exploring a ruin, the relevant RuinExplorerStats object will be returned. Otherwise,
     * null is returned.
     * @return RuinExplorerStats|null
     */
    public function activeExplorerStats(): ?RuinExplorerStats {
        return (($ex = $this->currentExplorerStats()) && $ex->getActive()) ? $ex : null;
    }

    /**
     * @return Collection|RuinExplorerStats[]
     */
    public function getExplorerStats(): Collection
    {
        return $this->explorerStats;
    }

    public function addExplorerStat(RuinExplorerStats $explorerStat): self
    {
        if (!$this->explorerStats->contains($explorerStat)) {
            $this->explorerStats[] = $explorerStat;
            $explorerStat->setCitizen($this);
        }

        return $this;
    }

    public function removeExplorerStat(RuinExplorerStats $explorerStat): self
    {
        if ($this->explorerStats->contains($explorerStat)) {
            $this->explorerStats->removeElement($explorerStat);
            $explorerStat->getZone()->getExplorerStats()->removeElement($explorerStat);

            // set the owning side to null (unless already changed)
            if ($explorerStat->getCitizen() === $this) {
                $explorerStat->setCitizen(null);
            }
        }

        return $this;
    }

    public function getBuildingVote(): ?BuildingVote
    {
        return $this->buildingVote;
    }

    public function setBuildingVote(?BuildingVote $buildingVote): self
    {
        $this->buildingVote = $buildingVote;

        // set the owning side of the relation if necessary
        if ($buildingVote !== null && $buildingVote->getCitizen() !== $this) {
            $buildingVote->setCitizen($this);
        }

        return $this;
    }

    /**
     * @return Collection|HelpNotificationMarker[]
     */
    public function getHelpNotifications(): Collection
    {
        return $this->helpNotifications;
    }

    public function hasSeenHelpNotification(string $name) {
        foreach ($this->getHelpNotifications() as $notification)
            if ($notification->getName() === $name) return true;
        return false;
    }

    public function addHelpNotification(HelpNotificationMarker $helpNotification): self
    {
        if (!$this->helpNotifications->contains($helpNotification)) {
            $this->helpNotifications[] = $helpNotification;
        }

        return $this;
    }

    public function removeHelpNotification(HelpNotificationMarker $helpNotification): self
    {
        if ($this->helpNotifications->contains($helpNotification)) {
            $this->helpNotifications->removeElement($helpNotification);
        }

        return $this;
    }

    public function getHasSeenGazette(): ?bool
    {
        return $this->hasSeenGazette;
    }

    public function setHasSeenGazette(bool $hasSeenGazette): self
    {
        $this->hasSeenGazette = $hasSeenGazette;

        return $this;
    }
}
