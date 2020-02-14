<?php


namespace App\Service;


use App\Entity\Building;
use App\Entity\BuildingPrototype;
use App\Entity\CitizenHome;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\Town;
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

    public function calculate_home_def( CitizenHome &$home, ?int &$house_def = null, ?int &$upgrade_def = null, ?int &$item_def = null ): int {
        $house_def = $home->getPrototype()->getDefense();

        /** @var CitizenHomeUpgrade|null $n */
        $n = $this->entity_manager->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype( $home,
            $this->entity_manager->getRepository( CitizenHomeUpgradePrototype::class )->findOneByName( 'defense' )
        );
        $upgrade_def = $n ? $n->getLevel() : 0;
        $item_def = $this->inventory_handler->countSpecificItems( $home->getChest(),
            $this->inventory_handler->resolveItemProperties( 'defence' )
        );

        return $house_def + $upgrade_def + $item_def;
    }

    public function calculate_town_def( Town &$town, ?int &$house_def = null, ?int &$citizen_def = null, ?int &$building_def = null, ?int &$item_def = null ): int {
        $f_house_def = 0.0;
        $citizen_def = 0;
        foreach ($town->getCitizens() as $citizen)
            if ($citizen->getAlive()) {
                $home = $citizen->getHome();
                $f_house_def += $this->calculate_home_def( $home ) * 0.4;
                if (!$citizen->getZone() && $citizen->getProfession()->getName() === 'guardian')
                    $citizen_def += 5;
            }
        $house_def = floor($f_house_def);
        $building_def = 0;
        $item_def_factor = 1.0;
        foreach ($town->getBuildings() as $building)
            if ($building->getComplete()) {
                $building_def += ( $building->getDefenseBonus() + $building->getPrototype()->getDefense() );
                if ($building->getPrototype()->getName() === 'item_meca_parts_#00')
                    $item_def_factor += (1+$building->getLevel()) * 0.5;
            }

        $item_def = floor($this->inventory_handler->countSpecificItems( $town->getBank(),
            $this->inventory_handler->resolveItemProperties( 'defence' )
        ) * $item_def_factor);

        return $house_def + $citizen_def + $building_def + $item_def;
    }
}