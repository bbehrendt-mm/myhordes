<?php


namespace App\EventListener\Game\Town\Addon\Dump;


use App\Controller\Town\TownController;
use App\Entity\ActionCounter;
use App\Entity\ItemPrototype;
use App\Enum\ActionCounterType;
use App\Event\Game\Town\Addon\Dump\DumpInsertionCheckData;
use App\Event\Game\Town\Addon\Dump\DumpInsertionCheckEvent;
use App\Event\Game\Town\Addon\Dump\DumpInsertionExecuteEvent;
use App\Event\Game\Town\Basic\Buildings\BuildingEffectEvent;
use App\Event\Game\Town\Basic\Buildings\BuildingEffectPostAttackEvent;
use App\Service\ErrorHelper;
use App\Service\InventoryHandler;
use App\Service\LogTemplateHandler;
use App\Service\TownHandler;
use App\Structures\ItemRequest;
use App\Translation\T;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: DumpInsertionCheckEvent::class, method: 'onCheckDumpAvailability', priority: -10)]
#[AsEventListener(event: DumpInsertionCheckEvent::class, method: 'onCheckItems', priority: -20)]

#[AsEventListener(event: DumpInsertionExecuteEvent::class, method: 'onItemHandling', priority: 10)]
#[AsEventListener(event: DumpInsertionExecuteEvent::class, method: 'onLogMessages', priority: 0)]
#[AsEventListener(event: DumpInsertionExecuteEvent::class, method: 'onFlashMessages', priority: 0)]

#[AsEventListener(event: BuildingEffectPostAttackEvent::class, method: 'onProcessPostAttackEffect', priority: -5)]
final readonly class DumpInsertionCommonListener implements ServiceSubscriberInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public static function getSubscribedServices(): array
    {
        return [
            InventoryHandler::class,
            EntityManagerInterface::class,
            LogTemplateHandler::class,
            TownHandler::class,
            TranslatorInterface::class
        ];
    }

    public function onCheckDumpAvailability(DumpInsertionCheckEvent $event ): void {
        if (($event->citizen->getBanished() && !$event->to_home) || !$event->dump_built) {
            $event->pushErrorCode( ErrorHelper::ErrorActionNotAvailable )->stopPropagation();
            return;
        }

        if ($event->to_home && ($event->citizen->getSpecificActionCounterValue(ActionCounterType::HomeDump) + $event->quantity) > ($event->citizen->getHome()->getPrototype()->getLevel() * 2)) {
            $event->pushErrorCode( ErrorHelper::ErrorActionNotAvailable )->stopPropagation();
            return;
        }

        /** @var TownHandler $townHandler */
        $townHandler = $this->container->get(TownHandler::class);

        $dump = $townHandler->getBuilding($event->citizen->getTown(), "small_trash_#00");
        $cache = [];

        foreach ($event->citizen->getTown()->getBank()->getItems() as $item) {
            if ($item->getBroken()) continue;

            $dumpDef = DumpUpgradesCheckListener::getDumpItemDef( $item->getPrototype(), $event );
            if ($dumpDef == 0) continue;

            if (!isset($cache[$item->getPrototype()->getId()]))
                $cache[$item->getPrototype()->getId()] = [
                    $item->getPrototype()->getId(),
                    $item->getCount(),
                    $dumpDef,
                    0,
                    0,
                ];
            else $cache[$item->getPrototype()->getId()][1] += $item->getCount();
        }

        foreach ($dump->getInventory()->getItems() as $item)
            if (!isset($cache[$item->getPrototype()->getId()])) {
                $cache[$item->getPrototype()->getId()] = [
                    $item->getPrototype()->getId(),
                    0,
                    DumpUpgradesCheckListener::getDumpItemDef( $item->getPrototype(), $event ),
                    $item->getCount(),
                    0
                ];
            } else $cache[$item->getPrototype()->getId()][3] += $item->getCount();

        if ($event->citizen->getHome()->getPrototype()->getLevel() > 0)
            foreach ($event->citizen->getInventory()->getItems() as $item) {

                $dumpDef = DumpUpgradesCheckListener::getDumpItemDef( $item->getPrototype(), $event );
                if ($dumpDef == 0) continue;

                if (!isset($cache[$item->getPrototype()->getId()])) {
                    $cache[$item->getPrototype()->getId()] = [
                        $item->getPrototype()->getId(),
                        0,
                        DumpUpgradesCheckListener::getDumpItemDef( $item->getPrototype(), $event ),
                        0,
                        $item->getCount(),
                    ];
                } else $cache[$item->getPrototype()->getId()][4] += $item->getCount();

            }

        usort( $cache, fn(array $a, array $b) => $a[2] <=> $b[2] ?: $a[0] <=> $b[0]);

        $event->dumpableItems = $cache;
    }

    /**
     * @param DumpInsertionCheckEvent $event
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onCheckItems( DumpInsertionCheckEvent $event ): void {
        if ($event->citizen->getBanished() || !$event->dump_built)
            $event->pushErrorCode( ErrorHelper::ErrorActionNotAvailable )->stopPropagation();

        $proto = $event->consumable;

        if (!$proto) {
            $event->pushErrorCode( ErrorHelper::ErrorInvalidRequest )->stopPropagation();
            return;
        }

        $translator = $this->container->get(TranslatorInterface::class);
        if (($event->to_home && $event->quantity > 1) || $event->quantity > 20) {
            $event->pushError( ErrorHelper::ErrorActionNotAvailable, $translator->trans('Du kannst nicht so viele Gegenstände auf die Müllhalde werfen.', [], 'game') )->stopPropagation();
            return;
        }

        /** @var InventoryHandler $inventoryHandler */
        $inventoryHandler = $this->container->get(InventoryHandler::class);

        // You don't have enough AP (the first dump is free)
        if ($event->citizen->getSpecificActionCounterValue(ActionCounterType::DumpInsertion) > 0 && $event->citizen->getAp() < $event->ap_cost) {
            $event->pushErrorCode(ErrorHelper::ErrorNoAP)->stopPropagation();
            return;
        }

        // Check if items are available
        $items = $inventoryHandler->fetchSpecificItems( $event->to_home
                                                            ? $event->citizen->getInventory()
                                                            : $event->citizen->getTown()->getBank(),
                                                        [new ItemRequest($proto->getName(), $event->quantity)] );
        if (!$items) {
            $event->pushErrorCode(ErrorHelper::ErrorItemsMissing)->stopPropagation();
        }
    }

    /**
     * @param DumpInsertionExecuteEvent $event
     * @return void
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onItemHandling(DumpInsertionExecuteEvent $event ): void {
        if (!$event->check->consumable) return;

        /** @var InventoryHandler $inventoryHandler */
        $inventoryHandler = $this->container->get(InventoryHandler::class);
        /** @var TownHandler $townHandler */
        $townHandler = $this->container->get(TownHandler::class);

        $dump_def = $event->check->defense;

        $dump = $townHandler->getBuilding($event->citizen->getTown(), "small_trash_#00");

        $items = $inventoryHandler->fetchSpecificItems( $event->check->to_home
                                                            ? $event->citizen->getInventory()
                                                            : $event->citizen->getTown()->getBank(),
                                                        [new ItemRequest($event->check->consumable->getName(), $event->quantity)] );

        // Remove items
        $n = $event->quantity;
        while (!empty($items) && $n > 0) {
            $item = array_pop($items);
            $c = $item->getCount();
            $qtyMoved = min($c, $n);
            if ($event->check->to_home)
                $inventoryHandler->forceRemoveItem($item, $qtyMoved);
            else $inventoryHandler->forceMoveItem($dump->getInventory(), $item, $qtyMoved);
            $n -= $c;
        }

        // Reduce AP
        if ($event->citizen->getSpecificActionCounterValue(ActionCounterType::DumpInsertion) > 0)
            $event->citizen->setAp( $event->citizen->getAp() - $event->check->ap_cost );

        // Increase def
        if ($event->check->to_home)
            $event->citizen->getHome()->setDumpTemporaryDefense( $event->citizen->getHome()->getDumpTemporaryDefense() + $event->quantity * $dump_def );
        else $dump->setTempDefenseBonus( $dump->getTempDefenseBonus() + $event->quantity * $dump_def );

        $event->addedDefense = $event->quantity * $dump_def;

        // Set ActionCounter
        $event->citizen->getSpecificActionCounter(ActionCounterType::DumpInsertion)->increment();
        if ($event->check->to_home) $event->citizen->getSpecificActionCounter(ActionCounterType::HomeDump)->increment( $event->quantity );

        $event->markModified();
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function onLogMessages(DumpInsertionExecuteEvent $event ): void {
        if ($event->check->to_home) return;

		$itemsForLog = [
			$event->check->consumable->getId() => [
				'item' => $event->check->consumable,
				'count' => $event->quantity
			]
		];

        $this->container->get(EntityManagerInterface::class)->persist(
            $this->container->get(LogTemplateHandler::class)->dumpItems( $event->citizen, $itemsForLog, $event->addedDefense )
        );

        $event->markModified();
    }

    public function onFlashMessages(DumpInsertionExecuteEvent $event ): void {
        if ($event->check->to_home)
            $event->addFlashMessage(
                T::__('Du hast {item} verwendet, um dein Haus abzusichern. <strong>Dein Haus hat {def} Verteidigungspunkt(e) dazugewonnen.</strong> Was werden die anderen Stadtbewohner wohl dazu sagen..?', 'game'), 'notice',
                'game', ['item' => $event->check->consumable, 'count' => $event->check->quantity, 'def' => $event->addedDefense]
            );
        else
            $event->addFlashMessage(
                T::__('Du hast {count} x {item} auf der öffentlichen Müllhalde abgeladen. <strong>Die Stadt hat {def} Verteidigungspunkt(e) dazugewonnen.</strong>', 'game'), 'notice',
                'game', ['item' => $event->check->consumable, 'count' => $event->check->quantity, 'def' => $event->addedDefense]
            );
    }

    public function onProcessPostAttackEffect(BuildingEffectEvent $event): void {
        // we want to empty the dump's inventory
        if ($event->building->getPrototype()->getName() !== "small_trash_#00") return;

        $inventoryHandler = $this->container->get(InventoryHandler::class);
        foreach ($event->building->getInventory()->getItems() as $item) {
            $inventoryHandler->forceRemoveItem($item, $item->getCount());
        }
    }
}
