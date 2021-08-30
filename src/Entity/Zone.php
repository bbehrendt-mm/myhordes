<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ZoneRepository")
 * @UniqueEntity("gps")
 * @Table(uniqueConstraints={
 *     @UniqueConstraint(name="gps_unique_zone",columns={"x","y","town_id"})
 * })
 */
class Zone
{
    const DiscoveryStateNone    = 0;
    const DiscoveryStatePast    = 1;
    const DiscoveryStateCurrent = 2;

    const ZombieStateUnknown  = 0;
    const ZombieStateEstimate = 1;
    const ZombieStateExact    = 2;

    const DirectionNorthWest = 1;
    const DirectionNorth     = 2;
    const DirectionNorthEast = 3;
    const DirectionWest      = 4;
    const DirectionCenter    = 5;
    const DirectionEast      = 6;
    const DirectionSouthWest = 7;
    const DirectionSouth     = 8;
    const DirectionSouthEast = 9;

    const BluePrintNone      = 0;
    const BlueprintAvailable = 1;
    const BlueprintFound     = 2;

    /**
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="integer")
     */
    private $x;

    /**
     * @ORM\Column(type="integer")
     */
    private $y;

    /**
     * @ORM\Column(type="integer")
     */
    private $zombies;

    /**
     * @ORM\OneToOne(targetEntity="App\Entity\Inventory", inversedBy="zone", cascade={"persist", "remove"})
     * @ORM\JoinColumn(nullable=false)
     */
    private $floor;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\Town", inversedBy="zones")
     * @ORM\JoinColumn(nullable=false)
     */
    private $town;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\Citizen", mappedBy="zone", fetch="LAZY")
     */
    private $citizens;

    /**
     * @ORM\Column(type="integer")
     */
    private $initialZombies;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ZonePrototype")
     */
    private $prototype;

    /**
     * @ORM\Column(type="integer")
     */
    private $digs = 10;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DigTimer", mappedBy="zone", orphanRemoval=true, cascade={"persist","remove"})
     */
    private $digTimers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\EscapeTimer", mappedBy="zone", orphanRemoval=true, cascade={"persist","remove"})
     */
    private $escapeTimers;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\DigRuinMarker", mappedBy="zone", orphanRemoval=true)
     */
    private $digRuinMarkers;

    /**
     * @ORM\Column(type="integer")
     */
    private $ruinDigs = 10;

    /**
     * @ORM\Column(type="integer")
     */
    private $discoveryStatus = self::DiscoveryStateNone;

    /**
     * @ORM\Column(type="integer")
     */
    private $zombieStatus = self::ZombieStateUnknown;

    /**
     * @ORM\Column(type="integer")
     */
    private $buryCount = 0;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\ScoutVisit", mappedBy="zone", cascade={"persist","remove"}, orphanRemoval=true)
     */
    private $scoutVisits;

    /**
     * @ORM\Column(type="integer")
     */
    private $scoutEstimationOffset;

    /**
     * @ORM\Column(type="float")
     */
    private $improvementLevel = 0;

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private $blueprint = self::BluePrintNone;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\ZoneTag")
     */
    private $tag;

    /**
     * @ORM\OneToMany(targetEntity="App\Entity\RuinZone", mappedBy="zone", orphanRemoval=true, cascade={"persist", "remove"})
     */
    private $ruinZones;

    /**
     * @ORM\OneToMany(targetEntity=RuinExplorerStats::class, mappedBy="zone", orphanRemoval=true, cascade={"persist", "remove"})
     */
    private $explorerStats;

    /**
     * @ORM\OneToMany(targetEntity=ChatSilenceTimer::class, mappedBy="zone", orphanRemoval=true, cascade={"persist","remove"})

     */
    private $chatSilenceTimers;

    /**
     * @ORM\Column(type="integer")
     */
    private $startZombies = 0;

    public function __construct()
    {
        $this->citizens = new ArrayCollection();
        $this->digTimers = new ArrayCollection();
        $this->escapeTimers = new ArrayCollection();
        $this->digRuinMarkers = new ArrayCollection();
        $this->scoutVisits = new ArrayCollection();
        $this->ruinZones = new ArrayCollection();
        $this->explorerStats = new ArrayCollection();
        $this->chatSilenceTimers = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getX(): ?int
    {
        return $this->x;
    }

    public function setX(int $x): self
    {
        $this->x = $x;

        return $this;
    }

    public function getY(): ?int
    {
        return $this->y;
    }

    public function setY(int $y): self
    {
        $this->y = $y;

        return $this;
    }

    public function isTownZone(): bool {
        return $this->x === 0 && $this->y === 0;
    }

    public function getDistance(): int {
        return round(sqrt( pow($this->getX(),2) + pow($this->getY(),2) ));
    }

    public function getApDistance(): int {
        return $this->getX() + $this->getY();
    }

    public function getZombies(): ?int
    {
        return $this->zombies;
    }

    public function setZombies(int $zombies): self
    {
        $this->zombies = $zombies;

        return $this;
    }

    public function getFloor(): ?Inventory
    {
        return $this->floor;
    }

    public function setFloor(Inventory $floor): self
    {
        $this->floor = $floor;

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

    /**
     * @return Collection|Citizen[]
     */
    public function getCitizens(): Collection
    {
        return $this->citizens;
    }

    public function addCitizen(Citizen $citizen): self
    {
        if (!$this->citizens->contains($citizen)) {
            $this->citizens[] = $citizen;
            $citizen->setZone($this);
        }

        return $this;
    }

    public function removeCitizen(Citizen $citizen): self
    {
        if ($this->citizens->contains($citizen)) {
            $this->citizens->removeElement($citizen);
            // set the owning side to null (unless already changed)
            if ($citizen->getZone() === $this) {
                $citizen->setZone(null);
            }
        }

        return $this;
    }

    public function getCampers(): array {
        // No citizens = no campers.
        if ($this->citizens->isEmpty()) {
            return [];
        }
        $campers = [];
        foreach ($this->citizens as $citizen) {
            if ($citizen->getCampingTimestamp() > 0) {
                $campers[$citizen->getCampingTimestamp()] = $citizen;
            }
        }
        ksort($campers);
        return $campers;
    }

    public function getInitialZombies(): ?int
    {
        return $this->initialZombies;
    }

    public function setInitialZombies(int $initialZombies): self
    {
        $this->initialZombies = $initialZombies;

        return $this;
    }

    public function getPrototype(): ?ZonePrototype
    {
        return $this->prototype;
    }

    public function setPrototype(?ZonePrototype $prototype): self
    {
        $this->prototype = $prototype;

        return $this;
    }

    public function getDigs(): ?int
    {
        return $this->digs;
    }

    public function setDigs(int $digs): self
    {
        $this->digs = $digs;

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
            $digTimer->setZone($this);
        }

        return $this;
    }

    public function removeDigTimer(DigTimer $digTimer): self
    {
        if ($this->digTimers->contains($digTimer)) {
            $this->digTimers->removeElement($digTimer);
            // set the owning side to null (unless already changed)
            if ($digTimer->getZone() === $this) {
                $digTimer->setZone(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|EscapeTimer[]
     */
    public function getEscapeTimers(): Collection
    {
        return $this->escapeTimers;
    }

    public function addEscapeTimer(EscapeTimer $escapeTimer): self
    {
        if (!$this->escapeTimers->contains($escapeTimer)) {
            $this->escapeTimers[] = $escapeTimer;
            $escapeTimer->setZone($this);
        }

        return $this;
    }

    public function removeEscapeTimer(EscapeTimer $escapeTimer): self
    {
        if ($this->escapeTimers->contains($escapeTimer)) {
            $this->escapeTimers->removeElement($escapeTimer);
            // set the owning side to null (unless already changed)
            if ($escapeTimer->getZone() === $this) {
                $escapeTimer->setZone(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|DigRuinMarker[]
     */
    public function getDigRuinMarkers(): Collection
    {
        return $this->digRuinMarkers;
    }

    public function addDigRuinMarker(DigRuinMarker $digRuinMarker): self
    {
        if (!$this->digRuinMarkers->contains($digRuinMarker)) {
            $this->digRuinMarkers[] = $digRuinMarker;
            $digRuinMarker->setZone($this);
        }

        return $this;
    }

    public function removeDigRuinMarker(DigRuinMarker $digRuinMarker): self
    {
        if ($this->digRuinMarkers->contains($digRuinMarker)) {
            $this->digRuinMarkers->removeElement($digRuinMarker);
            // set the owning side to null (unless already changed)
            if ($digRuinMarker->getZone() === $this) {
                $digRuinMarker->setZone(null);
            }
        }

        return $this;
    }

    public function getRuinDigs(): ?int
    {
        return $this->ruinDigs;
    }

    public function setRuinDigs(int $ruinDigs): self
    {
        $this->ruinDigs = $ruinDigs;

        return $this;
    }

    public function getDiscoveryStatus(): ?int
    {
        return $this->discoveryStatus;
    }

    public function setDiscoveryStatus(int $discoveryStatus): self
    {
        $this->discoveryStatus = $discoveryStatus;

        return $this;
    }

    public function getZombieStatus(): ?int
    {
        return $this->zombieStatus;
    }

    public function setZombieStatus(int $zombieStatus): self
    {
        $this->zombieStatus = $zombieStatus;

        return $this;
    }

    public function getDirection(): int {
        if ($this->getX() === 0 && $this->getY() === 0) return self::DirectionCenter;
        elseif ($this->getX() != 0 && $this->getY() != 0 && (abs(abs($this->getX())-abs($this->getY())) < min(abs($this->getX()),abs($this->getY())))) {
            if ($this->getX() < 0 && $this->getY() < 0) return self::DirectionSouthWest;
            if ($this->getX() < 0 && $this->getY() > 0) return self::DirectionNorthWest;
            if ($this->getX() > 0 && $this->getY() < 0) return self::DirectionSouthEast;
            if ($this->getX() > 0 && $this->getY() > 0) return self::DirectionNorthEast;
        } else {
            if (abs($this->getX()) > abs($this->getY()) && $this->getX() < 0) return self::DirectionWest;
            if (abs($this->getX()) > abs($this->getY()) && $this->getX() > 0) return self::DirectionEast;
            if (abs($this->getX()) < abs($this->getY()) && $this->getY() < 0) return self::DirectionSouth;
            if (abs($this->getX()) < abs($this->getY()) && $this->getY() > 0) return self::DirectionNorth;
        }
    }

    public function getBuryCount(): ?int
    {
        return $this->buryCount;
    }

    public function setBuryCount(int $buryCount): self
    {
        $this->buryCount = $buryCount;

        return $this;
    }

    /**
     * @return Collection|ScoutVisit[]
     */
    public function getScoutVisits(): Collection
    {
        return $this->scoutVisits;
    }

    public function addScoutVisit(ScoutVisit $scoutVisit): self
    {
        if (!$this->scoutVisits->contains($scoutVisit)) {
            $this->scoutVisits[] = $scoutVisit;
            $scoutVisit->setZone($this);
        }

        return $this;
    }

    public function removeScoutVisit(ScoutVisit $scoutVisit): self
    {
        if ($this->scoutVisits->contains($scoutVisit)) {
            $this->scoutVisits->removeElement($scoutVisit);
            // set the owning side to null (unless already changed)
            if ($scoutVisit->getZone() === $this) {
                $scoutVisit->setZone(null);
            }
        }

        return $this;
    }

    public function getScoutLevel(): int
    {
        return max(0,$this->getScoutVisits()->count() - 1);
    }

    public function getScoutEstimationOffset(): ?int
    {
        return $this->scoutEstimationOffset;
    }

    public function getPersonalScoutEstimation(Citizen $c): ?int
    {
        return ($this->getZombies() === 0) ? 0 : max(0, $this->getZombies() + (($c->getId() + $this->scoutEstimationOffset) % 5) - 2);
    }

    public function setScoutEstimationOffset(int $scoutEstimationOffset): self
    {
        $this->scoutEstimationOffset = $scoutEstimationOffset;

        return $this;
    }

    public function getImprovementLevel(): ?float
    {
      return $this->improvementLevel;
    }

    public function setImprovementLevel(float $improvementLevel): self
    {
      $this->improvementLevel = $improvementLevel;

      return $this;
    }

    public function getBlueprint(): ?int
    {
        return $this->blueprint;
    }

    public function setBlueprint(int $blueprint): self
    {
        $this->blueprint = $blueprint;

        return $this;
    }

    public function getTag(): ?ZoneTag
    {
        return $this->tag;
    }

    public function setTag(?ZoneTag $tag): self
    {
        $this->tag = $tag;

        return $this;
    }

    /**
     * @return Collection|RuinZone[]
     */
    public function getRuinZones(): Collection
    {
        return $this->ruinZones;
    }

    public function addRuinZone(RuinZone $ruinZone): self
    {
        if (!$this->ruinZones->contains($ruinZone)) {
            $this->ruinZones[] = $ruinZone;
            $ruinZone->setZone($this);
        }

        return $this;
    }

    public function removeRuinZone(RuinZone $ruinZone): self
    {
        if ($this->ruinZones->contains($ruinZone)) {
            $this->ruinZones->removeElement($ruinZone);
            // set the owning side to null (unless already changed)
            if ($ruinZone->getZone() === $this) {
                $ruinZone->setZone(null);
            }
        }

        return $this;
    }

    /**
     * If a citizen is currently exploring the ruin in this zone, the relevant RuinExplorerStats object will be
     * returned. Otherwise, null is returned.
     * @return RuinExplorerStats|null
     */
    public function activeExplorerStats(): ?RuinExplorerStats {
        if ($this->getPrototype() && $this->getPrototype()->getExplorable())
            foreach ($this->getExplorerStats() as $explorerStat)
                if ($explorerStat->getActive() && $this->citizens->contains( $explorerStat->getCitizen() ))
                    return $explorerStat;
        return null;
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
            $explorerStat->setZone($this);
        }

        return $this;
    }

    public function removeExplorerStat(RuinExplorerStats $explorerStat): self
    {
        if ($this->explorerStats->contains($explorerStat)) {
            $this->explorerStats->removeElement($explorerStat);
            // set the owning side to null (unless already changed)
            if ($explorerStat->getZone() === $this) {
                $explorerStat->setZone(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection|ChatSilenceTimer[]
     */
    public function getChatSilenceTimers(): Collection
    {
        return $this->chatSilenceTimers;
    }

    public function addChatSilenceTimer(ChatSilenceTimer $chatSilenceTimer): self
    {
        if (!$this->chatSilenceTimers->contains($chatSilenceTimer)) {
            $this->chatSilenceTimers[] = $chatSilenceTimer;
            $chatSilenceTimer->setZone($this);
        }

        return $this;
    }

    public function removeChatSilenceTimer(ChatSilenceTimer $chatSilenceTimer): self
    {
        if ($this->chatSilenceTimers->removeElement($chatSilenceTimer)) {
            // set the owning side to null (unless already changed)
            if ($chatSilenceTimer->getZone() === $this) {
                $chatSilenceTimer->setZone(null);
            }
        }

        return $this;
    }

    public function getStartZombies(): ?int
    {
        return $this->startZombies;
    }

    public function setStartZombies(int $startZombies): self
    {
        $this->startZombies = $startZombies;

        return $this;
    }
}
