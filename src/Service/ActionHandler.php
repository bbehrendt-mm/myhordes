<?php


namespace App\Service;


use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\CitizenStatus;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\ItemPrototype;
use App\Entity\ItemTargetDefinition;
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
    private $death_handler;
    private $inventory_handler;
    private $random_generator;
    private $item_factory;
    private $translator;
    private $game_factory;
    private $town_handler;
    private $assets;


    public function __construct(
        EntityManagerInterface $em, StatusFactory $sf, CitizenHandler $ch, InventoryHandler $ih, DeathHandler $dh,
        RandomGenerator $rg, ItemFactory $if, TranslatorInterface $ti, GameFactory $gf, Packages $am, TownHandler $th)
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
        $this->town_handler = $th;
        $this->death_handler = $dh;
    }

    const ActionValidityNone = 1;
    const ActionValidityHidden = 2;
    const ActionValidityCrossed = 3;
    const ActionValidityAllow = 4;
    const ActionValidityFull = 5;

    protected function evaluate( Citizen $citizen, Item $item, ?Item $target, ItemAction $action, ?string &$message ): int {

        if (!$item->getPrototype()->getActions()->contains( $action )) return self::ActionValidityNone;
        if ($target && (!$action->getTarget() || !$this->targetDefinitionApplies($target, $action->getTarget())))
            return self::ActionValidityNone;

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

            if ($home = $meta_requirement->getHome()) {
                if ($home->getMinLevel() !== null && $citizen->getHome()->getPrototype()->getLevel() < $home->getMinLevel()) $current_state = min( $current_state, $this_state );
            }

            if ($ap = $meta_requirement->getAp()) {
                $max = $ap->getRelativeMax() ? ($this->citizen_handler->getMaxAP( $citizen ) + $ap->getMax()) : $ap->getMax();
                if ($citizen->getAp() < $ap->getMin() || $citizen->getAp() > $max) $current_state = min( $current_state, $this_state );
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

            if ($building_condition = $meta_requirement->getBuilding()) {
                $town = $citizen->getTown();
                $building = $this->town_handler->getBuilding($town, $building_condition->getBuilding(), false);

                $cpl = true;
                if ($building) {
                    if ($building_condition->getComplete() !== null && $building_condition->getComplete() !== $building->getComplete()) $current_state = min( $current_state, $this_state );
                    if ($building->getComplete()) {
                        if ($building_condition->getMinLevel() > $building->getLevel()) $current_state = min( $current_state, $this_state );
                        if ($building_condition->getMaxLevel() < $building->getLevel()) $current_state = min( $current_state, $this_state );
                    }
                    elseif ($building_condition->getMinLevel() !== null) $current_state = min( $current_state, $this_state );
                    elseif ($building_condition->getMaxLevel() !== null) $current_state = min( $current_state, $this_state );
                }
                elseif ($building_condition->getComplete() === true) $current_state = min( $current_state, $this_state );
                elseif ($building_condition->getMinLevel() !== null) $current_state = min( $current_state, $this_state );
                elseif ($building_condition->getMaxLevel() !== null) $current_state = min( $current_state, $this_state );
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
            $mode = $this->evaluate( $citizen, $item, null, $action, $tx );
            if ($mode >= self::ActionValidityAllow) $available[] = $action;
            else if ($mode >= self::ActionValidityCrossed) $crossed[] = $action;
        }

    }

    public function targetDefinitionApplies(Item $item, ItemTargetDefinition $definition): bool {
        if ($definition->getHeavy() !== null && $item->getPrototype()->getHeavy() !== $definition->getHeavy()) return false;
        if ($definition->getBroken() !== null && $item->getBroken() !== $definition->getBroken()) return false;
        if ($definition->getPoison() !== null && $item->getPoison() !== $definition->getPoison()) return false;
        if ($definition->getPrototype() !== null && $item->getPrototype()->getId() !== $definition->getPrototype()->getId()) return false;
        if ($definition->getTag() !== null && !$item->getPrototype()->getProperties()->contains($definition->getTag())) return false;
        return true;
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

    public function execute( Citizen &$citizen, Item &$item, ?Item &$target, ItemAction $action, ?string &$message, ?array &$remove ): int {

        $remove = [];
        $tags = [];

        $mode = $this->evaluate( $citizen, $item, $target, $action, $tx );
        if ($mode <= self::ActionValidityNone)    return self::ErrorActionUnregistered;
        if ($mode <= self::ActionValidityCrossed) return self::ErrorActionImpossible;
        if ($mode <= self::ActionValidityAllow) {
            $message = $tx;
            return self::ErrorActionForbidden;
        }
        if ($mode != self::ActionValidityFull) return self::ErrorActionUnregistered;

        $execute_info_cache = [
            'ap' => 0,
            'item'   => $item->getPrototype(),
            'target' => $target ? $target->getPrototype() : null,
            'item_morph' => [ null, null ],
            'item_target_morph' => [ null, null ],
            'items_consume' => [],
            'items_spawn' => [],
            'bp_spawn' => [],
            'rp_text' => '',
            'casino' => '',
            'well' => 0,
        ];

        $execute_result = function(Result &$result) use (&$citizen, &$item, &$target, &$action, &$message, &$remove, &$execute_result, &$execute_info_cache, &$tags) {
            if ($status = $result->getStatus()) {

                if ($status->getInitial() && $status->getResult()) {
                    if ($citizen->getStatus()->contains( $status->getInitial() )) {
                        $this->citizen_handler->removeStatus( $citizen, $status->getInitial() );
                        $this->citizen_handler->inflictStatus( $citizen, $status->getResult() );
                    }
                }
                elseif ($status->getInitial()) $this->citizen_handler->removeStatus( $citizen, $status->getInitial() );
                elseif ($status->getResult())  $this->citizen_handler->inflictStatus( $citizen, $status->getResult() );

            }

            if ($ap = $result->getAp()) {
                $old_ap = $citizen->getAp();
                if ($ap->getMax()) {
                    $to = $this->citizen_handler->getMaxAP($citizen) + $ap->getAp();
                    $this->citizen_handler->setAP( $citizen, false, max( $old_ap, $to ), null );
                } else $this->citizen_handler->setAP( $citizen, true, $ap->getAp(), $ap->getAp() < 0 ? null :$ap->getBonus() );

                $execute_info_cache['ap'] += ( $citizen->getAp() - $old_ap );
            }

            if ($death = $result->getDeath()) {
                $this->death_handler->kill( $citizen, $death->getCause(), $r );
                foreach ($r as $r_entry) $remove[] = $r_entry;
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
                    if ($this->town_handler->addBuilding( $town, $pick )) {
                        $tags[] = 'bp_ok';
                        $execute_info_cache['bp_spawn'][] = $pick;
                    }

                } else $tags[] = 'bp_fail';
            }

            if ($item_result = $result->getItem()) {
                if ($execute_info_cache['item_morph'][0] === null) $execute_info_cache['item_morph'][0] = $item->getPrototype();
                if ($item_result->getConsume()) {
                    $item->getInventory()->removeItem($item);
                    $remove[] = $item;
                    $execute_info_cache['items_consume'][] = $item->getPrototype();
                } else {
                    if ($item_result->getMorph())
                        $item->setPrototype( $execute_info_cache['item_morph'][1] = $item_result->getMorph() );
                    if ($item_result->getBreak()  !== null) $item->setBroken( $item_result->getBreak() );
                    if ($item_result->getPoison() !== null) $item->setPoison( $item_result->getPoison() );
                }
            }

            if ($target_result = $result->getTarget()) {
                if ($execute_info_cache['item_target_morph'][0] === null) $execute_info_cache['item_target_morph'][0] = $target->getPrototype();
                if ($target_result->getConsume()) {
                    $target->getInventory()->removeItem($target);
                    $remove[] = $target;
                    $execute_info_cache['items_consume'][] = $target->getPrototype();
                } else {
                    if ($target_result->getMorph())
                        $target->setPrototype( $execute_info_cache['item_target_morph'][1] = $target_result->getMorph() );
                    if ($target_result->getBreak()  !== null) $target->setBroken( $target_result->getBreak() );
                    if ($target_result->getPoison() !== null) $target->setPoison( $target_result->getPoison() );
                }
            }

            if ($item_spawn = $result->getSpawn()) {
                for ($i = 0; $i < $item_spawn->getCount(); $i++ ) {
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

            if ($home_set = $result->getHome()) {

                $citizen->getHome()->setAdditionalStorage( $citizen->getHome()->getAdditionalStorage() + $home_set->getAdditionalStorage() );
                $citizen->getHome()->setAdditionalDefense( $citizen->getHome()->getAdditionalDefense() + $home_set->getAdditionalDefense() );

            }

            if ($well = $result->getWell()) {

                $add = mt_rand( $well->getFillMin(), $well->getFillMax() );
                $citizen->getTown()->setWell( $citizen->getTown()->getWell() + $add );
                $execute_info_cache['well'] += $add;
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

            if ($result->getCasino())
            {
                $ap     = false;
                $terror = false;
                switch ($result->getCasino()) {
                    case 1:
                        $dice = [ mt_rand(1, 6), mt_rand(1, 6), mt_rand(1, 6) ];
                        $cmg = $this->translator->trans('Du hast folgendes gewürfelt: {dc1}, {dc2} und {dc3}.', [
                            '{dc1}' => "<b>{$dice[0]}</b>",
                            '{dc2}' => "<b>{$dice[1]}</b>",
                            '{dc3}' => "<b>{$dice[2]}</b>",
                        ], 'items');
                        sort($dice);

                        if ( $dice[0] === $dice[1] && $dice[0] === $dice[2] ) {
                            $ap = true;
                            $cmg .= ' ' . $this->translator->trans('Wow, du hast einen Trippel geworfen. Das hat so viel Spaß gemacht, dass du 1AP gewinnst!', [], 'items');
                        } else if ( $dice[0] === ($dice[1]-1) && $dice[0] === ($dice[2]-2) ) {
                            $ap = true;
                            $cmg .= ' ' . $this->translator->trans('Wow, du hast eine Straße geworfen. Das hat so viel Spaß gemacht, dass du 1AP gewinnst!', [], 'items');
                        } else if ( $dice[0] === 1 && $dice[0] === 2 && $dice[2] === 4 ) {
                            $ap = true;
                            $cmg .= ' ' . $this->translator->trans('Wow, du hast beim ersten Versuch eine 4-2-1 geworfen. Das hat so viel Spaß gemacht, dass du 1AP gewinnst!', [], 'items');
                        } else if ( $dice[0] === $dice[1] || $dice[1] === $dice[2] )
                            $cmg .= ' ' . $this->translator->trans('Nicht schlecht, du hast einen Pasch geworfen.', [], 'items');
                        else $cmg .= ' ' . $this->translator->trans('Was für ein Spaß!', [], 'items');

                        $execute_info_cache['casino'] = $cmg;
                        break;
                    case 2:
                        $card = mt_rand(0, 53);
                        $color = floor($card / 13);
                        $value = $card - ( $color * 13 );

                        if ( $color > 3 ) {
                            if ($value === 0) {
                                $terror = true;
                                $cmg = $this->translator->trans('Du ziehst eine Karte... und stellst fest, dass dein Name darauf mit Blut geschrieben steht! Du erstarrst vor Schreck!', [], 'items');
                            } else {
                                $ap = true;
                                $cmg = $this->translator->trans('Du ziehst eine Karte... und stellst fest, dass du die Karte mit den Spielregeln gezogen hast! Das erheitert dich so sehr, dass du 1AP gewinnst.', [], 'items');
                            }
                        } else {

                            $s_color = $this->translator->trans((['Kreuz','Pik','Herz','Karo'])[$color], [], 'items');
                            $s_value = $value < 9 ? ('' . ($value+2)) : $this->translator->trans((['Bube','Dame','König','Ass'])[$value-9], [], 'items');

                            $cmg = $this->translator->trans('Du ziehst eine Karte... es ist die {color} {value}.', [
                                '{color}' => "<b>{$s_color}</b>",
                                '{value}' => "<b>{$s_value}</b>",
                            ], 'items');

                            if ( $value === 12 ) {
                                $ap = true;
                                $cmg .= ' ' . $this->translator->trans('Das muss ein Zeichen sein! In dieser Welt ist kein Platz für Moral... du erhälst 1AP.', [], 'items');
                            } else if ($value === 10 && $color === 2) {
                                $ap = true;
                                $cmg .= ' ' . $this->translator->trans('Das Symbol der Liebe... dein Herz schmilzt dahin und du erhälst 1AP.', [], 'items');
                            }
                        }

                        $execute_info_cache['casino'] = $cmg;
                        break;
                }

                if ($ap) {
                    $this->citizen_handler->setAP( $citizen, true, 1, 1 );
                    $execute_info_cache['ap'] += 1;
                }

                if ($terror)
                    $this->citizen_handler->inflictStatus( $citizen, 'error' );
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
                '{minus_ap}'  => -$execute_info_cache['ap'],
                '{well}'      => $execute_info_cache['well'],
                '{item}'      => $this->wrap($execute_info_cache['item']),
                '{target}'    => $execute_info_cache['target'] ? $this->wrap($execute_info_cache['target']) : "-",
                '{item_from}' => $execute_info_cache['item_morph'][0] ? ($this->wrap($execute_info_cache['item_morph'][0])) : "-",
                '{item_to}'   => $execute_info_cache['item_morph'][1] ? ($this->wrap($execute_info_cache['item_morph'][1])) : "-",
                '{target_from}' => $execute_info_cache['item_target_morph'][0] ? ($this->wrap($execute_info_cache['item_target_morph'][0])) : "-",
                '{target_to}'   => $execute_info_cache['item_target_morph'][1] ? ($this->wrap($execute_info_cache['item_target_morph'][1])) : "-",
                '{items_consume}' => $this->wrap_concat($execute_info_cache['items_consume']),
                '{items_spawn}'   => $this->wrap_concat($execute_info_cache['items_spawn']),
                '{bp_spawn}'      => $this->wrap_concat($execute_info_cache['bp_spawn']),
                '{rp_text}'       => $this->wrap( $execute_info_cache['rp_text'] ),
                '{casino}'        => $execute_info_cache['casino'],
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

        switch ( $recipe->getType() ) {
            case Recipe::WorkshopType:case Recipe::ManualInside:
                if ($citizen->getZone()) return ErrorHelper::ErrorActionNotAvailable;
                break;
            case Recipe::ManualOutside:
                if (!$citizen->getZone()) return ErrorHelper::ErrorActionNotAvailable;
                break;
            default: break;
        }

        $remove = [];

        if ($recipe->getType() === Recipe::WorkshopType) {
            $have_saw  = $this->inventory_handler->countSpecificItems( $c_inv, $this->entity_manager->getRepository( ItemPrototype::class )->findOneByName( 'saw_tool_#00' ) ) > 0;
            $have_manu = $this->town_handler->getBuilding($town, 'small_factory_#00', true) !== null;

            $ap = 3 - ($have_saw ? 1 : 0) - ($have_manu ? 1 : 0);
        } else $ap = 0;


        if ( ($citizen->getAp() + $citizen->getBp()) < $ap || $this->citizen_handler->isTired( $citizen ) )
            return ErrorHelper::ErrorNoAP;

        $source_inv = $recipe->getType() === Recipe::WorkshopType ? [ $t_inv ] : [$c_inv, $citizen->getZone() ? $citizen->getZone()->getFloor() : $citizen->getHome()->getChest()];
        $target_inv = $recipe->getType() === Recipe::WorkshopType ? [ $t_inv ] : [$c_inv, $citizen->getZone() ? $citizen->getZone()->getFloor() : $citizen->getHome()->getChest()];

        $s = [];
        foreach ($recipe->getSource()->getEntries() as $entry)
            $s[] = (new ItemRequest($entry->getPrototype()->getName(), $entry->getChance() ));
        $items = $this->inventory_handler->fetchSpecificItems( $source_inv, $s );
        if (empty($items)) return ErrorHelper::ErrorItemsMissing;

        $list = [];
        foreach ($items as $item) {
            $item->getInventory()->removeItem( $item );
            $list[] = $item->getPrototype();
            $remove[] = $item;
        }

        $this->citizen_handler->deductAPBP( $citizen, $ap );

        $new_item = $this->random_generator->pickItemPrototypeFromGroup( $recipe->getResult() );
        $this->inventory_handler->placeItem( $citizen, $this->item_factory->createItem( $new_item ) , $target_inv );

        switch ( $recipe->getType() ) {
            case Recipe::WorkshopType:
                $base = 'Du hast %item_list% in der Werkstatt zu %item% umgewandelt.';
                break;
            case Recipe::ManualOutside:case Recipe::ManualInside:case Recipe::ManualAnywhere:default:
                $base = 'Du hast %item_list% zu %item% umgewandelt.';
                break;
        }

        $message = $this->translator->trans( $base, [
            '%item_list%' => $this->wrap_concat( $list ),
            '%item%' => $this->wrap( $new_item ),
        ], 'game' );

        return self::ErrorNone;
    }
}