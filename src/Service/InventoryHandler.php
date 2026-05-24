<?php


namespace App\Service;


use App\Controller\Town\TownController;
use App\Entity\Citizen;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\CitizenStatus;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemGroup;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\RuinZone;
use App\Entity\Town;
use App\Enum\Configuration\CitizenProperties;
use App\Enum\ItemPoisonType;
use App\Structures\ItemRequest;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\UnexpectedResultException;
use Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;

class InventoryHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entity_manager,
        private readonly ItemFactory $item_factory,
        private readonly DoctrineCacheService $doctrineCache,
        private readonly EventProxyService $events,
    ) {}

    /**
     * Returns the maximum number of slots in an inventory, considering the number of essential items and the size of
     * the inventory. Returns 0 if the inventory has no slot limit.
     * @param Inventory $inventory
     * @return int
     */
    public function getSize( Inventory $inventory ): int {
        if ($inventory->getCitizen()) {
            $base = 4 + $this->countEssentialItems($inventory) + $inventory->getCitizen()->property( CitizenProperties::InventorySpaceBonus );

            if (
                $this->countSpecificItems( $inventory, 'bagxl_#00') > 0 ||
                $this->countSpecificItems( $inventory, 'cart_#00') > 0
            )
                $base += 3;
            else if ($this->countSpecificItems( $inventory, 'bag_#00' ) > 0)
                $base += 2;

            if ($this->countSpecificItems( $inventory, 'pocket_belt_#00' ) > 0)
                $base += 2;

            return $base;
        }

        if ($inventory->getHome()) {
            $base = 4 + ($inventory->getHome()->getCitizen()->property( CitizenProperties::ChestSpaceBonus ) ?? 0);

            // Check upgrades
            $upgrade = $this->entity_manager->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype(
                $inventory->getHome(),
                $this->doctrineCache->getEntityByIdentifier( CitizenHomeUpgradePrototype::class, 'chest')
            );
            /** @var CitizenHomeUpgrade $upgrade */
            if ($upgrade) $base += $upgrade->getLevel();

            $base += $inventory->getHome()->getAdditionalStorage();

            return $base;
        }

        return 0;
    }

    /**
     * Returns the maximum number of heavy item slots in an inventory. Returns 0 if the inventory has no slot limit.
     * @param Inventory $inventory
     * @return int
     */
    public function getHeavyItemSize( Inventory $inventory ): int {
        if ($inventory->getCitizen()) {
            $base = 1;

            if ($this->countSpecificItems( $inventory, 'shield_off_#00' ) > 0)
                $base += 1;

            return $base;
        }

        return 0;
    }

    /**
     * Get the number of free slots in an inventory, optionally ignoring a set of items
     * @param Inventory $inv Inventory to check
     * @param Item[] $ignore List of items to ignore. If one of these items is in the given inventory, it's slot is
     * considered empty.
     * @return int List of free slots, or PHP_INT_MAX if the inventory has no slot limit
     */
    public function getFreeSize( Inventory $inv, array $ignore = [] ): int {
        if (empty($ignore))
            return ($max = $this->getSize($inv)) > 0 ? ($max - $inv->getItems()->count()) : PHP_INT_MAX;

        return ($max = $this->getSize($inv)) > 0 ? ($max - count(
            $inv->getItems()->filter(fn(Item $i) => !in_array($i, $ignore))
        )) : PHP_INT_MAX;
    }

    public function findStackPrototype( Inventory $inv, Item $item ): ?Item {
        // Items with the <individual> tag cannot stack
        if ($item->getPrototype()->getIndividual()) return null;
        return $inv->getItems()->matching( new Criteria(accessRawFieldValues: true)
            ->where( Criteria::expr()->eq('prototype', $item->getPrototype()) )
            ->andWhere( Criteria::expr()->neq('id', $item->getId()) )
            ->andWhere( Criteria::expr()->eq('poison', $item->getPoison()) )
            ->andWhere( Criteria::expr()->eq('broken', $item->getBroken()) )
            ->andWhere( Criteria::expr()->eq('essential', $item->getEssential()) )
        )->first() ?: null;
    }

    /**
     * @param string|string[]|ItemProperty|ItemProperty[] $props
     * @return ItemPrototype[]
     */
    public function resolveItemProperties( $props ): array {
        if (!is_array( $props )) $props = [$props];
        $props = array_map(function($e):ItemProperty {
            if (!is_string($e)) return $e;
            return $this->doctrineCache->getEntityByIdentifier(ItemProperty::class, $e);
        }, $props);

        $tmp = [];
        foreach ($props as $prop)
            foreach ($prop->getItemPrototypes() as $itemPrototype)
                $tmp[$itemPrototype->getId()] = $itemPrototype;
        return array_values($tmp);
    }

    /**
     * @param Inventory|Inventory[] $inventory Inventory(ies) to search into
     * @param string|ItemPrototype|ItemPrototype[] $prototype The item prototype or property we're looking for
     * @param bool $is_property Is the prototype string an item property or an item prototype
     * @param bool|null $broken Filter for broken (true) or unbroken (false) items; disable by setting to null (default)
     * @param bool|null $poison Filter for poisoned (true) or normal (false) items; disable by setting to null (default)
     * @return int Number of item matching the filters
     */
    public function countSpecificItems(Inventory|array $inventory, ItemPrototype|array|string $prototype, bool $is_property = false, ?bool $broken = null, ?bool $poison = null): int {
        if (is_string( $prototype )) $prototype = $is_property
            ? $this->doctrineCache->getEntityByIdentifier(ItemProperty::class, $prototype)->getItemPrototypes()->getValues()
            : $this->doctrineCache->getEntityByIdentifier(ItemPrototype::class, $prototype);

        if (!is_array($prototype)) $prototype = [$prototype];
        if (!is_array($inventory)) $inventory = [$inventory];

        $j = 0;
        foreach($inventory as $inv){
            $j = $j + array_reduce( array_filter( $inv->getItems()->getValues(),
                        fn(Item $k) =>
                            in_array( $k->getPrototype(), $prototype) &&
                            ( $broken === null || $k->getBroken() === $broken ) &&
                            ( $poison === null || $k->getPoison()->poisoned() === $poison )
                    )
                    , fn(int $c, Item $i) => $i->getCount() + $c, 0);
        }

        return $j;
    }

    /**
     * @param Inventory|Inventory[] $inventory
     * @param ItemGroup|ItemRequest[]|string[] $requests
     * @return Item[]
     */
    public function fetchSpecificItems(Inventory|array $inventory, ItemGroup|array $requests): array {
        $return = [];

        if (is_array($inventory)) $inventory = array_filter($inventory, function(Inventory $i) { return $i->getId() !== null; } );
        elseif ($inventory->getId() === null && $inventory->getItems()->count() == 0) return [];

        if (is_a( $requests, ItemGroup::class )) {
            $tmp = [];
            foreach ($requests->getEntries() as $entry)
                $tmp[] = new ItemRequest( $entry->getPrototype()->getName(), $entry->getChance(), false, false, false );
            $requests = $tmp;
        } else $requests = array_map( fn(string|ItemRequest $r) => is_string($r) ? new ItemRequest( $r ) : $r, $requests );

        foreach ($requests as $request) {
            $id_list = [];
            if ($request->isProperty()) {
                $prop = $this->doctrineCache->getEntityByIdentifier(ItemProperty::class, $request->getItemPropertyName());
                if ($prop) $id_list = array_map(function(ItemPrototype $p): int {
                    return $p->getId();
                }, $prop->getItemPrototypes()->getValues() );
            }

            $qb = $this->entity_manager->createQueryBuilder();
            $qb
                ->select('i.id')->from(Item::class,'i')
                ->leftJoin(ItemPrototype::class, 'p', Join::ON, 'i.prototype = p.id');
            if (!$request->getAll())
                $qb->setMaxResults( $request->getCount() );
            if (is_array($inventory))
                $qb->where('i.inventory IN (:inv)')->setParameter('inv', $inventory);
            else
                $qb->where('i.inventory = :inv')->setParameter('inv', $inventory);


            if ($request->isProperty())
                $qb
                    ->andWhere('p.id IN (:type)')->setParameter('type', $id_list);
            else $qb
                    ->andWhere('p.name = :type')->setParameter('type', $request->getItemPrototypeName());

            if (!empty($return)) $qb->andWhere('i.id NOT IN (:found)')->setParameter('found', $return);
            if ($request->filterBroken()) $qb->andWhere('i.broken = :isb')->setParameter('isb', $request->getBroken());
            if ($request->filterPoison()) $qb->andWhere($request->getPoison() ? 'i.poison != :isp' : 'i.poison = :isp')->setParameter('isp', ItemPoisonType::None->value);

            $result = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_SCALAR);

            $n = 0;
            $return = array_merge($return, array_map(function(array $a) use (&$n): Item {
                /** @var Item $inst */
                $inst = $this->entity_manager->getRepository(Item::class)->find( (int)$a['id'] );
                $n += $inst->getCount();
                return $inst;
            }, $result));

            if (!$request->getIncomplete() && !$request->getAll() && $n < $request->getCount()) return [];
        }

        return $return;
    }

    /**
     * @param Inventory $inventory
     * @param array $ignore
     * @return int
     */
    public function countHeavyItems(Inventory $inventory, array $ignore = []): int {
        return $inventory->getItems()->reduce(
            fn(int $c, Item $i) => $c + ( $i->getPrototype()->getHeavy() && !in_array( $i, $ignore ) ? $i->getCount() : 0 ),
            0
        );
    }

    /**
     * @param Inventory $inventory
     * @return bool
     */
    public function isOverEncumbered(Inventory $inventory): bool {
        $inv_size = $this->getSize( $inventory );
        $heavy_size = $this->getHeavyItemSize( $inventory );

        return
            ($inv_size > 0 && $inventory->getItems()->reduce( fn(int $c, Item $i) => $c + $i->getCount(), 0 ) > $inv_size) ||
            ($heavy_size > 0 && $this->countHeavyItems( $inventory ) > $heavy_size);
    }

    public function countEssentialItems(Inventory $inventory): int {
        $c = 0;
        foreach ($inventory->getItems() as $item)
            if ($item->getEssential()) $c += $item->getCount();
        return $c;
    }

    const int ErrorNone = 0;
    const int ErrorInvalidTransfer      = ErrorHelper::BaseInventoryErrors + 1;
    const int ErrorInventoryFull        = ErrorHelper::BaseInventoryErrors + 2;
    const int ErrorHeavyLimitHit        = ErrorHelper::BaseInventoryErrors + 3;
    const int ErrorBankLimitHit         = ErrorHelper::BaseInventoryErrors + 4;
    const int ErrorStealLimitHit        = ErrorHelper::BaseInventoryErrors + 5;
    const int ErrorStealBlocked         = ErrorHelper::BaseInventoryErrors + 6;
    const int ErrorBankBlocked          = ErrorHelper::BaseInventoryErrors + 7;
    const int ErrorExpandBlocked        = ErrorHelper::BaseInventoryErrors + 8;
    const int ErrorTransferBlocked      = ErrorHelper::BaseInventoryErrors + 9;
    const int ErrorUnstealableItem      = ErrorHelper::BaseInventoryErrors + 10;
    const int ErrorEscortDropForbidden  = ErrorHelper::BaseInventoryErrors + 11;
    const int ErrorEssentialItemBlocked = ErrorHelper::BaseInventoryErrors + 12;
    const int ErrorTooManySouls         = ErrorHelper::BaseInventoryErrors + 13;
    const int ErrorBankTheftFailed      = ErrorHelper::BaseInventoryErrors + 14;
    const int ErrorTargetChestFull      = ErrorHelper::BaseInventoryErrors + 15;
    const int ErrorTransferStealPMBlock = ErrorHelper::BaseInventoryErrors + 16;
    const int ErrorTransferStealDropInvalid = ErrorHelper::BaseInventoryErrors + 17;
    const int ErrorMultiHeavyLimitHit   = ErrorHelper::BaseInventoryErrors + 18;


    public function forceMoveItem( Inventory $to, Item $item, int $count = 1 ): Item {
        if ($item->getInventory() && $item->getInventory()->getId() === $to->getId())
            return $item;

        if ($inv = $item->getInventory()) {
            if ($item->getCount() > $count) {
                $item->setCount( $item->getCount() - $count);
                $this->entity_manager->persist( $item );
                $item = $this->item_factory->createBaseItemCopy( $item )->setCount($count);
            } else
                $item->getInventory()->removeItem( $item );
            $this->entity_manager->persist($inv);
        }

        $t = $to->findTown();
        if ($t) $this->events->forceTransferItem( $t, $item, $inv, $to );

        // This is a bank or a building inventory
        if (($to->getTown() || $to->getBuilding()) && $possible_stack = $this->findStackPrototype( $to, $item )) {
            $possible_stack->setCount( $possible_stack->getCount() + $item->getCount() );
            $this->entity_manager->persist($possible_stack);
            $this->entity_manager->remove( $item );
            $item = $possible_stack;
        } else {
            $to->addItem( $item );
            $this->entity_manager->persist($item);
            $this->entity_manager->persist($to);
        }

        return $item;
    }

    public function forceRemoveItem( Item $item, int $count = 1, bool $allStacks = false ): void {
        // Initialize the stack and cache the inventory
        $stack = $item;
        $inventory = $item->getInventory();

        do {
            if ($stack->getCount() > $count) {
                // The current item stack is larger than the requested count, so we simply reduce the count
                $stack->setCount($stack->getCount() - $count);
                $this->entity_manager->persist($stack);
                $count = 0;
            } else {
                // The current item stack is smaller than the requested count, so we remove it entirely
                $count -= $stack->getCount();
                $inventory?->removeItem($stack);
                $this->entity_manager->remove($stack);

                // If the allStacks flag is set, we need to check if there's a possible stack to remove
                // This is only needed when the requested count is larger than the current stack count and only possible
                // if we have an inventory
                $stack = ($allStacks && $count > 0 && $inventory)
                    ? $this->findStackPrototype($inventory, $stack)
                    : null;
            }
        // Continue as long as we have a stack to remove and the requested count is still larger than 0
        } while ($count > 0 && $stack );


    }

    public function getAllInventoryIDs( Town $town, bool $bank = true, bool $homes = true, bool $rucksack = true, bool $floor = true, bool $ruinFloor = true, bool $buildings = true ): array {
        // Get all inventory IDs
        // We're just getting IDs, because we don't want to actually hydrate the inventory instances
        $q = $this->entity_manager->createQueryBuilder()
            ->select('i.id')
            ->from(Inventory::class, 'i');
        if ($bank) $q->leftJoin('i.town', 't')->orWhere('t = :town' )->setParameter('town', $town);
        if ($homes) $q->leftJoin('i.home', 'h')->orWhere('h IN (:homes)')->setParameter('homes', array_map( fn(Citizen $c) => $c->getHome(), $town->getCitizens()->getValues()) );
        if ($rucksack) $q->leftJoin('i.citizen', 'c')->orWhere('c IN (:citizens)')->setParameter('citizens', $town->getCitizens());
        if ($floor) $q->leftJoin('i.zone', 'z')->orWhere('z IN (:zones)')->setParameter('zones', $town->getZones());
		if ($buildings) $q->leftJoin('i.building', 'b')->orWhere('b IN (:buildings)')->setParameter('buildings', $town->getBuildings());
        if ($ruinFloor) $q
            ->leftJoin('i.ruinZone', 'rz')->orWhere('rz IN (:ruinZones)')->setParameter('ruinZones', $this->entity_manager->getRepository(RuinZone::class)->findBy(['zone' => $town->getZones()->getValues()]) )
            ->leftJoin('i.ruinZoneRoom', 'rzr')->orWhere('rzr IN (:ruinZones)');
        return array_column($q->getQuery()->getScalarResult(), 'id');
    }

    /**
     * @param Town $town
     * @param ItemPrototype|ItemProperty[]|string|string[] $prototype
     * @param bool $bank
     * @param bool $homes
     * @param bool $rucksack
     * @param bool $floor
     * @param bool $ruinFloor
	 * @param bool $buildings
     * @return Item[]
     */
    public function getAllItems( Town $town, $prototype, bool $bank = true, bool $homes = true, bool $rucksack = true, bool $floor = true, bool $ruinFloor = true, bool $buildings = true ): array {
        if (!is_array($prototype)) $prototype = [$prototype];
        $prototype = array_map( fn($a) => is_string($a) ? $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName($a) : $a, $prototype );

        // Get all items
        return $this->entity_manager->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->andWhere('i.inventory IN (:invs)')->setParameter('invs', $this->getAllInventoryIDs($town, $bank, $homes, $rucksack, $floor, $ruinFloor, $buildings))
            ->andWhere('i.prototype IN (:protos)')->setParameter('protos', $prototype)
            ->getQuery()
            ->getResult();
    }

	/**
	 * @param Town                                         $town
	 * @param ItemPrototype|ItemProperty[]|string|string[] $prototype
	 * @param bool                                         $bank
	 * @param bool                                         $homes
	 * @param bool                                         $rucksack
	 * @param bool                                         $floor
	 * @param bool                                         $ruinFloor
	 * @param bool                                         $buildings
	 * @return int
	 */
    public function countAllItems( Town $town, $prototype, bool $bank = true, bool $homes = true, bool $rucksack = true, bool $floor = true, bool $ruinFloor = true, bool $buildings = true ): int {
        if (!is_array($prototype)) $prototype = [$prototype];
        $prototype = array_map( fn($a) => is_string($a) ? $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName($a) : $a, $prototype );

        // Get all items
        try {
            return $this->entity_manager->createQueryBuilder()
                ->select('SUM(i.count)')
                ->from(Item::class, 'i')
                ->andWhere('i.inventory IN (:invs)')->setParameter('invs', $this->getAllInventoryIDs($town, $bank, $homes, $rucksack, $floor, $ruinFloor, $buildings))
                ->andWhere('i.prototype IN (:protos)')->setParameter('protos', $prototype)
                ->getQuery()->getSingleScalarResult();
        } catch (Exception $e) { return 0; }

    }

}
