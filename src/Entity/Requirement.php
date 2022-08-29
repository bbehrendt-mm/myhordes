<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Table;
use Doctrine\ORM\Mapping\UniqueConstraint;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: 'App\Repository\RequirementRepository')]
#[UniqueEntity('name')]
#[Table]
#[UniqueConstraint(name: 'requirement_name_unique', columns: ['name'])]
class Requirement
{
    const HideOnFail  = 0;
    const CrossOnFail = 1;
    const MessageOnFail = 2;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 32)]
    private $name;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireStatus')]
    private $statusRequirement;
    #[ORM\Column(type: 'smallint')]
    private $failureMode;
    #[ORM\Column(type: 'text', nullable: true)]
    private $failureText;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireItem')]
    private $item;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireZombiePresence')]
    private $zombies;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireLocation')]
    private $location;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireAP')]
    private $ap;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireBuilding')]
    private $building;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireHome')]
    private $home;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireZone')]
    private $zone;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireCounter')]
    private $counter;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequirePM')]
    private $pm;
    #[ORM\ManyToOne(targetEntity: 'App\Entity\RequireCP')]
    private $cp;
    #[ORM\ManyToOne(targetEntity: RequireConf::class)]
    private $conf;
    #[ORM\ManyToOne(targetEntity: RequireDay::class)]
    private $day;
    #[ORM\Column(type: 'integer', nullable: true)]
    private $custom;
    #[ORM\ManyToOne(targetEntity: RequireEvent::class)]
    private $Event;
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
    public function clear(): self {
        $this->statusRequirement = $this->item = $this->zombies = $this->location = $this->ap = $this->building =
        $this->home = $this->zone = $this->counter = $this->pm = $this->cp = $this->conf = null;
        return $this;
    }
    public function getStatusRequirement(): ?RequireStatus
    {
        return $this->statusRequirement;
    }
    public function setStatusRequirement(?RequireStatus $statusRequirement): self
    {
        $this->statusRequirement = $statusRequirement;

        return $this;
    }
    public function getFailureMode(): ?int
    {
        return $this->failureMode;
    }
    public function setFailureMode(int $failureMode): self
    {
        $this->failureMode = $failureMode;

        return $this;
    }
    public function getFailureText(): ?string
    {
        return $this->failureText;
    }
    public function setFailureText(?string $failureText): self
    {
        $this->failureText = $failureText;

        return $this;
    }
    public function getItem(): ?RequireItem
    {
        return $this->item;
    }
    public function setItem(?RequireItem $item): self
    {
        $this->item = $item;

        return $this;
    }
    public function getZombies(): ?RequireZombiePresence
    {
        return $this->zombies;
    }
    public function setZombies(?RequireZombiePresence $zombies): self
    {
        $this->zombies = $zombies;

        return $this;
    }
    public function getLocation(): ?RequireLocation
    {
        return $this->location;
    }
    public function setLocation(?RequireLocation $location): self
    {
        $this->location = $location;

        return $this;
    }
    public function getAp(): ?RequireAP
    {
        return $this->ap;
    }
    public function setAp(?RequireAP $ap): self
    {
        $this->ap = $ap;

        return $this;
    }
    public function getBuilding(): ?RequireBuilding
    {
        return $this->building;
    }
    public function setBuilding(?RequireBuilding $building): self
    {
        $this->building = $building;

        return $this;
    }
    public function getHome(): ?RequireHome
    {
        return $this->home;
    }
    public function setHome(?RequireHome $home): self
    {
        $this->home = $home;

        return $this;
    }
    public function getZone(): ?RequireZone
    {
        return $this->zone;
    }
    public function setZone(?RequireZone $zone): self
    {
        $this->zone = $zone;

        return $this;
    }
    public function getCounter(): ?RequireCounter
    {
        return $this->counter;
    }
    public function setCounter(?RequireCounter $counter): self
    {
        $this->counter = $counter;

        return $this;
    }
    public function getPm(): ?RequirePM
    {
        return $this->pm;
    }
    public function setPm(?RequirePM $pm): self
    {
        $this->pm = $pm;

        return $this;
    }
    public function getCp(): ?RequireCP
    {
        return $this->cp;
    }
    public function setCp(?RequireCP $cp): self
    {
        $this->cp = $cp;

        return $this;
    }
    public function getConf(): ?RequireConf
    {
        return $this->conf;
    }
    public function setConf(?RequireConf $conf): self
    {
        $this->conf = $conf;

        return $this;
    }
    public function getDay(): ?RequireDay
    {
        return $this->day;
    }
    public function setDay(?RequireDay $day): self
    {
        $this->day = $day;

        return $this;
    }
    public function getCustom(): ?int
    {
        return $this->custom;
    }
    public function setCustom(?int $custom): self
    {
        $this->custom = $custom;

        return $this;
    }
    public function getEvent(): ?RequireEvent
    {
        return $this->Event;
    }
    public function setEvent(?RequireEvent $Event): self
    {
        $this->Event = $Event;

        return $this;
    }
}
