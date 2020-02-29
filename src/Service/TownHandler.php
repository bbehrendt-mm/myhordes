<?php


namespace App\Service;


use App\Entity\Building;
use App\Entity\BuildingPrototype;
use App\Entity\CitizenHome;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\Town;
use App\Entity\ZombieEstimation;
use App\Structures\HomeDefenseSummary;
use App\Structures\TownDefenseSummary;
use Doctrine\ORM\EntityManagerInterface;

class TownHandler
{
    private $entity_manager;
    private $inventory_handler;

    public function __construct(
        EntityManagerInterface $em, InventoryHandler $ih)
    {
        $this->entity_manager = $em;
        $this->inventory_handler = $ih;
    }

    private function internalAddBuilding( Town &$town, BuildingPrototype $prototype ) {

        // Add building
        $town->addBuilding( (new Building())->setPrototype( $prototype ) );

        // Add all children that do not require blueprints
        foreach ( $prototype->getChildren() as $child )
            if ($child->getBlueprint() == 0) $this->internalAddBuilding( $town, $child );
    }

    public function addBuilding( Town &$town, BuildingPrototype $prototype ): bool {

        // Do not add a building that already exist
        $parent_available = empty($prototype->getParent());
        foreach ($town->getBuildings() as $b) {
            if ($b->getPrototype()->getId() === $prototype->getId())
                return false;
            $parent_available = $parent_available || ($b->getPrototype()->getId() === $prototype->getParent()->getId());
        }

        // Do not add building if parent does not exist; skip for buildings without parent
        if (!$parent_available) return false;

        $this->internalAddBuilding( $town, $prototype );
        return true;
    }

    public function getBuilding(Town &$town, $prototype, $finished = true): ?Building {
        if (is_string($prototype))
            $prototype = $this->entity_manager->getRepository(BuildingPrototype::class)->findOneByName($prototype);

        if (!$prototype) return null;
        foreach ($town->getBuildings() as $b)
            if ($b->getPrototype()->getId() === $prototype->getId())
                return (!$finished || $b->getComplete()) ? $b : null;
        return null;
    }

    public function calculate_home_def( CitizenHome &$home, ?HomeDefenseSummary &$summary = null): int {
        $summary = new HomeDefenseSummary();
        $summary->house_defense = $home->getPrototype()->getDefense();

        /** @var CitizenHomeUpgrade|null $n */
        $n = $this->entity_manager->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype( $home,
            $this->entity_manager->getRepository( CitizenHomeUpgradePrototype::class )->findOneByName( 'defense' )
        );
        $summary->upgrades_defense = ($n ? $n->getLevel() : 0) + $home->getAdditionalDefense();
        $summary->item_defense = $this->inventory_handler->countSpecificItems( $home->getChest(),
            $this->inventory_handler->resolveItemProperties( 'defence' )
        );

        return $summary->sum();
    }

    public function calculate_town_def( Town &$town, ?TownDefenseSummary &$summary = null ): int {
        $summary = new TownDefenseSummary();

        $f_house_def = 0.0;
        $summary->guardian_defense = 0;

        $home_def_factor = $this->getBuilding( $town, 'small_strategy_#00', true ) ? 0.8 : 0.4;
        $pentagon = $this->getBuilding( $town, 'item_shield_#00', true );

        if ($pentagon) {
            if     ($pentagon->getLevel() === 2) $summary->overall_scale += 0.14;
            elseif ($pentagon->getLevel() === 1) $summary->overall_scale += 0.12;
            else                                 $summary->overall_scale += 0.10;
        }

        $guardian_bonus = $this->getBuilding($town, 'small_watchmen_#00', true) ? 15 : 5;

        foreach ($town->getCitizens() as $citizen)
            if ($citizen->getAlive()) {
                $home = $citizen->getHome();
                $f_house_def += $this->calculate_home_def( $home ) * $home_def_factor;
                if (!$citizen->getZone() && $citizen->getProfession()->getName() === 'guardian')
                    $summary->guardian_defense += $guardian_bonus;
            }
        $summary->house_defense = floor($f_house_def);
        $summary->building_defense = 0;
        $item_def_factor = 1.0;
        foreach ($town->getBuildings() as $building)
            if ($building->getComplete()) {

                if ($building->getPrototype()->getName() === 'item_tube_#00' && $building->getLevel() > 0) {
                    $n = [0,2,4,6,9,12];

                    if ($town->getWell() >= $n[ $building->getLevel() ])
                        $summary->building_defense += $building->getDefenseBonus();
                    $summary->building_defense += $building->getPrototype()->getDefense();

                } else $summary->building_defense += ( $building->getDefenseBonus() + $building->getPrototype()->getDefense() );

                if ($building->getPrototype()->getName() === 'item_meca_parts_#00')
                    $item_def_factor += (1+$building->getLevel()) * 0.5;
            }

        $summary->item_defense = floor($this->inventory_handler->countSpecificItems( $town->getBank(),
            $this->inventory_handler->resolveItemProperties( 'defence' )
        ) * $item_def_factor);

        return $summary->sum();
    }

    public function get_zombie_estimation_quality(Town &$town, int $future = 0, ?int &$min = null, ?int &$max = null): float {
        $est = $this->entity_manager->getRepository(ZombieEstimation::class)->findOneByTown($town,$town->getDay()+$future);
        if (!$est) return 0;

        $min = round( $est->getZombies() - $est->getZombies() * $est->getOffsetMin()/100);
        $max = round( $est->getZombies() + $est->getZombies() * $est->getOffsetMax()/100);
        return 1 - (($est->getOffsetMin() + $est->getOffsetMax()) - 10) / 24;
    }

    public function calculate_zombie_attacks(Town &$town, int $future = 2) {
        if ($future < 0) return;
        $d = $town->getDay();
        for ($current_day = $d; $current_day <= ($d+$future); $current_day++)
            if (!$this->entity_manager->getRepository(ZombieEstimation::class)->findOneByTown($town,$current_day)) {
                $min = round( ($current_day <= 3 ? 0.66 : 1.0) * pow(max(1,$current_day-1) * 0.75 + 2.5,3) );
                $max = round( ($current_day <= 3 ? ($current_day <= 1 ? 0.4 : 0.66) : 1.0) * pow($current_day * 0.75 + 3.5,3) );

                $value = mt_rand($min,$max);
                if ($value > ($min + 0.5 * ($max-$min))) $value = mt_rand($min,$max);

                $off_min = mt_rand( 10, 24 );
                $off_max = 34 - $off_min;

                $town->addZombieEstimation(
                    (new ZombieEstimation())->setDay( $current_day )->setZombies( $value )->setOffsetMin( $off_min )
                    ->setOffsetMax( $off_max )
                );
            }

    }
}