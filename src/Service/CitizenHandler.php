<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\CitizenStatus;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use App\Structures\ItemRequest;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;

class CitizenHandler
{
    private $entity_manager;
    private $status_factory;
    private $item_factory;
    private $random_generator;
    private $inventory_handler;

    public function __construct(
        EntityManagerInterface $em, StatusFactory $sf, RandomGenerator $g, InventoryHandler $ih, ItemFactory $if)
    {
        $this->entity_manager = $em;
        $this->status_factory = $sf;
        $this->random_generator = $g;
        $this->inventory_handler = $ih;
        $this->item_factory = $if;
    }

    /**
     * @param Citizen $citizen
     * @param string|CitizenStatus|string[]|CitizenStatus[] $status
     * @param bool $all
     * @return bool
     */
    public function hasStatusEffect( Citizen $citizen, $status, bool $all = false ): bool {
        $status = array_map(function($s): string {
            /** @var $s string|CitizenStatus */
            if (is_a($s, CitizenStatus::class)) return $s->getName();
            elseif (is_string($s)) return $s;
            else return '???';
        }, is_array($status) ? $status : [$status]);

        if ($all) {
            foreach ($citizen->getStatus() as $s)
                if (!in_array($s->getName(), $status)) return false;
        } else {
            foreach ($citizen->getStatus() as $s)
                if (in_array($s->getName(), $status)) return true;
        }
        return $all;
    }

    public function isWounded(Citizen $citizen) {
        return $this->hasStatusEffect( $citizen, ['wound1','wound2','wound3','wound4','wound5','wound6'], false );
    }

    public function inflictWound( Citizen &$citizen ) {
        if ($this->isWounded($citizen)) return;
        $citizen->addStatus( $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName(
            $this->random_generator->pick( ['wound1','wound2','wound3','wound4','wound5','wound6'] )
        ) );
    }

    public function isTired(Citizen $citizen) {
        foreach ($citizen->getStatus() as $status)
            if ($status->getName() === 'tired') return true;
        return false;
    }

    public function getMaxAP(Citizen $citizen) {
        return $this->isWounded($citizen) ? 5 : 6;
    }

    public function setAP(Citizen &$citizen, bool $relative, int $num, ?int $max_bonus = null) {
        if ($max_bonus !== null)
            $citizen->setAp( max(0, min(max($this->getMaxAP( $citizen ) + $max_bonus, $citizen->getAp()), $relative ? ($citizen->getAp() + $num) : max(0,$num) )) );
        else $citizen->setAp( max(0, $relative ? ($citizen->getAp() + $num) : max(0,$num) ) );
        if ($citizen->getAp() == 0) $citizen->addStatus( $this->status_factory->createStatus( 'tired' ) );
        else $citizen->removeStatus( $this->status_factory->createStatus( 'tired' ) );
    }

    public function getCP(Citizen &$citizen): int {
        if ($this->hasStatusEffect( $citizen, 'terror', false )) $base = 0;
        else $base = $citizen->getProfession()->getName() == 'guardian' ? 4 : 2;

        if (!empty($this->inventory_handler->fetchSpecificItems(
            $citizen->getInventory(), [new ItemRequest( 'car_door_#00' )]
        ))) $base += 1;

        return $base;
    }

    public function applyProfession(Citizen &$citizen, CitizenProfession &$profession) {
        $item_type_cache = [];

        if ($citizen->getProfession() === $profession) return;

        if ($citizen->getProfession()) {
            foreach ($citizen->getProfession()->getProfessionItems() as $pi)
                if (!isset($item_type_cache[$pi->getId()])) $item_type_cache[$pi->getId()] = [-1,$pi];
            foreach ($citizen->getProfession()->getAltProfessionItems() as $pi)
                if (!isset($item_type_cache[$pi->getId()])) $item_type_cache[$pi->getId()] = [-1,$pi];
        }

        foreach ($profession->getProfessionItems() as $pi)
            if (!isset($item_type_cache[$pi->getId()])) $item_type_cache[$pi->getId()] = [1,$pi];
            else $item_type_cache[$pi->getId()] = [0,$pi];

        $inventory = $citizen->getInventory(); $null = null;
        foreach ($item_type_cache as &$entry) {
            list(&$action,&$proto) = $entry;

            if ($action < 0) foreach ($this->inventory_handler->fetchSpecificItems( $inventory, [new ItemRequest($proto->getName(),1,null,null)] ) as $item)
                $this->inventory_handler->transferItem($citizen,$item,$inventory,$null);
            if ($action > 0) {
                $item = $this->item_factory->createItem( $proto );
                $item->setEssential(true);
                $this->inventory_handler->transferItem($citizen,$item,$null,$inventory);
            }
        }

        $citizen->setProfession( $profession );
    }
}