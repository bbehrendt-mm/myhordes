<?php

namespace App\Service;

use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\CitizenRankingProxy;
use App\Entity\GameProfilerEntry;
use App\Entity\ItemPrototype;
use App\Entity\Recipe;
use App\Entity\Town;
use App\Entity\User;
use App\Entity\ZonePrototype;
use App\Enum\GameProfileEntryType;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

class GameProfilerService {

    private EntityManagerInterface $em;
    private ConfMaster $confMaster;
    private string $env;

    public function __construct(string $env, EntityManagerInterface $em, ConfMaster $conf)
    {
        $this->env = $env;
        $this->em = $em;
        $this->confMaster = $conf;
    }

    private function resolve_citizen( Citizen $citizen ): ?CitizenRankingProxy {
        if ($citizen->getRankingEntry()) return $citizen->getRankingEntry();
        return $this->em->getRepository( CitizenRankingProxy::class )->findOneBy([ 'citizen' => $citizen ]);
    }

    private function version_gate( Town $town, GameProfileEntryType $type ): bool {
        if ($this->env === 'dev') return true;
        return $town->getProfilerVersion() >= $type->version();
    }

    private function init( GameProfileEntryType $type, Town $town, ?Citizen $citizen = null ): ?GameProfilerEntry {
        if (!$this->version_gate( $town, $type )) return null;
        return (new GameProfilerEntry())
            ->setTown( $town->getRankingEntry() )
            ->setTempTown( $town->getRankingEntry() === null ? $town : null )
            ->setCitizen( $citizen ? $this->resolve_citizen( $citizen ) : null )
            ->setType( $type->value )
            ->setTimestamp( new DateTime() )
            ->setVersion( GameProfileEntryType::latest_version() )
            ->setDay( $town->getDay() );
    }

    private function maybe_persist( ?GameProfilerEntry $entry ): void {
        if ($entry) $this->em->persist( $entry );
    }

    public function recordTownCreated(Town $town, ?User $creator = null, string $method = 'default'): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::TownCreated, $town )
                ?->setForeign1($creator?->getId())
                ?->setData( [
                                'conf' =>$this->confMaster->getTownConfiguration( $town )->raw(),
                                'by' => $method
                            ]
                )
        );
    }

    public function recordTownEnded(Town $town): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::TownEnded, $town )
        );
    }

    public function recordCitizenJoined( Citizen $citizen, string $method = 'default' ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::CitizenJoined, $citizen->getTown(), $citizen )
                ?->setData( [ 'by' => $method ] )
        );
    }

    public function recordCitizenProfessionSelected( Citizen $citizen ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::CitizenJoined, $citizen->getTown(), $citizen )
                ?->setForeign1( $citizen->getProfession()->getId() )
        );
    }

    public function recordCitizenDied( Citizen $citizen ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::CitizenDied, $citizen->getTown(), $citizen )
                ?->setForeign1( $citizen->getCauseOfDeath()->getId() )
        );
    }

    public function recordCitizenCitizenProfessionChanged( Citizen $citizen, CitizenProfession $former_profession ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::CitizenProfessionChanged, $citizen->getTown(), $citizen )
                ?->setForeign1( $citizen->getProfession()->getId() )
                ?->setForeign2( $former_profession->getId() )
        );
    }

    public function recordBuildingDiscovered( BuildingPrototype $building, Town $town, ?Citizen $citizen = null, string $method = 'default' ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::BuildingDiscovered, $town, $citizen )
                ?->setForeign1( $building->getId() )
                ?->setData( [ 'by' => $method ])
        );
    }

    public function recordBuildingConstructionInvested( BuildingPrototype $building, Town $town, ?Citizen $citizen = null, int $ap = 1, int $bp = 0 ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::BuildingConstructionInvested, $town, $citizen )
                ?->setForeign1( $building->getId() )
                ?->setData( [ 'ap' => $ap, 'bp' => $bp ])
        );
    }

    public function recordBuildingConstructed( BuildingPrototype $building, Town $town, ?Citizen $citizen = null, string $method = 'default' ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::BuildingDiscovered, $town, $citizen )
                ?->setForeign1( $building->getId() )
                ?->setData( [ 'by' => $method ])
        );
    }

    public function recordBuildingRepairInvested( BuildingPrototype $building, Town $town, ?Citizen $citizen = null, int $ap = 1, int $bp = 0 ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::BuildingRepairInvested, $town, $citizen )
                ?->setForeign1( $building->getId() )
                ?->setData( [ 'ap' => $ap, 'bp' => $bp ])
        );
    }

    public function recordBuildingDestroyed( BuildingPrototype $building, Town $town, string $method = 'default' ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::BuildingCollapsed, $town )
                ?->setForeign1( $building->getId() )
                ?->setData( [ 'by' => $method ])
        );
    }

    public function recordBuildingDamaged( BuildingPrototype $building, Town $town, int $damage = 1): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::BuildingDamaged, $town )
                ?->setForeign1( $building->getId() )
                ?->setData( [ 'damage' => $damage ])
        );
    }

    public function recordBuildingCollapsed( BuildingPrototype $building, Town $town ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::BuildingCollapsed, $town )
                ?->setForeign1( $building->getId() )
        );
    }

    public function recordRecipeExecuted( Recipe $recipe, Citizen $citizen, ?ItemPrototype $result ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::RecipeExecuted, $citizen->getTown(), $citizen )
                ?->setForeign1( $recipe->getId() )
                ?->setForeign2( $result?->getId() )
        );
    }

    public function recordItemFound( ItemPrototype $item, Citizen $citizen, ?ZonePrototype $ruin = null, $method = 'scavenge' ): void {
        $this->maybe_persist(
            $this->init( GameProfileEntryType::ItemFound, $citizen->getTown(), $citizen )
                ?->setForeign1( $item->getId() )
                ?->setForeign2( $ruin?->getId() )
                ?->setData( [ 'by' => $method ])
        );
    }
}