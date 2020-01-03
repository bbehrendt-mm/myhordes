<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
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

class InventoryHandler
{
    private $entity_manager;
    private $item_factory;

    public function __construct( EntityManagerInterface $em, ItemFactory $if)
    {
        $this->entity_manager = $em;
        $this->item_factory = $if;
    }

    public function getSize( Inventory $inventory ): int {
        if ($inventory->getCitizen())
            return 4;

        if ($inventory->getHome())
            return 4;

        return 0;
    }

    /**
     * @param Inventory $inventory
     * @param ItemRequest[] $requests
     * @return Item[]
     */
    public function fetchSpecificItems(Inventory $inventory, array $requests): array {
        $return = [];
        foreach ($requests as $request) {
            $qb = $this->entity_manager->createQueryBuilder();
            $qb
                ->select('i.id')->from('App:Item','i')
                ->leftJoin('App:ItemPrototype', 'p', Join::WITH, 'i.prototype = p.id')
                ->where('i.inventory = :inv')->setParameter('inv', $inventory)
                ->andWhere('p.name = :type')->setParameter('type', $request->getItemPrototypeName())
                ->setMaxResults( $request->getCount() );
            if (!empty($return)) $qb->andWhere('i.id NOT IN (:found)')->setParameter('found', $return);
            if ($request->filterBroken()) $qb->andWhere('i.broken = :isb')->setParameter('isb', $request->getBroken());
            if ($request->filterPoison()) $qb->andWhere('i.poison = :isp')->setParameter('isp', $request->getPoison());

            $result = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_SCALAR);
            if (count($result) !== $request->getCount()) return [];
            else $return = array_merge($return, array_map(function(array $a): int { return $a['id']; }, $result));
        }
        return array_map(function(int $id): Item {
            return $this->entity_manager->getRepository(Item::class)->find( $id );
        }, $return);
    }

    public function fetchHeavyItems(Inventory $inventory) {
        $qb = $this->entity_manager->createQueryBuilder();
        $qb
            ->select('i.id')->from('App:Item','i')
            ->leftJoin('App:ItemPrototype', 'p', Join::WITH, 'i.prototype = p.id')
            ->where('i.inventory = :inv')->setParameter('inv', $inventory)
            ->andWhere('p.heavy = :hv')->setParameter('hv', true);

        $result = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_SCALAR);
        return array_map(function(array $a): Item { return $this->entity_manager->getRepository(Item::class)->find( $a['id'] ); }, $result);
    }

    const TransferTypeUnknown = 0;
    const TransferTypeSpawn = 1;
    const TransferTypeConsume = 2;
    const TransferTypeRucksack = 3;
    const TransferTypeBank = 4;
    const TransferTypeHome = 5;
    const TransferTypeSteal = 6;
    const TransferTypeLocal = 7;
    const TransferTypeEscort = 8;

    protected function validateTransferTypes( int $target, int $source ): bool {
        $valid_types = [
            self::TransferTypeSpawn => [ self::TransferTypeRucksack, self::TransferTypeBank, self::TransferTypeHome, self::TransferTypeLocal ],
            self::TransferTypeRucksack => [ self::TransferTypeBank, self::TransferTypeLocal, self::TransferTypeHome, self::TransferTypeConsume ],
            self::TransferTypeBank => [ self::TransferTypeRucksack, self::TransferTypeConsume ],
            self::TransferTypeHome => [ self::TransferTypeRucksack, self::TransferTypeConsume ],
            self::TransferTypeSteal => [ self::TransferTypeRucksack ],
            self::TransferTypeLocal => [ self::TransferTypeRucksack, self::TransferTypeEscort, self::TransferTypeConsume ],
            self::TransferTypeEscort => [ self::TransferTypeRucksack, self::TransferTypeConsume ]
        ];

        return isset($valid_types[$source]) && in_array($target, $valid_types[$source]);
    }

    protected function singularTransferType( ?Citizen $citizen, ?Inventory $inventory ): int {
        if (!$citizen || !$inventory) return self::TransferTypeUnknown;

        // ToDo: Actually check this!
        $citizen_is_at_home = true;

        // Check if the inventory belongs to a town, and if the town is the same town as that of the citizen
        if ($inventory->getTown() && $inventory->getTown()->getId() === $citizen->getTown()->getId())
            return $citizen_is_at_home ? self::TransferTypeBank : self::TransferTypeUnknown;

        // Check if the inventory belongs to a house, and if the house is owned by the citizen
        if ($inventory->getHome() && $inventory->getHome()->getId() === $citizen->getHome()->getId())
            return $citizen_is_at_home ? self::TransferTypeHome : self::TransferTypeUnknown;

        // Check if the inventory belongs to a house, and if the house is owned by a different citizen of the same town
        if ($inventory->getHome() && $inventory->getHome()->getId() !== $citizen->getHome()->getId() && $inventory->getHome()->getCitizen()->getTown()->getId() === $citizen->getTown()->getId())
            return $citizen_is_at_home ? self::TransferTypeSteal : self::TransferTypeUnknown;

        // Check if the inventory belongs directly to the citizen
        if ($inventory->getCitizen() && $inventory->getCitizen()->getId() === $citizen->getId())
            return self::TransferTypeRucksack;

        //ToDo: Check local
        //ToDo: Check escort
        return self::TransferTypeUnknown;
    }

    protected function transferType( Citizen &$citizen, ?Inventory &$target, ?Inventory &$source, ?int &$target_type, ?int &$source_type): bool {
        $source_type = !$source ? self::TransferTypeSpawn   : self::singularTransferType( $citizen, $source );
        $target_type = !$target ? self::TransferTypeConsume : self::singularTransferType( $citizen, $target );
        return $this->validateTransferTypes($target_type, $source_type);
    }

    const ErrorNone = 0;
    const ErrorInvalidTransfer = ErrorHelper::BaseInventoryErrors + 1;
    const ErrorInventoryFull   = ErrorHelper::BaseInventoryErrors + 2;
    const ErrorHeavyLimitHit   = ErrorHelper::BaseInventoryErrors + 3;
    const ErrorBankLimitHit    = ErrorHelper::BaseInventoryErrors + 4;
    const ErrorStealLimitHit   = ErrorHelper::BaseInventoryErrors + 5;

    public function transferItem( ?Citizen &$actor, Item &$item, ?Inventory &$from, ?Inventory &$to ): int {
        // Check if the source is valid
        if ($item->getInventory() && ( !$from || $from->getId() !== $item->getInventory()->getId() ) )
            return self::ErrorInvalidTransfer;

        if (!$this->transferType( $actor, $to, $from, $type_to, $type_from ))
            return self::ErrorInvalidTransfer;

        // Check inventory size
        if ($to && ($max_size = $this->getSize($to)) > 0 && count($to->getItems()) >= $max_size ) return self::ErrorInventoryFull;

        // Check Heavy item limit
        if ($item->getPrototype()->getHeavy() &&
            ($type_to === self::TransferTypeRucksack || $type_to === self::TransferTypeEscort) &&
            !empty($this->fetchHeavyItems($to))
        ) return self::ErrorHeavyLimitHit;

        //ToDo Check Bank lock
        //if ($type_from === self::TransferTypeBank) {}

        //ToDo Check Steal lock
        //if ($type_from === self::TransferTypeSteal) {}

        $item->setInventory( $to );
        return self::ErrorNone;
    }

}