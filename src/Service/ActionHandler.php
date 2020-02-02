<?php


namespace App\Service;


use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\ItemPrototype;
use App\Entity\Recipe;
use App\Entity\RequireLocation;
use App\Entity\Requirement;
use App\Entity\Result;
use App\Entity\RolePlayerText;
use App\Structures\ItemRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Asset\Packages;

class ActionHandler
{
    private $entity_manager;
    private $status_factory;
    private $citizen_handler;
    private $inventory_handler;
    private $random_generator;
    private $item_factory;
    private $translator;
    private $game_factory;
    private $assets;


    public function __construct(
        EntityManagerInterface $em, StatusFactory $sf, CitizenHandler $ch, InventoryHandler $ih,
        RandomGenerator $rg, ItemFactory $if, TranslatorInterface $ti, GameFactory $gf, Packages $am)
    {
        $this->entity_manager = $em;
        $this->status_factory = $sf;
        $this->citizen_handler = $ch;
        $this->inventory_handler = $ih;
        $this->random_generator = $rg;
        $this->item_factory = $if;
        $this->translator = $ti;
        $this->game_factory = $gf;
        $this->assets = $am;
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

    /**
     * @param ItemPrototype|BuildingPrototype|string $o
     * @param $c
     * @return string
     */
    private function wrap($o, $c=1) :string {
        $s = ''; $i = null;
        if (is_a($o, ItemPrototype::class)) {
            $s = $this->translator->trans($o->getLabel(), [], 'items');
            $i = 'build/images/item/item_' . $o->getIcon() . '.gif';
        } else if (is_a($o, BuildingPrototype::class)) {
            $s =  $this->translator->trans($o->getLabel(), [], 'buildings');
            $i = 'build/images/building/' . $o->getIcon() . '.gif';
        }
        else if (is_string($o)) $s = $o;
        else if (is_null($o)) $s = 'NULL';
        else $s = '_UNKNOWN_';

        if (!empty($i)) $i = $this->assets->getUrl( $i );
        return '<span>' . ($c > 1 ? "$c x " : '') . ($i ? "<img alt='' src='$i' />" : '') . $s .  '</span>';
    }

    private function wrap_concat(array $c) {
        return implode(', ', array_map(function(array $e): string {
            return $this->wrap( $e[1], $e[0] );
        }, $this->reformat_prototype_list($c)));
    }

    const ErrorNone = 0;
    const ErrorActionUnregistered = ErrorHelper::BaseActionErrors + 1;
    const ErrorActionForbidden    = ErrorHelper::BaseActionErrors + 2;
    const ErrorActionImpossible   = ErrorHelper::BaseActionErrors + 3;

    public function execute( Citizen &$citizen, Item &$item, ItemAction $action, ?string &$message, ?array &$remove ): int {

        $remove = [];
        $tags = [];

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
            'bp_spawn' => [],
            'rp_text' => '',
        ];

        $execute_result = function(Result &$result) use (&$citizen, &$item, &$action, &$message, &$remove, &$execute_result, &$execute_info_cache, &$tags) {
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

            if ($bp = $result->getBlueprint()) {
                $possible = $this->entity_manager->getRepository(BuildingPrototype::class)->findProspectivePrototypes( $citizen->getTown() );
                $filtered = array_filter( $possible, function(BuildingPrototype $proto) use ($bp) {
                    if ($bp->getType() !== null && $bp->getType() === $proto->getBlueprint() ) return true;
                    else return $bp->getList()->contains( $proto );
                } );

                if (!empty($filtered)) {
                    $pick = $this->random_generator->pick( $filtered );
                    $town = $citizen->getTown();
                    if ($this->game_factory->addBuilding( $town, $pick )) {
                        $tags[] = 'bp_ok';
                        $execute_info_cache['bp_spawn'][] = $pick;
                    }

                } else $tags[] = 'bp_fail';
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

            if ($result->getRolePlayerText()) {
                /** @var RolePlayerText|null $text */
                $text = $this->random_generator->pick( $this->entity_manager->getRepository(RolePlayerText::class)->findAll() );
                if ($text && $citizen->getUser()->getFoundTexts()->contains($text))
                    $tags[] = 'rp_fail';
                else {
                    $tags[] = 'rp_ok';
                    $execute_info_cache['rp_text'] = $text->getTitle();
                    $citizen->getUser()->getFoundTexts()->add( $text );
                }
            }

            if ($result_group = $result->getResultGroup()) {
                $r = $this->random_generator->pickResultsFromGroup( $result_group );
                foreach ($r as &$sub_result) $execute_result( $sub_result );
            }
        };

        foreach ($action->getResults() as &$result) $execute_result( $result );

        if ($action->getMessage()) {

            $message = $this->translator->trans( $action->getMessage(), [
                '{ap}'        => $execute_info_cache['ap'],
                '{item}'      => $this->wrap($execute_info_cache['item']),
                '{item_from}' => $execute_info_cache['item_morph'][0] ? ($this->wrap($execute_info_cache['item_morph'][0])) : "-",
                '{item_to}'   => $execute_info_cache['item_morph'][1] ? ($this->wrap($execute_info_cache['item_morph'][1])) : "-",
                '{items_consume}' => $this->wrap_concat($execute_info_cache['items_consume']),
                '{items_spawn}'   => $this->wrap_concat($execute_info_cache['items_spawn']),
                '{bp_spawn}'      => $this->wrap_concat($execute_info_cache['bp_spawn']),
                '{rp_text}'       => $this->wrap( $execute_info_cache['rp_text'] )
            ], 'items' );

            do {
                $message = preg_replace_callback( '/<t-(.*?)>(.*?)<\/t-\1>/' , function(array $m) use ($tags): string {
                    list(, $tag, $text) = $m;
                    return in_array( $tag, $tags ) ? $text : '';
                }, $message, -1, $c);
            } while ($c > 0);
        }


        return self::ErrorNone;
    }

    public function execute_recipe( Citizen &$citizen, Recipe &$recipe, ?array &$remove, ?string &$message ): int {
        $town = $citizen->getTown();
        $c_inv = $citizen->getInventory();
        $t_inv = $citizen->getTown()->getBank();

        $remove = [];

        $have_saw  = $this->inventory_handler->countSpecificItems( $c_inv, $this->entity_manager->getRepository( ItemPrototype::class )->findOneByName( 'saw_tool_#00' ) ) > 0;
        $have_manu = $this->game_factory->getBuilding($town, 'small_factory_#00', true) !== null;

        $ap = 3 - ($have_saw ? 1 : 0) - ($have_manu ? 1 : 0);

        if ( $citizen->getAp() < $ap || $this->citizen_handler->isTired( $citizen ) )
            return ErrorHelper::ErrorNoAP;

        $source_inv = $recipe->getType() === Recipe::WorkshopType ? $t_inv : $c_inv;
        $target_inv = $recipe->getType() === Recipe::WorkshopType ? [ $t_inv ] : [ $c_inv, $citizen->getZone() ? $citizen->getZone()->getFloor() : $citizen->getHome()->getChest() ];

        $s = [];
        foreach ($recipe->getSource()->getEntries() as $entry)
            $s[] = (new ItemRequest($entry->getPrototype()->getName(), $entry->getChance() ));
        $items = $this->inventory_handler->fetchSpecificItems( $source_inv, $s );
        if (empty($items)) return ErrorHelper::ErrorItemsMissing;

        $list = [];
        foreach ($items as $item) {
            $source_inv->removeItem( $item );
            $list[] = $item->getPrototype();
            $remove[] = $item;
        }

        $this->citizen_handler->setAP( $citizen, true, -$ap);

        $new_item = $this->random_generator->pickItemPrototypeFromGroup( $recipe->getResult() );
        $this->inventory_handler->placeItem( $citizen, $this->item_factory->createItem( $new_item ) , $target_inv );

        $message = $this->translator->trans( 'Du hast %item_list% in der Werkstatt zu %item% umgewandelt.', [
            '%item_list%' => $this->wrap_concat( $list ),
            '%item%' => $this->wrap( $new_item ),
        ], 'game' );

        return self::ErrorNone;
    }
}