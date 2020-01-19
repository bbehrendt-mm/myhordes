<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\ItemPrototype;
use App\Entity\RequireLocation;
use App\Entity\Requirement;
use App\Entity\Result;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use App\Response\AjaxResponse;
use App\Structures\ItemRequest;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActionHandler
{
    private $entity_manager;
    private $status_factory;
    private $citizen_handler;
    private $inventory_handler;
    private $random_generator;
    private $item_factory;
    private $translator;


    public function __construct(
        EntityManagerInterface $em, StatusFactory $sf, CitizenHandler $ch, InventoryHandler $ih,
        RandomGenerator $rg, ItemFactory $if, TranslatorInterface $ti)
    {
        $this->entity_manager = $em;
        $this->status_factory = $sf;
        $this->citizen_handler = $ch;
        $this->inventory_handler = $ih;
        $this->random_generator = $rg;
        $this->item_factory = $if;
        $this->translator = $ti;
    }

    const ActionValidityNone = 1;
    const ActionValidityHidden = 2;
    const ActionValidityCrossed = 3;
    const ActionValidityAllow = 4;
    const ActionValidityFull = 5;

    protected function evaluate( Citizen $citizen, Item $item, ItemAction $action, ?string &$message ): int {

        if (!$item->getPrototype()->getActions()->contains( $action )) return self::ActionValidityNone;

        $current_state = self::ActionValidityFull;
        foreach ($action->getRequirements() as $meta_requirement) {

            $last_state = $current_state;

            $this_state = self::ActionValidityNone;
            switch ($meta_requirement->getFailureMode()) {
                case Requirement::MessageOnFail: $this_state = self::ActionValidityAllow; break;
                case Requirement::CrossOnFail: $this_state = self::ActionValidityCrossed; break;
                case Requirement::HideOnFail: $this_state = self::ActionValidityHidden; break;
            }

            if ($status = $meta_requirement->getStatusRequirement()) {
                $status_is_active = $citizen->getStatus()->contains( $status->getStatus() );
                if ($status_is_active !== $status->getEnabled()) $current_state = min( $current_state, $this_state );
            }

            if ($item_condition = $meta_requirement->getItem()) {
                $item_str = ($is_prop = (bool)$item_condition->getProperty())
                    ? $item_condition->getProperty()->getName()
                    : $item_condition->getPrototype()->getName();

                if (empty($this->inventory_handler->fetchSpecificItems( $citizen->getInventory(),
                    [new ItemRequest($item_str, 1, null, null, $is_prop)]
                ))) $current_state = min( $current_state, $this_state );
            }

            if ($location_condition = $meta_requirement->getLocation())
                switch ( $location_condition->getLocation() ) {
                    case RequireLocation::LocationInTown:
                        if ( $citizen->getZone() ) $current_state = min( $current_state, $this_state );
                        break;
                    case RequireLocation::LocationOutside:
                        if ( !$citizen->getZone() ) $current_state = min( $current_state, $this_state );
                        break;
                    default:
                        break;
                }

            if ($zombie_condition = $meta_requirement->getZombies()) {
                $cp = 0;
                $current_zeds = $citizen->getZone() ? $citizen->getZone()->getZombies() : 0;
                if ( $citizen->getZone() ) foreach ( $citizen->getZone()->getCitizens() as $c )
                    $cp += $this->citizen_handler->getCP( $c );

                if (
                    ($zombie_condition->getMustBlock() && $cp >= $current_zeds) ||
                    ($zombie_condition->getNumber() > $current_zeds)
                ) $current_state = min( $current_state, $this_state );
            }


            if ($current_state < $last_state) $message = $meta_requirement->getFailureText();

        }

        return $current_state;

    }

    /**
     * @param ItemPrototype[] $list
     * @return array
     */
    private function reformat_prototype_list(array $list): array {

        $cache = [];
        foreach ( $list as $entry ) {
            if (!isset( $cache[$entry->getId()] )) $cache[$entry->getId()] = [1,$entry];
            else $cache[$entry->getId()][0]++;
        }

        return $cache;

    }

    /**
     * @param Citizen $citizen
     * @param Item $item
     * @param ItemAction[] $available
     * @param ItemAction[] $crossed
     */
    public function getAvailableItemActions(Citizen $citizen, Item &$item, ?array &$available, ?array &$crossed ) {

        $available = $crossed = [];
        if ($item->getBroken()) return;

        foreach ($item->getPrototype()->getActions() as $action) {
            $mode = $this->evaluate( $citizen, $item, $action, $tx );
            if ($mode >= self::ActionValidityAllow) $available[] = $action;
            else if ($mode >= self::ActionValidityCrossed) $crossed[] = $action;
        }

    }

    const ErrorNone = 0;
    const ErrorActionUnregistered = ErrorHelper::BaseActionErrors + 1;
    const ErrorActionForbidden    = ErrorHelper::BaseActionErrors + 2;
    const ErrorActionImpossible   = ErrorHelper::BaseActionErrors + 3;

    public function execute( Citizen &$citizen, Item &$item, ItemAction $action, ?string &$message, ?array &$remove ): int {

        $remove = [];

        $mode = $this->evaluate( $citizen, $item, $action, $tx );
        if ($mode <= self::ActionValidityNone)    return self::ErrorActionUnregistered;
        if ($mode <= self::ActionValidityCrossed) return self::ErrorActionImpossible;
        if ($mode <= self::ActionValidityAllow) {
            $message = $tx;
            return self::ErrorActionForbidden;
        }
        if ($mode != self::ActionValidityFull) return self::ErrorActionUnregistered;

        $execute_info_cache = [
            'ap' => 0,
            'item' => $item->getPrototype(),
            'item_morph' => [ null, null ],
            'items_consume' => [],
            'items_spawn' => [],
        ];

        $execute_result = function(Result &$result) use (&$citizen, &$item, &$action, &$message, &$remove, &$execute_result, &$execute_info_cache) {
            if ($status = $result->getStatus()) {

                if ($status->getInitial() && $status->getResult()) {
                    if ($citizen->getStatus()->contains( $status->getInitial() )) {
                        $citizen->removeStatus( $status->getInitial() );
                        $citizen->addStatus( $status->getResult() );
                    }
                }
                elseif ($status->getInitial()) $citizen->removeStatus( $status->getInitial() );
                elseif ($status->getResult())  $citizen->addStatus( $status->getResult() );

            }

            if ($ap = $result->getAp()) {
                $old_ap = $citizen->getAp();
                $this->citizen_handler->setAP( $citizen, !$ap->getMax(), $ap->getMax() ? ( $this->citizen_handler->getMaxAP($citizen) + $ap->getAp() ) : $ap->getAp() );
                $execute_info_cache['ap'] += ( $citizen->getAp() - $old_ap );
            }

            if ($item_result = $result->getItem()) {
                if ($execute_info_cache['item_morph'][0] === null) $execute_info_cache['item_morph'][0] = $item->getPrototype();
                if ($item_result->getConsume()) {
                    $citizen->getInventory()->removeItem( $item );
                    $remove[] = $item;
                    $execute_info_cache['items_consume'][] = $item->getPrototype();
                } else {
                    if ($item_result->getMorph())
                        $item->setPrototype( $execute_info_cache['item_morph'][1] = $item_result->getMorph() );
                    if ($item_result->getBreak()  !== null) $item->setBroken( $item_result->getBreak() );
                    if ($item_result->getPoison() !== null) $item->setPoison( $item_result->getPoison() );
                }
            }

            if ($item_spawn = $result->getSpawn()) {
                $proto = null;
                if ($p = $item_spawn->getPrototype())
                    $proto = $p;
                elseif ($g = $item_spawn->getItemGroup())
                    $proto = $this->random_generator->pickItemPrototypeFromGroup( $g );

                if ($proto && $this->inventory_handler->placeItem( $citizen, $this->item_factory->createItem( $proto ),
                    $citizen->getZone()
                        ? [ $citizen->getInventory(), $citizen->getZone()->getFloor() ]
                        : [ $citizen->getInventory(), $citizen->getHome()->getChest(), $citizen->getTown()->getBank() ]
                )) $execute_info_cache['items_spawn'][] = $proto;
            }

            if ($item_consume = $result->getConsume()) {
                $items = $this->inventory_handler->fetchSpecificItems( $citizen->getInventory(),
                    [new ItemRequest( $item_consume->getPrototype()->getName(), $item_consume->getCount() )] );
                foreach ($items as $consume_item) {
                    $citizen->getInventory()->removeItem( $consume_item );
                    $remove[] = $consume_item;
                    $execute_info_cache['items_consume'][] = $consume_item->getPrototype();
                }
            }

            if ($zombie_kill = $result->getZombies()) {

                if ($citizen->getZone())
                    $citizen->getZone()->setZombies( max( 0, $citizen->getZone()->getZombies() - mt_rand( $zombie_kill->getMin(), $zombie_kill->getMax() ) ) );

            }

            if ($result_group = $result->getResultGroup()) {
                $r = $this->random_generator->pickResultsFromGroup( $result_group );
                foreach ($r as &$sub_result) $execute_result( $sub_result );
            }
        };

        foreach ($action->getResults() as &$result) $execute_result( $result );

        if ($action->getMessage()) {

            $trans = function( string $s ): string {
                return $this->translator->trans( $s, [], 'items' );
            };

            $concat = function(array $c) use (&$trans) {
                return implode(', ', array_map(function(array $e) use (&$trans): string {
                    $s = $trans($e[1]->getLabel());
                    return $e[0] > 1 ? ("{$e[0]} x {$s}") : $s;
                }, $this->reformat_prototype_list($c)));
            };

            $message = $this->translator->trans( $action->getMessage(), [
                '{ap}'        => $execute_info_cache['ap'],
                '{item}'      => $execute_info_cache['item']->getLabel(),
                '{item_from}' => $execute_info_cache['item_morph'][0] ? $trans($execute_info_cache['item_morph'][0]->getLabel()) : "",
                '{item_to}'   => $execute_info_cache['item_morph'][1] ? $trans($execute_info_cache['item_morph'][1]->getLabel()) : "",
                '{items_consume}' => $concat($execute_info_cache['items_consume']),
                '{items_spawn}'   => $concat($execute_info_cache['items_spawn']),
            ], 'items' );
        }


        return self::ErrorNone;
    }
}