<?php


namespace App\Service;


use App\Entity\Building;
use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenHomePrototype;
use App\Entity\CitizenProfession;
use App\Entity\DigTimer;
use App\Entity\Inventory;
use App\Entity\ItemGroup;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use App\Entity\Zone;
use App\Entity\ZonePrototype;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class TownHandler
{
    private $entity_manager;

    public function __construct(
        EntityManagerInterface $em)
    {
        $this->entity_manager = $em;
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
}