<?php


namespace App\Service;


use App\Entity\ActionCounter;
use App\Entity\AffectItemSpawn;
use App\Entity\BuildingPrototype;
use App\Entity\CampingActionPrototype;
use App\Entity\CauseOfDeath;
use App\Entity\ChatSilenceTimer;
use App\Entity\Citizen;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenRole;
use App\Entity\CitizenVote;
use App\Entity\Complaint;
use App\Entity\DigTimer;
use App\Entity\EscapeTimer;
use App\Entity\EscortActionGroup;
use App\Entity\EventActivationMarker;
use App\Entity\FoundRolePlayText;
use App\Entity\HomeActionPrototype;
use App\Entity\HomeIntrusion;
use App\Entity\Item;
use App\Entity\ItemAction;
use App\Entity\ItemProperty;
use App\Entity\ItemPrototype;
use App\Entity\ItemTargetDefinition;
use App\Entity\LogEntryTemplate;
use App\Entity\PictoPrototype;
use App\Entity\Recipe;
use App\Entity\RequireDay;
use App\Entity\RequireLocation;
use App\Entity\Requirement;
use App\Entity\Result;
use App\Entity\RolePlayText;
use App\Entity\RuinZone;
use App\Entity\TownLogEntry;
use App\Entity\Zone;
use App\Structures\EscortItemActionSet;
use App\Structures\ItemRequest;
use App\Structures\MyHordesConf;
use App\Structures\TownConf;
use App\Translation\T;
use DateTime;
use Doctrine\Common\Collections\Criteria;
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
    private $zone_handler;
    private $picto_handler;
    private $assets;
    private $log;
    private $conf;
    private $maze;


    public function __construct(
        EntityManagerInterface $em, StatusFactory $sf, CitizenHandler $ch, InventoryHandler $ih, DeathHandler $dh,
        RandomGenerator $rg, ItemFactory $if, TranslatorInterface $ti, GameFactory $gf, Packages $am, TownHandler $th,
        ZoneHandler $zh, PictoHandler $ph, LogTemplateHandler $lt, ConfMaster $conf, MazeMaker $mm)
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
        $this->zone_handler = $zh;
        $this->picto_handler = $ph;
        $this->log = $lt;
        $this->conf = $conf;
        $this->maze = $mm;
    }

    const ActionValidityNone = 1;
    const ActionValidityHidden = 2;
    const ActionValidityCrossed = 3;
    const ActionValidityAllow = 4;
    const ActionValidityFull = 5;

    protected function evaluate( Citizen $citizen, ?Item $item, $target, ItemAction $action, ?string &$message ): int {

        if ($item && !$item->getPrototype()->getActions()->contains( $action )) return self::ActionValidityNone;
        if ($target && (!$action->getTarget() || !$this->targetDefinitionApplies($target, $action->getTarget())))
            return self::ActionValidityNone;

        $evaluate_info_cache = [
            'missing_items' => [],
        ];

        $messages = [];


        $current_state = self::ActionValidityFull;
        foreach ($action->getRequirements() as $meta_requirement) {
            $last_state = $current_state;

            $this_state = self::ActionValidityNone;
            switch ($meta_requirement->getFailureMode()) {
                case Requirement::MessageOnFail: $this_state = self::ActionValidityAllow; break;
                case Requirement::CrossOnFail: $this_state = self::ActionValidityCrossed; break;
                case Requirement::HideOnFail: $this_state = self::ActionValidityHidden; break;
            }

            if ($config = $meta_requirement->getConf()) {

                $set_val = $this->conf->getTownConfiguration($citizen->getTown())->get($config->getConf(), null);

                $success = false;
                if (!$success && $config->getBoolVal() !== null && $set_val === $config->getBoolVal()) $success = true;

                if (!$success) $current_state = min( $current_state, $this_state );
            }


            if ($status = $meta_requirement->getStatusRequirement()) {
                if ($status->getStatus() !== null && $status->getEnabled() !== null) {
                    $status_is_active = $citizen->getStatus()->contains( $status->getStatus() );
                    if ($status_is_active !== $status->getEnabled()) $current_state = min( $current_state, $this_state );
                }

                if ($status->getProfession() !== null && $status->getEnabled() !== null) {
                    $profession_is_active = $citizen->getProfession()->getId() === $status->getProfession()->getId();
                    if ($profession_is_active !== $status->getEnabled()) $current_state = min( $current_state, $this_state );
                }

                if ($status->getRole() !== null && $status->getEnabled() !== null) {
                    $role_is_active = $citizen->getRoles()->contains( $status->getRole() );
                    if ($role_is_active !== $status->getEnabled()) $current_state = min( $current_state, $this_state );
                }

                if ($status->getBanished() !== null && $citizen->getBanished() !== $status->getBanished()) $current_state = min( $current_state, $this_state );
            }

            if ($home = $meta_requirement->getHome()) {
                if ($home->getUpgrade() === null) {
                    if ($home->getMinLevel() !== null && $citizen->getHome()->getPrototype()->getLevel() < $home->getMinLevel()) $current_state = min( $current_state, $this_state );
                    if ($home->getMaxLevel() !== null && $citizen->getHome()->getPrototype()->getLevel() > $home->getMaxLevel()) $current_state = min( $current_state, $this_state );
                } else {
                    /** @var CitizenHomeUpgrade|null $target_home_upgrade */
                    $target_home_upgrade = $this->entity_manager->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype($citizen->getHome(), $home->getUpgrade());
                    $target_home_upgrade_level = $target_home_upgrade ? $target_home_upgrade->getLevel() : 0;
                    if ($home->getMinLevel() !== null && $target_home_upgrade_level < $home->getMinLevel()) $current_state = min( $current_state, $this_state );
                    if ($home->getMaxLevel() !== null && $target_home_upgrade_level > $home->getMaxLevel()) $current_state = min( $current_state, $this_state );
                }
            }

            if ($zone = $meta_requirement->getZone()) {
                if ($zone->getMaxLevel() !== null && $citizen->getZone() && ( $citizen->getZone()->getImprovementLevel() ) >= $zone->getMaxLevel()) $current_state = min( $current_state, $this_state );
            }

            if ($ap = $meta_requirement->getAp()) {
                $max = $ap->getRelativeMax() ? ($this->citizen_handler->getMaxAP( $citizen ) + $ap->getMax()) : $ap->getMax();
                if ($citizen->getAp() < $ap->getMin() || $citizen->getAp() > $max) $current_state = min( $current_state, $this_state );
            }

            if ($pm = $meta_requirement->getPm()) {
                $max = $pm->getRelativeMax() ? ($this->citizen_handler->getMaxPM( $citizen ) + $pm->getMax()) : $pm->getMax();
                if ($citizen->getPm() < $pm->getMin() || $citizen->getPm() > $max) $current_state = min( $current_state, $this_state );
            }

            if ($cp = $meta_requirement->getCp()) {
                $max = $cp->getRelativeMax() ? ($this->citizen_handler->getMaxBP( $citizen ) + $cp->getMax()) : $cp->getMax();
                if ($citizen->getBp() < $cp->getMin() || $citizen->getBp() > $max) $current_state = min( $current_state, $this_state );
            }

            if ($counter = $meta_requirement->getCounter()) {
                $counter_value = $citizen->getSpecificActionCounterValue( $counter->getType() );
                if ($counter->getMin() !== null && $counter_value < $counter->getMin()) $current_state = min( $current_state, $this_state );
                if ($counter->getMax() !== null && $counter_value > $counter->getMax()) $current_state = min( $current_state, $this_state );
            }

            if ($item_condition = $meta_requirement->getItem()) {
                $item_str = ($is_prop = (bool)$item_condition->getProperty())
                    ? $item_condition->getProperty()->getName()
                    : $item_condition->getPrototype()->getName();

                $source = $citizen->getZone() ? [$citizen->getInventory()] : [$citizen->getInventory(), $citizen->getHome()->getChest()];

                $count = $item_condition->getCount() === null ? 1 : $item_condition->getCount();

                if ($count > 0) {
                    if (empty($this->inventory_handler->fetchSpecificItems( $source,
                        [new ItemRequest($item_str, $item_condition->getCount() ?? 1, false, $item_condition->getAllowPoison() ? null : false, $is_prop)]
                    ))) {
                        if (!$is_prop) for ($i = 0; $i < $item_condition->getCount() ?? 1; $i++) $evaluate_info_cache['missing_items'][] = $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName($item_str);
                        $current_state = min($current_state, $this_state);
                    }
                } else {
                    if (!empty($this->inventory_handler->fetchSpecificItems( $source,
                        [new ItemRequest($item_str, 1, false, $item_condition->getAllowPoison() ? null : false, $is_prop)]
                    ))) {
                        if (!$is_prop) $evaluate_info_cache['missing_items'][] = $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName($item_str);
                        $current_state = min( $current_state, $this_state );
                    }
                }
            }

            if ($location_condition = $meta_requirement->getLocation()) {
                switch ( $location_condition->getLocation() ) {
                    case RequireLocation::LocationInTown:
                        if ( $citizen->getZone() ) $current_state = min( $current_state, $this_state );
                        break;
                    case RequireLocation::LocationOutside:case RequireLocation::LocationOutsideFree:
                    case RequireLocation::LocationOutsideRuin:case RequireLocation::LocationOutsideBuried:
                    case RequireLocation::LocationOutsideOrExploring:
                        if ( !$citizen->getZone() ) $current_state = min( $current_state, $this_state );
                        else {
                            if     ( $location_condition->getLocation() === RequireLocation::LocationOutsideFree   &&  $citizen->getZone()->getPrototype() ) $current_state = min( $current_state, $this_state );
                            elseif ( $location_condition->getLocation() === RequireLocation::LocationOutsideRuin   && !$citizen->getZone()->getPrototype() ) $current_state = min( $current_state, $this_state );
                            elseif ( $location_condition->getLocation() === RequireLocation::LocationOutsideBuried && (!$citizen->getZone()->getPrototype() || !$citizen->getZone()->getBuryCount()) ) $current_state = min( $current_state, $this_state );
                            elseif ( $location_condition->getLocation() !== RequireLocation::LocationOutsideOrExploring && $citizen->activeExplorerStats() ) $current_state = min( $current_state, $this_state );

                            if ($location_condition->getMinDistance() !== null || $location_condition->getMaxDistance() !== null) {
                                $dist = round(sqrt( pow($citizen->getZone()->getX(),2) + pow($citizen->getZone()->getY(),2) ));
                                if ( ($location_condition->getMinDistance() !== null && $dist < $location_condition->getMinDistance() ) || ($location_condition->getMaxDistance() !== null && $dist > $location_condition->getMaxDistance() ) )
                                    $current_state = min( $current_state, $this_state );
                            }
                        }
                        break;
                    case RequireLocation::LocationExploring:
                        if ( !$citizen->activeExplorerStats() ) $current_state = min( $current_state, $this_state );
                        break;
                    default:
                        break;
                }
            }


            if ($zombie_condition = $meta_requirement->getZombies()) {
                $cp = 0;
                $current_zeds = 0;

                if ($citizen->activeExplorerStats())
                    $current_zeds = $this->entity_manager->getRepository(RuinZone::class)->findOneByExplorerStats( $citizen->activeExplorerStats() )->getZombies();
                elseif ($citizen->getZone())
                    $current_zeds = $citizen->getZone()->getZombies();

                if ( $citizen->getZone() && !$citizen->activeExplorerStats() )
                    foreach ( $citizen->getZone()->getCitizens() as $c )
                        $cp += $this->citizen_handler->getCP( $c );

                if ($zombie_condition->getMustBlock() !== null) {

                    if ($zombie_condition->getMustBlock() && $cp >= $current_zeds) $current_state = min( $current_state, $this_state );
                    elseif (!$zombie_condition->getMustBlock() && $cp < $current_zeds) {

                        if (!$zombie_condition->getTempControlAllowed() || !$this->entity_manager->getRepository( EscapeTimer::class )->findActiveByCitizen($citizen))
                            $current_state = min( $current_state, $this_state );

                    }

                }

                if ($zombie_condition->getNumber() > $current_zeds) $current_state = min( $current_state, $this_state );
            }

            if ($building_condition = $meta_requirement->getBuilding()) {
                $town = $citizen->getTown();
                $building = $this->town_handler->getBuilding($town, $building_condition->getBuilding(), false);

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

            if ($day = $meta_requirement->getDay()) {
                /** @var RequireDay $day */
                $town = $citizen->getTown();
                if($day->getMin() > $town->getDay() || $day->getMax() < $town->getDay()) {
                    $current_state = min( $current_state, $this_state );
                }
            }

            if ($meta_requirement->getCustom())
                switch ($meta_requirement->getCustom()) {

                    // Day & Night
                    case 1:
                        if ($this->conf->getTownConfiguration($citizen->getTown())->isNightMode() )
                            $current_state = min($current_state, Requirement::HideOnFail);
                        break;
                    case 2:
                        if (!$this->conf->getTownConfiguration($citizen->getTown())->isNightMode() )
                            $current_state = min($current_state, Requirement::HideOnFail);
                        break;

                    // Event - April Fools
                    case 3:
                        /** @var EventActivationMarker[] $eam */
                        $eam = $this->entity_manager->getRepository(EventActivationMarker::class)->findBy(['citizen' => $citizen, 'active' => true]);
                        $b = false;
                        foreach ($eam as $m) if ($m->getEvent() === 'afools') $b = true;
                        if (!$b) $current_state = min($current_state, Requirement::CrossOnFail);
                        break;

                    // Vote
                    case 18: case 19:
                        if (!$citizen->getProfession()->getHeroic()) {
                            $current_state = min($current_state, Requirement::HideOnFail);
                            break;
                        }

                        if (!($role = $this->entity_manager->getRepository(CitizenRole::class)->findOneBy(['name' => $meta_requirement->getCustom() === 18 ? 'shaman' : 'guide']))) {
                            $current_state = min($current_state, Requirement::HideOnFail);
                            break;
                        }

                        if ($this->entity_manager->getRepository(CitizenVote::class)->findOneByCitizenAndRole($citizen, $role))
                            $current_state = min($current_state, Requirement::CrossOnFail);

                        if (!$this->town_handler->is_vote_needed( $citizen->getTown(), $role ))
                            $current_state = min($current_state, Requirement::HideOnFail);

                        break;
                }


            if ($current_state < $last_state) {
                $thisMessage = $meta_requirement->getFailureText() ? $this->translator->trans( $meta_requirement->getFailureText(), [
                    '{items_required}' => $this->wrap_concat($evaluate_info_cache['missing_items']),
                    '{hr}'             => "<hr />",
                ], 'items' ) : null;

                if ($thisMessage) $messages[] = $thisMessage;
            }

        }

        $message = !empty($messages) ? implode('<hr />', $messages) : null;

        return $current_state;

    }

    /**
     * @param ItemPrototype[] $list
     * @return array
     */
    private function reformat_prototype_list(array $list): array {

        $cache = [];
        foreach ( $list as $entry )
            if (!isset( $cache[$entry->getId()] )) $cache[$entry->getId()] = [1,$entry];
            else $cache[$entry->getId()][0]++;

        return $cache;
    }

    /**
     * @param Citizen $citizen
     * @param Item $item
     * @param ItemAction[] $available
     * @param ItemAction[] $crossed
     * @param array|null $messages
     */
    public function getAvailableItemActions(Citizen $citizen, Item $item, ?array &$available, ?array &$crossed, ?array &$messages = null, bool $ignore_broken_flag = false ) {

        $available = $crossed = $messages = [];
        if ($item->getBroken() && !$ignore_broken_flag) return;

        $is_at_00 = $citizen->getZone() && $citizen->getZone()->isTownZone();
        foreach ($item->getPrototype()->getActions() as $action) {
            if ($is_at_00 && !$action->getAllowedAtGate()) continue;
            $mode = $this->evaluate( $citizen, $item, null, $action, $tx );
            if ($mode >= self::ActionValidityAllow) $available[] = $action;
            else if ($mode >= self::ActionValidityCrossed) $crossed[] = $action;
            if (!empty($tx)) $messages[$action->getId()] = $tx;
        }
    }

    /**
     * @param Citizen $citizen
     * @return EscortItemActionSet[]
     */
    public function getAvailableItemEscortActions(Citizen $citizen ): array {

        $is_at_00 = $citizen->getZone() && $citizen->getZone()->isTownZone();

        $list = [];
        /** @var EscortActionGroup[] $escort_actions */
        $escort_actions = $this->entity_manager->getRepository(EscortActionGroup::class)->findAll();
        foreach ($escort_actions as $escort_action) {

            $struct = new EscortItemActionSet( $escort_action );

            foreach ($citizen->getInventory()->getItems() as $item)
                foreach ($item->getPrototype()->getActions() as $action)
                    if ($escort_action->getActions()->contains($action)) {
                        if ($is_at_00 && !$action->getAllowedAtGate()) continue;
                        $mode = $this->evaluate( $citizen, $item, null, $action, $tx );
                        if ($mode >= self::ActionValidityAllow) $struct->addAction( $action, $item, true );
                        else if ($mode >= self::ActionValidityCrossed) $struct->addAction( $action, $item, false );
                    }

            if ($struct->hasActions()) $list[] = $struct;
        }

        return $list;
    }

    /**
     * @param Citizen $citizen
     * @param ItemAction[] $available
     * @param ItemAction[] $crossed
     */
    public function getAvailableCampingActions(Citizen $citizen, ?array &$available, ?array &$crossed ) {

      $available = $crossed = [];
      $campingActions = $this->entity_manager->getRepository(CampingActionPrototype::class)->findAll();

      foreach ($campingActions as $action) {
        $mode = $this->evaluate( $citizen, null, null, $action->getAction(), $tx );
        if ($mode >= self::ActionValidityAllow) $available[] = $action;
        else if ($mode >= self::ActionValidityCrossed) $crossed[] = $action;
      }

    }

    /**
     * @param Citizen $citizen
     * @param ItemAction[] $available
     * @param ItemAction[] $crossed
     */
    public function getAvailableHomeActions(Citizen $citizen, ?array &$available, ?array &$crossed ) {

        $available = $crossed = [];
        $home_actions = $this->entity_manager->getRepository(HomeActionPrototype::class)->findAll();

        foreach ($home_actions as $action) {
            $mode = $this->evaluate( $citizen, null, null, $action->getAction(), $tx );
            if ($mode >= self::ActionValidityAllow) $available[] = $action;
            else if ($mode >= self::ActionValidityCrossed) $crossed[] = $action;
        }

    }

    /**
     * @param Citizen $citizen
     * @param ItemAction[] $available
     * @param ItemAction[] $crossed
     * @param ItemAction[] $used
     */
    public function getAvailableIHeroicActions(Citizen $citizen, ?array &$available, ?array &$crossed, ?array &$used ) {
        $available = $crossed = $used = [];

        if (!$citizen->getProfession()->getHeroic()) return;
        $is_at_00 = $citizen->getZone() && $citizen->getZone()->isTownZone();

        foreach ($citizen->getHeroicActions() as $heroic) {
            if ($is_at_00 && !$heroic->getAction()->getAllowedAtGate()) continue;
            $mode = $this->evaluate( $citizen, null, null, $heroic->getAction(), $tx );
            if ($mode >= self::ActionValidityAllow) $available[] = $heroic;
            else if ($mode >= self::ActionValidityCrossed) $crossed[] = $heroic;
        }

        foreach ($citizen->getUsedHeroicActions() as $used_heroic) {
            if ($citizen->getHeroicActions()->contains($used_heroic) || ($is_at_00 && !$used_heroic->getAction()->getAllowedAtGate())) continue;
            $mode = $this->evaluate( $citizen, null, null, $used_heroic->getAction(), $tx );
            if ($mode >= self::ActionValidityCrossed) $used[] = $used_heroic;
        }

    }

    /**
     * @param Citizen $citizen
     * @param ItemAction[] $available
     * @param ItemAction[] $crossed
     */
    public function getAvailableISpecialActions(Citizen $citizen, ?array &$available, ?array &$crossed ) {
        $available = $crossed = [];

        foreach ($citizen->getSpecialActions() as $special) {
            $mode = $this->evaluate( $citizen, null, null, $special->getAction(), $tx );
            if ($mode >= self::ActionValidityAllow) $available[] = $special;
            else if ($mode >= self::ActionValidityCrossed) $crossed[] = $special;
        }

    }

    /**
     * @param Item|ItemPrototype|Citizen $target
     * @param ItemTargetDefinition $definition
     * @return bool
     */
    public function targetDefinitionApplies($target, ItemTargetDefinition $definition): bool {
        switch ($definition->getSpawner()) {
            case ItemTargetDefinition::ItemSelectionType:case ItemTargetDefinition::ItemSelectionTypePoison:
                if (!is_a( $target, Item::class )) return false;
                if ($definition->getHeavy() !== null && $target->getPrototype()->getHeavy() !== $definition->getHeavy()) return false;
                if ($definition->getBroken() !== null && $target->getBroken() !== $definition->getBroken()) return false;
                if ($definition->getPoison() !== null && $target->getPoison() !== $definition->getPoison()) return false;
                if ($definition->getPrototype() !== null && $target->getPrototype()->getId() !== $definition->getPrototype()->getId()) return false;
                if ($definition->getTag() !== null && !$target->getPrototype()->getProperties()->contains($definition->getTag())) return false;
                break;
            case ItemTargetDefinition::ItemTypeSelectionType:
                if (!is_a( $target, ItemPrototype::class )) return false;
                if ($definition->getPrototype() && $target->getId() !== $definition->getPrototype()->getId()) return false;
                if ($definition->getTag() && !$target->getProperties()->contains( $definition->getTag() ) ) return false;
                break;
            case ItemTargetDefinition::ItemHeroicRescueType:
                if (!is_a( $target, Citizen::class )) return false;
                if (!$target->getZone() || !$target->getAlive()) return false;
                if ( round( sqrt(pow($target->getZone()->getX(),2 ) + pow($target->getZone()->getY(),2 )) ) > 2 ) return false;
                break;
            case ItemTargetDefinition::ItemCitizenType: case ItemTargetDefinition::ItemCitizenVoteType: case ItemTargetDefinition::ItemCitizenOnZoneType: case ItemTargetDefinition::ItemCitizenOnZoneSBType:
                if (!is_a( $target, Citizen::class )) return false;
                if (!$target->getAlive()) return false;
                break;
            default: return false;
        }

        return true;
    }

    /**
     * @param ItemPrototype|BuildingPrototype|Citizen|string $o
     * @param int $c
     * @return string
     */
    private function wrap($o, int $c=1) :string {
        $i = null;
        if (is_a($o, ItemPrototype::class)) {
            $s = $this->translator->trans($o->getLabel(), [], 'items');
            $i = 'build/images/item/item_' . $o->getIcon() . '.gif';
        } else if (is_a($o, BuildingPrototype::class)) {
            $s =  $this->translator->trans($o->getLabel(), [], 'buildings');
            $i = 'build/images/building/' . $o->getIcon() . '.gif';
        } else if (is_a($o, Citizen::class)) {
            $s =  $o->getName();
            $i = 'build/images/professions/' . $o->getProfession()->getIcon() . '.gif';
        }
        else if (is_string($o)) $s = $o;
        else if (is_null($o)) $s = 'NULL';
        else $s = '_UNKNOWN_';

        if (!empty($i)) $i = $this->assets->getUrl( $i );
        return '<span>' . ($c > 1 ? "$c x " : '') . ($i ? "<img alt='' src='$i' />" : '') . $s .  '</span>';
    }

    private function wrap_concat(array $c): string {
        return implode(', ', array_map(function(array $e): string {
            return $this->wrap( $e[1], $e[0] );
        }, $this->reformat_prototype_list($c)));
    }

    private function wrap_concat_hierarchy(array $c): string {
        return implode(' > ', array_map(function(array $e): string {
            return $this->wrap( $e[1], $e[0] );
        }, $this->reformat_prototype_list($c)));
    }

    const ErrorNone = 0;
    const ErrorActionUnregistered = ErrorHelper::BaseActionErrors + 1;
    const ErrorActionForbidden    = ErrorHelper::BaseActionErrors + 2;
    const ErrorActionImpossible   = ErrorHelper::BaseActionErrors + 3;

    /**
     * @param Citizen $citizen
     * @param Item|null $item
     * @param Item|ItemPrototype|Citizen|null $target
     * @param ItemAction $action
     * @param string|null $message
     * @param array|null $remove
     * @param bool $force Do not check if the action is valid
     * @return int
     */
    public function execute( Citizen &$citizen, ?Item &$item, &$target, ItemAction $action, ?string &$message, ?array &$remove, bool $force = false, bool $escort_mode = false ): int {

        $remove = [];
        $tags = [];

        $kill_by_poison = $item && $item->getPoison() && ($action->getPoisonHandler() & ItemAction::PoisonHandlerConsume);
        $spread_poison = false;

        $town_conf = $this->conf->getTownConfiguration( $citizen->getTown() );

        if (!$force) {
            $mode = $this->evaluate( $citizen, $item, $target, $action, $tx );
            if ($mode <= self::ActionValidityNone)    return self::ErrorActionUnregistered;
            if ($mode <= self::ActionValidityCrossed) return self::ErrorActionImpossible;
            if ($mode <= self::ActionValidityAllow) {
                $message = $tx;
                return self::ErrorActionForbidden;
            }
            if ($mode != self::ActionValidityFull) return self::ErrorActionUnregistered;
        }

        $target_item_prototype = null;
        if ($target && is_a( $target, Item::class )) $target_item_prototype = $target->getPrototype();
        if ($target && is_a( $target, ItemPrototype::class )) $target_item_prototype = $target;

        $execute_info_cache = [
            'ap' => 0,
            'pm' => 0,
            'cp' => 0,
            'item'   => $item ? $item->getPrototype() : null,
            'target' => $target_item_prototype,
            'source_inv' => $item ? $item->getInventory() : null,
            'user' => $citizen,
            'citizen' => is_a($target, Citizen::class) ? $target : null,
            'item_morph' => [ null, null ],
            'item_target_morph' => [ null, null ],
            'items_consume' => [],
            'items_spawn' => [],
            'item_tool' => [],
            'tamer_dog' => LogTemplateHandler::generateDogName($citizen->getId(), $this->translator),
            'bp_spawn' => [],
            'bp_parent' => [],
            'rp_text' => '',
            'casino' => '',
            'zone' => null,
            'well' => 0,
            'zombies' => 0,
            'message' => [
                $escort_mode ? $action->getEscortMessage() : $action->getMessage(),
            ],
            'kills' => 0,
            'bury_count' => 0,
            'items_count' => 0,
            'size' => 0,
            'home_storage' => 0,
            'home_defense' => 0
        ];

        if ($citizen->activeExplorerStats())
            $ruinZone = $this->entity_manager->getRepository(RuinZone::class)->findOneByExplorerStats($citizen->activeExplorerStats());
        else $ruinZone = null;

        $floor_inventory = null;
        if (!$citizen->getZone())
            $floor_inventory = $citizen->getHome()->getChest();
        elseif ($citizen->getZone()->getX() === 0 && $citizen->getZone()->getY() === 0)
            $floor_inventory = $citizen->getTown()->getBank();
        elseif (!$ruinZone)
            $floor_inventory = $citizen->getZone()->getFloor();
        /*elseif ($citizen->activeExplorerStats()->getInRoom())
            $floor_inventory = $ruinZone->getRoomFloor();*/
        else
            $floor_inventory = $ruinZone->getFloor();

        $execute_result = function(Result $result) use ($citizen, &$item, &$target, &$action, &$message, &$remove, &$execute_result, &$execute_info_cache, &$tags, &$kill_by_poison, &$spread_poison, $town_conf, &$floor_inventory, &$ruinZone, $escort_mode) {
            /** @var Citizen $citizen */
            if ($status = $result->getStatus()) {
                if ($status->getResetThirstCounter())
                    $citizen->setWalkingDistance(0);

                if ($status->getCounter() !== null)
                    $citizen->getSpecificActionCounter( $status->getCounter() )->increment();

                if ($status->getCitizenHunger()) {
                    $ghoul_mode = $this->conf->getTownConfiguration($citizen->getTown())->get(TownConf::CONF_FEATURE_GHOUL_MODE, 'normal');
                    if ($status->getForced() || !in_array($ghoul_mode, ['bloodthirst','airbnb']))
                        $citizen->setGhulHunger( max(0,$citizen->getGhulHunger() + $status->getCitizenHunger()) );
                }


                if ($status->getRole() !== null && $status->getRoleAdd() !== null) {
                    if ($status->getRoleAdd()) {
                        if ($this->citizen_handler->addRole( $citizen, $status->getRole() )) {
                            $tags[] = 'role-up';
                            $tags[] = "role-up-{$status->getRole()->getName()}";
                        }
                    } else {
                        if ($this->citizen_handler->removeRole( $citizen, $status->getRole() )) {
                            $tags[] = 'role-down';
                            $tags[] = "role-down-{$status->getRole()->getName()}";
                        }
                    }
                }

                if ($status->getInitial() && $status->getResult()) {
                    if ($citizen->getStatus()->contains( $status->getInitial() )) {
                        $this->citizen_handler->removeStatus( $citizen, $status->getInitial() );
                        $this->citizen_handler->inflictStatus( $citizen, $status->getResult() );
                        $tags[] = 'stat-change';
                        $tags[] = "stat-change-{$status->getInitial()->getName()}-{$status->getResult()->getName()}";
                    }
                }
                elseif ($status->getInitial()) {
                    if ($citizen->getStatus()->contains( $status->getInitial() ) && $this->citizen_handler->removeStatus( $citizen, $status->getInitial() )) {
                        $tags[] = 'stat-down';
                        $tags[] = "stat-down-{$status->getInitial()->getName()}";
                    }
                }
                elseif ($status->getResult()) {
                    $inflict = true;
                    if($status->getResult()->getName() == "infect" && $this->citizen_handler->hasStatusEffect($citizen, "tg_infect_wtns")) {
                        $inflict = $this->random_generator->chance(0.5);
                        $this->citizen_handler->removeStatus( $citizen, 'tg_infect_wtns' );
                        if($inflict){
                            $execute_info_cache['message'][] = T::__("Ein Opfer der Großen Seuche zu sein hat dir diesmal nicht viel gebracht... und es sieht nicht gut aus...", "items");
                        } else {
                            $execute_info_cache['message'][] = T::__("Da hast du wohl Glück gehabt... Als Opfer der Großen Seuche bist du diesmal um eine unangenehme Infektion herumgekommen.", "items");
                        }
                    }
                    if ($inflict){
                        if (!$citizen->getStatus()->contains( $status->getResult() ) && $this->citizen_handler->inflictStatus($citizen, $status->getResult())) {
                            $tags[] = 'stat-up';
                            $tags[] = "stat-up-{$status->getResult()->getName()}";
                        }
                    }
                }
            }

            if ($ap = $result->getAp()) {
                $old_ap = $citizen->getAp();
                if ($ap->getMax()) {
                    $to = $this->citizen_handler->getMaxAP($citizen) + $ap->getAp();
                    $this->citizen_handler->setAP( $citizen, false, max( $old_ap, $to ), null );
                } else $this->citizen_handler->setAP( $citizen, true, $ap->getAp(), $ap->getAp() < 0 ? null :$ap->getBonus() );

                $execute_info_cache['ap'] += ( $citizen->getAp() - $old_ap );
                $tags[] = 'ap-up';
            }

            if ($pm = $result->getPm()) {
                $old_pm = $citizen->getPm();
                if ($pm->getMax()) {
                    $to = $this->citizen_handler->getMaxPM($citizen) + $pm->getPm();
                    $this->citizen_handler->setPM( $citizen, false, max( $old_pm, $to ) );
                } else $this->citizen_handler->setPM( $citizen, true, $pm->getPm() );

                $execute_info_cache['pm'] += ( $citizen->getPm() - $old_pm );
            }

            if ($cp = $result->getCp()) {
                $old_cp = $citizen->getBp();
                if ($cp->getMax()) {
                    $to = $this->citizen_handler->getMaxBP($citizen) + $cp->getCp();
                    $this->citizen_handler->setBP( $citizen, false, max( $old_cp, $to ) );
                } else $this->citizen_handler->setBP( $citizen, true, $cp->getCp() );

                $execute_info_cache['cp'] += ( $citizen->getBp() - $old_cp );
            }

            if ($death = $result->getDeath()) {
                $this->death_handler->kill( $citizen, $death->getCause(), $r );
                $this->entity_manager->persist( $this->log->citizenDeath( $citizen ) );
                foreach ($r as $r_entry) $remove[] = $r_entry;
            }

            if ($bp = $result->getBlueprint()) {
                $blocked = $this->conf->getTownConfiguration($citizen->getTown())->get(TownConf::CONF_DISABLED_BUILDINGS);
                $possible = $this->entity_manager->getRepository(BuildingPrototype::class)->findProspectivePrototypes( $citizen->getTown() );
                $filtered = array_filter( $possible, function(BuildingPrototype $proto) use ($bp,$blocked) {
                    if (in_array($proto->getName(), $blocked)) return false;
                    elseif ($bp->getType() !== null && $bp->getType() === $proto->getBlueprint() ) return true;
                    else return $bp->getList()->contains( $proto );
                } );

                if (!empty($filtered)) {
                    /** @var BuildingPrototype $pick */
                    $pick = $this->random_generator->pick( $filtered );
                    $town = $citizen->getTown();
                    if ($this->town_handler->addBuilding( $town, $pick )) {
                        $tags[] = 'bp_ok';
                        $execute_info_cache['bp_spawn'][] = $pick;
                        $this->entity_manager->persist( $this->log->constructionsNewSite( $citizen, $pick ) );
                        if($pick->getParent()){
                            $tags[] = 'bp_parent';
                            $parent = $pick->getParent();
                            do {
                                $execute_info_cache['bp_parent'][] = $parent;
                                $parent = $parent->getParent();
                            } while($parent !== null);
                            $execute_info_cache['bp_parent'] = array_reverse($execute_info_cache['bp_parent']);
                        }
                    }

                } else $tags[] = 'bp_fail';
            }

            if ($item && $item_result = $result->getItem()) {
                if ($execute_info_cache['item_morph'][0] === null) $execute_info_cache['item_morph'][0] = $item->getPrototype();
                if ($item_result->getConsume()) {
                    $this->inventory_handler->forceRemoveItem( $item );
                    $execute_info_cache['items_consume'][] = $item->getPrototype();
                    $tags[] = 'consumed';
                } else {
                    if ($item_result->getMorph()) {
                        $item->setPrototype( $execute_info_cache['item_morph'][1] = $item_result->getMorph() );
                        $tags[] = 'morphed';
                    }

                    if ($item_result->getBreak()  !== null) $item->setBroken( $item_result->getBreak() );
                    if ($item_result->getPoison() !== null) $item->setPoison( $item_result->getPoison() );
                }

                $can_opener_prop = $this->entity_manager->getRepository(ItemProperty::class )->findOneBy(['name' => 'can_opener']);
                $box_opener_prop = $this->entity_manager->getRepository(ItemProperty::class )->findOneBy(['name' => 'box_opener']);
                $parcel_opener_prop = $this->entity_manager->getRepository(ItemProperty::class )->findOneBy(['name' => 'parcel_opener']);
                $parcel_opener_home_prop = $this->entity_manager->getRepository(ItemProperty::class )->findOneBy(['name' => 'parcel_opener_h']);

                foreach ($action->getRequirements() as $req) {
                    if (!$req->getItem() || $req->getItem()->getCount() <= 0) continue;

                    if ($req->getItem() && $req->getItem()->getProperty() == $can_opener_prop) {
                        $execute_info_cache['item_tool'] = $this->inventory_handler->fetchSpecificItems($citizen->getZone() ? $citizen->getInventory() : [$citizen->getInventory(),$citizen->getHome()->getChest()], [new ItemRequest('can_opener', 1, false, null, true)])[0]->getPrototype();
                        break;
                    }

                    if ($req->getItem() && $req->getItem()->getProperty() == $box_opener_prop) {
                        $execute_info_cache['item_tool'] = $this->inventory_handler->fetchSpecificItems($citizen->getZone() ? $citizen->getInventory() : [$citizen->getInventory(),$citizen->getHome()->getChest()], [new ItemRequest('box_opener', 1, false, null, true)])[0]->getPrototype();
                        break;
                    }

                    if ($req->getItem() && $req->getItem()->getProperty() == $parcel_opener_prop) {
                        $execute_info_cache['item_tool'] = $this->inventory_handler->fetchSpecificItems($citizen->getZone() ? $citizen->getInventory() : [$citizen->getInventory(),$citizen->getHome()->getChest()], [new ItemRequest('parcel_opener', 1, false, null, true)])[0]->getPrototype();
                        break;
                    }

                    if ($req->getItem() && $req->getItem()->getProperty() == $parcel_opener_home_prop) {
                        $execute_info_cache['item_tool'] = $this->inventory_handler->fetchSpecificItems($citizen->getZone() ? $citizen->getInventory() : [$citizen->getInventory(),$citizen->getHome()->getChest()], [new ItemRequest('parcel_opener_h', 1, false, null, true)])[0]->getPrototype();
                        break;
                    }
                }
            }

            if ($target && $target_result = $result->getTarget()) {
                if (is_a($target, Item::class)) {
                    if ($execute_info_cache['item_target_morph'][0] === null) $execute_info_cache['item_target_morph'][0] = $target->getPrototype();
                    if ($target_result->getConsume()) {
                        $this->inventory_handler->forceRemoveItem( $target );
                        $execute_info_cache['items_consume'][] = $target->getPrototype();
                        $tags[] = 'consumed';
                    } else {
                        if ($target_result->getMorph())
                            $target->setPrototype( $execute_info_cache['item_target_morph'][1] = $target_result->getMorph() );
                        if ($target_result->getBreak()  !== null) $target->setBroken( $target_result->getBreak() );
                        if ($target_result->getPoison() !== null) $target->setPoison( $target_result->getPoison() );
                    }
                } elseif (is_a($target, ItemPrototype::class)) {
                    if ($i = $this->inventory_handler->placeItem( $citizen, $this->item_factory->createItem( $target ), [ $citizen->getInventory(), $floor_inventory, $citizen->getZone() ? null : $citizen->getTown()->getBank() ])) {
                        if ($i !== $citizen->getInventory())
                            $execute_info_cache['message'][] = $this->translator->trans('Der Gegenstand, den du soeben gefunden hast, passt nicht in deinen Rucksack, darum bleibt er erstmal am Boden...', [], 'game');
                        $execute_info_cache['items_spawn'][] = $target;
                        if(!$citizen->getZone())
                            $tags[] = "inside";
                        else
                            $tags[] = "outside";
                    }
                }
            }

            if ($item_spawn = $result->getSpawn()) {
                for ($i = 0; $i < $item_spawn->getCount(); $i++ ) {
                    $proto = null;
                    if ($p = $item_spawn->getPrototype())
                        $proto = $p;
                    elseif ($g = $item_spawn->getItemGroup())
                        $proto = $this->random_generator->pickItemPrototypeFromGroup( $g );

                    if ($proto) $tags[] = 'spawned';

                    $force = false;

                    switch ($item_spawn->getSpawnTarget()) {
                        case AffectItemSpawn::DropTargetFloor:
                            $target = [ $floor_inventory, $citizen->getZone() ? null : $citizen->getTown()->getBank() ];
                            $force = true;
                            break;
                        case AffectItemSpawn::DropTargetRucksack:
                            $target = [ $citizen->getInventory() ];
                            $force = true;
                            break;
                        case AffectItemSpawn::DropTargetDefault:
                        default:
                            $target = [$execute_info_cache['source_inv'] ?? null, $citizen->getInventory(), $floor_inventory, $citizen->getZone() ? null : $citizen->getTown()->getBank() ];
                            break;
                    }

                    if ($proto) {
                        if ($this->inventory_handler->placeItem( $citizen, $this->item_factory->createItem( $proto ), $target, $force)) {
                            $execute_info_cache['items_spawn'][] = $proto;
                            if(!$citizen->getZone())
                                $tags[] = "inside";
                            else
                                $tags[] = "outside";
                        } else {
                            // TODO: Get the actual error (not enough place, too many heavy items, etc...)
                            return self::ErrorActionImpossible;
                        }
                    }
                }
            }

            if ($item_consume = $result->getConsume()) {
                $source = $citizen->getZone() ? [$citizen->getInventory()] : [$citizen->getInventory(), $citizen->getHome()->getChest()];
                $items = $this->inventory_handler->fetchSpecificItems( $source,
                    [new ItemRequest( $item_consume->getPrototype()->getName(), $item_consume->getCount() )] );

                foreach ($items as $consume_item) {

                    if ($consume_item->getPoison()) {
                        if ($action->getPoisonHandler() & ItemAction::PoisonHandlerConsume) $kill_by_poison = true;
                        if ($action->getPoisonHandler() & ItemAction::PoisonHandlerTransgress) $spread_poison = $town_conf->get( TownConf::CONF_MODIFIER_POISON_TRANS, false );
                    }

                    $this->inventory_handler->forceRemoveItem( $consume_item );
                    $execute_info_cache['items_consume'][] = $consume_item->getPrototype();
                    $tags[] = "item-consumed";
                }
            }

            if ($zombie_kill = $result->getZombies()) {
                if ($citizen->getZone() && !$citizen->activeExplorerStats()) {
                    $kills = min($citizen->getZone()->getZombies(), mt_rand( $zombie_kill->getMin(), $zombie_kill->getMax() ));
                    if ($kills > 0) {
                        $citizen->getZone()->setZombies( $citizen->getZone()->getZombies() - $kills );
                        $execute_info_cache['kills'] = $kills;
                        $this->entity_manager->persist( $this->log->zombieKill( $citizen, $execute_info_cache['item'], $kills, $action->getName() ) );
                        $this->picto_handler->give_picto($citizen, 'r_killz_#00', $kills);
                        $tags[] = 'kills';
                        if($citizen->getZone()->getZombies() <= 0)
                            $tags[] = 'kill-latest';

                    }
                }

                if ($citizen->activeExplorerStats()) {
                    $kills = min($ruinZone->getZombies(), mt_rand( $zombie_kill->getMin(), $zombie_kill->getMax() ));
                    if ($kills > 0) {
                        $ruinZone->setZombies( $ruinZone->getZombies() - $kills );
                        $ruinZone->setKilledZombies( $ruinZone->getKilledZombies() + $kills );
                        $execute_info_cache['kills'] = $kills;
                        $this->picto_handler->give_picto($citizen, 'r_killz_#00', $kills);
                        $this->entity_manager->persist( $this->log->zombieKill( $citizen, $execute_info_cache['item'], $kills, $action->getName() ) );
                        $tags[] = 'kills';
                        if($ruinZone->getZombies() <= 0)
                            $tags[] = 'kill-latest';
                    }
                }
            }

            if ($home_set = $result->getHome()) {
                $citizen->getHome()->setAdditionalStorage( $citizen->getHome()->getAdditionalStorage() + $home_set->getAdditionalStorage() );
                $citizen->getHome()->setAdditionalDefense( $citizen->getHome()->getAdditionalDefense() + $home_set->getAdditionalDefense() );
                $execute_info_cache["home_storage"] = $home_set->getAdditionalStorage();
                $execute_info_cache["home_defense"] = $home_set->getAdditionalDefense();
            }

            if ($town_set = $result->getTown()){
                $citizen->getTown()->setSoulDefense($citizen->getTown()->getSoulDefense() + $town_set->getAdditionalDefense());
            }

            if ($zoneEffect = $result->getZone()) {
                $base_zone = $citizen->getZone();

                if ($zoneEffect->getUncoverZones()) {
                    $base_zone_x = 0;
                    $base_zone_y = 0;
                    if ($base_zone) {
                        $base_zone_x = $base_zone->getX();
                        $base_zone_y = $base_zone->getY();
                    }
                    $upgraded_map = $this->town_handler->getBuilding($citizen->getTown(),'item_electro_#00', true);
                    for ($x = -1; $x <= 1; $x++)
                        for ($y = -1; $y <= 1; $y++) {
                            /** @var Zone $zone */
                            $zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition($citizen->getTown(), $base_zone_x + $x, $base_zone_y + $y);
                            if ($zone) {
                                $zone->setDiscoveryStatus( Zone::DiscoveryStateCurrent );
                                if ($upgraded_map) $zone->setZombieStatus( Zone::ZombieStateExact );
                                else $zone->setZombieStatus( max( $zone->getZombieStatus(), Zone::ZombieStateEstimate ) );
                            }
                        }
                }

                if ($base_zone) {
                    if ($zoneEffect->getUncoverRuin()) {
                        // If we get 4 the first time, roll again to reduce the chances for 4
                        $count = min(mt_rand(2,4), $base_zone->getBuryCount());
                        if ($count === 4) $count = min(mt_rand(2,4), $base_zone->getBuryCount());

                        $execute_info_cache['bury_count'] = $count;
                        $base_zone->setBuryCount( max(0, $base_zone->getBuryCount() - $count ));
                        if ($base_zone->getPrototype())
                            $this->entity_manager->persist( $this->log->outsideUncover( $citizen, $count, $item ? $item->getPrototype() : null ) );
                        if ($base_zone->getBuryCount() == 0)
                            $this->entity_manager->persist( $this->log->outsideUncoverComplete( $citizen ) );
                    }

                    if ($zoneEffect->getEscape() !== null && $zoneEffect->getEscape() > 0) {
                        $tags[] = 'any-escape';
                        if ($ruinZone) {
                            $z = $ruinZone->getZombies();
                            $ruinZone->setZombies( 0 );
                            if ($z > 0) $this->maze->populateMaze( $ruinZone->getZone(), $z, false, false, [$ruinZone] );
                            $execute_info_cache['zombies'] += $z;
                            $tags[] = 'reverse-escape';
                        } else {
                            $base_zone->addEscapeTimer((new EscapeTimer())->setTime(new DateTime("+{$zoneEffect->getEscape()}sec")));
                            if ($execute_info_cache['item'])
                                $this->entity_manager->persist( $this->log->zoneEscapeItemUsed( $citizen, $execute_info_cache['item'], $zoneEffect->getEscape() ) );
                            $tags[] = 'escape';
                        }
                    }

                    if ($zoneEffect->getImproveLevel()) {
                        $base_zone->setImprovementLevel( $base_zone->getImprovementLevel() + $zoneEffect->getImproveLevel() );
                    }

                    if ($zoneEffect->getChatSilence() !== null && $zoneEffect->getChatSilence() > 0) {
                        $base_zone->addChatSilenceTimer((new ChatSilenceTimer())->setTime(new DateTime("+{$zoneEffect->getChatSilence()}sec"))->setCitizen($citizen));
                        $limit = new DateTime("-3min");

                        foreach ($this->entity_manager->getRepository(TownLogEntry::class)->findByFilter( $base_zone->getTown(), null, null, $base_zone ) as $entry) {
                            /** @var TownLogEntry $entry */
                            if ($entry->getLogEntryTemplate() !== null) {
                                $suffix = '';
                                switch ($entry->getLogEntryTemplate()->getClass()) {
                                    case LogEntryTemplate::ClassWarning:
                                        $suffix = "Warning";
                                        break;
                                    case LogEntryTemplate::ClassCritical:
                                        $suffix = "Critical";
                                        break;
                                    case LogEntryTemplate::ClassChat:
                                        $suffix = "Chat";
                                        break;
                                    case LogEntryTemplate::ClassDanger:
                                        $suffix = "Danger";
                                        break;
                                }

                                $template = $this->entity_manager->getRepository(LogEntryTemplate::class)->findOneBy(['name' => 'smokeBombReplacement' . $suffix]);
                                if ($entry->getTimestamp() > $limit) {
                                    $entry->setLogEntryTemplate($template);
                                    $this->entity_manager->persist($entry);
                                }
                            }
                        }
                        $this->entity_manager->persist($this->log->smokeBombUsage($base_zone));
                    }
                }
            }

            if ($well = $result->getWell()) {

                $add = mt_rand( $well->getFillMin(), $well->getFillMax() );
                $citizen->getTown()->setWell( $citizen->getTown()->getWell() + $add );
                $execute_info_cache['well'] += $add;

                if ($add > 0)
                    $this->entity_manager->persist( $this->log->wellAdd( $citizen, $execute_info_cache['item'], $add) );
            }

            if ($result->getRolePlayText()) {
                /** @var RolePlayText|null $text */
                $text = $this->random_generator->pick( ($citizen->getTown()->getLanguage() === 'multi' || $citizen->getTown()->getLanguage() === null) ? $this->entity_manager->getRepository(RolePlayText::class)->findAll() : $this->entity_manager->getRepository(RolePlayText::class)->findAllByLang($citizen->getTown()->getLanguage() ));
                $alreadyfound = !$text || $this->entity_manager->getRepository(FoundRolePlayText::class)->findByUserAndText($citizen->getUser(), $text);
                $execute_info_cache['rp_text'] = $text->getTitle();
                if ($alreadyfound)
                    $tags[] = 'rp_fail';
                elseif ($text) {
                    $tags[] = 'rp_ok';
                    $foundrp = new FoundRolePlayText();
                    $foundrp->setUser($citizen->getUser())->setText($text)->setNew(true);
                    $citizen->getUser()->getFoundTexts()->add($foundrp);

                    $this->entity_manager->persist($foundrp);
                    $pictoPrototype = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => 'r_rp_#00']);
                    $this->picto_handler->give_picto($citizen, $pictoPrototype);
                }
            }

            if($picto = $result->getPicto()){
                $this->picto_handler->give_picto($citizen, $picto->getPrototype());
            }

            if($picto = $result->getGlobalPicto()){
                $citizens = $citizen->getTown()->getCitizens();
                foreach($citizens as $curCitizen) {
                    /** @var Citizen $curCitizen */
                    if(!$curCitizen->getAlive()) continue;
                    $this->picto_handler->give_picto($curCitizen, $picto->getPrototype());
                }
            }

            if ($result->getCustom())
            {
                $ap     = false;
                $terror = false;
                switch ($result->getCustom()) {
                    // Dice
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
                        } else if ( $dice[0] === 1 && $dice[1] === 2 && $dice[2] === 4 ) {
                            $ap = true;
                            $cmg .= ' ' . $this->translator->trans('Wow, du hast beim ersten Versuch eine 4-2-1 geworfen. Das hat so viel Spaß gemacht, dass du 1AP gewinnst!', [], 'items');
                        } else if ( $dice[0] === $dice[1] || $dice[1] === $dice[2] )
                            $cmg .= ' ' . $this->translator->trans('Nicht schlecht, du hast einen Pasch geworfen.', [], 'items');
                        else $cmg .= ' ' . $this->translator->trans('Was für ein Spaß!', [], 'items');

                        $execute_info_cache['casino'] = $cmg;
                        break;
                    // Cards
                    case 2:
                        $card = mt_rand(0, 53);
                        $color = (int)floor($card / 13);
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

                            $cmg = $this->translator->trans('Du ziehst eine Karte... es ist: {color} {value}.', [
                                '{color}' => "<strong>{$s_color}</strong>",
                                '{value}' => "<strong>{$s_value}</strong>",
                            ], 'items');

                            if ( $value === 12 ) {
                                $ap = true;
                                $cmg .= '<hr />' . $this->translator->trans('Das muss ein Zeichen sein! In dieser Welt ist kein Platz für Moral... du erhälst 1AP.', [], 'items');
                            } else if ($value === 10 && $color === 2) {
                                $ap = true;
                                $cmg .= '<hr />' . $this->translator->trans('Das Symbol der Liebe... dein Herz schmilzt dahin und du erhälst 1AP.', [], 'items');
                            }
                        }

                        $execute_info_cache['casino'] = $cmg;
                        break;
                    // Guitar
                    case 3:
                        $count = 0;
                        foreach ($citizen->getTown()->getCitizens() as $target_citizen) {
                            // Don't give AP to dead citizen 
                            if(!$target_citizen->getAlive())
                                continue;

                            $this->citizen_handler->inflictStatus( $target_citizen, 'tg_guitar' );
                            
                            if ($target_citizen->getZone()) 
                                continue;

                            // Don't give AP if already full
                            if($target_citizen->getAp() >= $this->citizen_handler->getMaxAP($target_citizen))
                                continue;
                            else if ($this->citizen_handler->hasStatusEffect($target_citizen, ['drunk','drugged'], false)) {
                                $this->citizen_handler->setAP($target_citizen, true, 2, 0);
                                $count+=2;
                            } else {
                                $this->citizen_handler->setAP($target_citizen, true, 1, 0);
                                $count++;
                            }
                        }
                        $execute_info_cache['casino'] = $this->translator->trans('Mit deiner Gitarre hast du die Stadt gerockt! Die Bürger haben {ap} AP erhalten.', ['{ap}' => $count], 'items');
                        break;

                    // Tamer
                    case 4:case 5:case 16:case 17: {

                        // The tamer does not work if the door is closed
                        if (!$citizen->getTown()->getDoor()) {
                            $tags[] = 'fail';
                            $tags[] = 'door-closed';
                            break;
                        }

                        $heavy = $result->getCustom() === 5 || $result->getCustom() === 17;

                        $source = $citizen->getInventory();
                        $create_log = ($result->getCustom() === 4 || $result->getCustom() === 5);
                        $bank = ($result->getCustom() === 4 || $result->getCustom() === 5) ? $citizen->getTown()->getBank() : $citizen->getHome()->getChest();

                        $heavy_break = false;
                        $item_count = 0; $success_count = 0;

                        foreach ( $citizen->getInventory()->getItems() as $target_item ) {
                            if ($target_item->getEssential()) continue;
                            if ($target_item !== $item) $item_count++;
                            if ($target_item->getPrototype()->getHeavy())
                                if (!$heavy) $heavy_break = true;
                        }

                        if ($heavy_break) {
                            $tags[] = 'fail';
                            $tags[] = 'too-heavy';
                        } elseif ($this->inventory_handler->getFreeSize( $bank ) < $item_count) {
                            $tags[] = 'fail';
                            $tags[] = 'no-room';
                            $execute_info_cache["items_count"] = $item_count;
                            $execute_info_cache["size"] = ($freeSize = $this->inventory_handler->getFreeSize($bank)) > 0 ? $freeSize : 0;
                        } else {
                            foreach ( $citizen->getInventory()->getItems() as $target_item ) if ($target_item !== $item) {
                                if ($this->inventory_handler->transferItem($citizen, $target_item, $source, $bank, InventoryHandler::ModalityTamer) === InventoryHandler::ErrorNone) {
                                    $success_count++;
                                    if ($create_log) $this->entity_manager->persist($this->log->bankItemTamerLog($citizen, $target_item->getPrototype(), $target_item->getBroken()));
                                }
                            }

                            if ($success_count > 0) {
                                if ($item->getPrototype()->getName() === 'tamed_pet_#00' || $item->getPrototype()->getName() === 'tamed_pet_drug_#00' )
                                    $item->setPrototype( $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'tamed_pet_off_#00']) );
                                $this->entity_manager->persist($this->log->beyondTamerSendLog($citizen, $success_count));
                            } else {
                                $tags[] = 'no-items';
                                $tags[] = 'fail';
                            }
                        }

                        break;
                    }

                    // Survivalist
                    case 6:case 7: {
                        $drink = $result->getCustom() === 6;
                        $chances = $citizen->getTown()->getDay() <= 3 ? 1 : max(0.1, 1 - ($citizen->getTown()->getDay() * 0.025));
                        if( $citizen->getTown()->getDevastated() ) $chances = max(0.1, $chances - 0.2);

                        $give_ap = false;
                        if ($this->random_generator->chance($chances)) {
                            if ($drink) {
                                $citizen->setWalkingDistance(0);
                                if($citizen->hasRole('ghoul')){
                                    $this->citizen_handler->inflictWound($citizen);
                                } else if($this->citizen_handler->hasStatusEffect($citizen, 'thirst2')){
                                    $this->citizen_handler->removeStatus($citizen, 'thirst2');
                                    $this->citizen_handler->inflictStatus($citizen, 'thirst1');
                                } else {
                                	$this->citizen_handler->removeStatus($citizen, 'thirst1');
                                    if (!$this->citizen_handler->hasStatusEffect($citizen, 'hasdrunk')) {
                                	   $this->citizen_handler->inflictStatus($citizen, 'hasdrunk');
                                       $give_ap = true;
                                    }
                                }
                            } else {
                                if (!$this->citizen_handler->hasStatusEffect($citizen, 'haseaten')) {
                                   $this->citizen_handler->inflictStatus($citizen, 'haseaten');
                                   $give_ap = true;
                                }
                            }

                            if($give_ap){
                                $old_ap = $citizen->getAp();
                                if ($old_ap < 6)
                                    $this->citizen_handler->setAP($citizen, false, 6, 0);
                                $execute_info_cache['ap'] += ( $citizen->getAp() - $old_ap );
                            }

                            $execute_info_cache['casino'] = $this->translator->trans($drink ? 'Äußerst erfrischend, und sogar mit einer leichten Note von Cholera.' : 'Immer noch besser als das Zeug, was die Köche in der Stadt zubereiten....', [], 'items');

                        } else $execute_info_cache['casino'] = $this->translator->trans('Trotz intensiver Suche hast du nichts verwertbares gefunden...', [], 'items');
                        break;
                    }
                    // Heroic teleport action
                    case 8:case 9: {
                        $zone = null;
                        $jumper = null;
                        if ($result->getCustom() === 8 && $citizen->getZone())
                            $jumper = $citizen;

                        if ($result->getCustom() === 9 && is_a( $target, Citizen::class ))
                            $jumper = $target;

                        if (!$jumper) break;
                        $zone = $jumper->getZone();
                        if (!$zone) break;

                        $this->zone_handler->updateZone( $zone );
                        $cp_ok = $this->zone_handler->check_cp( $zone );

                        if ($dig_timer = $jumper->getCurrentDigTimer()) {
                            $dig_timer->setPassive(true);
                            $this->entity_manager->persist( $dig_timer );
                        }

                        if ($result->getCustom() === 8){
                            foreach ($citizen->getValidLeadingEscorts() as $escort)
                                $escort->getCitizen()->getEscortSettings()->setLeader(null);
                        }

                        if ($jumper->getEscortSettings()) {
                            $remove[] = $jumper->getEscortSettings();
                            $jumper->setEscortSettings(null);
                        }

                        if ($jumper->activeExplorerStats())
                            $jumper->activeExplorerStats()->setActive( false );

                        $this->citizen_handler->removeStatus($jumper, 'tg_hide');
                        $this->citizen_handler->removeStatus($jumper, 'tg_tomb');
                        $jumper->setCampingTimestamp(0);
                        $jumper->setCampingChance(0);

                        $jumper->setZone(null);
                        $zone->removeCitizen( $jumper );
                        foreach ($this->entity_manager->getRepository(HomeIntrusion::class)->findBy(['victim' => $jumper]) as $homeIntrusion)
                            $this->entity_manager->remove($homeIntrusion);

                        /*if ( $zone->getX() !== 0 || $zone->getY() !== 0 ) {
                            $zero_zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition( $zone->getTown(), 0, 0 );

                            if ($others_are_here) $this->entity_manager->persist( $this->log->outsideMove( $jumper, $zone, $zero_zone, true ) );
                            $this->entity_manager->persist( $this->log->outsideMove( $jumper, $zero_zone, $zone, false ) );
                        }*/
                        $others_are_here = $zone->getCitizens()->count() > 0;
                        if ( ($result->getCustom() === 8) && $others_are_here )
                            $this->entity_manager->persist( $this->log->heroicReturnLog( $citizen, $zone ) );
                        if ( $result->getCustom() === 9 )
                            $this->entity_manager->persist( $this->log->heroicRescueLog( $citizen, $jumper, $zone ) );
                        else $this->entity_manager->persist( $this->log->doorPass( $jumper, true ) );
                        $this->zone_handler->handleCitizenCountUpdate( $zone, $cp_ok, $jumper );

                        break;
                    }
                    // Set campingTimer
                    case 10: {
                        $date = new DateTime();
                        $citizen->setCampingTimestamp( $date->getTimestamp() );
                        $citizen->setCampingChance( $this->citizen_handler->getCampingChance($citizen) );
                        $dig_timers = $citizen->getDigTimers();
                        foreach ($dig_timers as $timer) {
                            $timer->setPassive(true);
                        }

                        break;
                    }
                    // Reset campingTimer
                    case 11:
                    {
                        $citizen->setCampingTimestamp(0);
                        $citizen->setCampingChance(0);
                        break;
                    }

                    // Discover a random ruin
                    case 12:
                    {
                        $list = [];
                        foreach ($citizen->getTown()->getZones() as $zone)
                            if ($zone->getDiscoveryStatus() === Zone::DiscoveryStateNone && $zone->getPrototype())
                                $list[] = $zone;

                        $selected = $this->random_generator->pick($list);
                        if ($selected) {
                            $upgraded_map = $this->town_handler->getBuilding($citizen->getTown(),'item_electro_#00', true);
                            $execute_info_cache['zone'] = $selected;
                            $tags[] = 'zone';
                            $selected->setDiscoveryStatus( Zone::DiscoveryStateCurrent );
                            if ($upgraded_map) $selected->setZombieStatus( Zone::ZombieStateExact );
                            else $selected->setZombieStatus( max( $selected->getZombieStatus(), Zone::ZombieStateEstimate ) );
                        }
                        break;

                    }

                    // Increase town temp defense for the watchtower by ten
                    case 13: {
                        $cn = $this->town_handler->getBuilding( $citizen->getTown(), 'small_watchmen_#00', true );
                        if ($cn) $cn->setTempDefenseBonus( $cn->getTempDefenseBonus() + 10 );
                        break;
                    }

                    // Fill water weapons
                    case 14: {

                        $trans = [
                            'watergun_empty_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_3_#00']),
                            'watergun_2_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_3_#00']),
                            'watergun_1_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_3_#00']),
                            'watergun_opt_empty_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_opt_5_#00']),
                            'watergun_opt_4_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_opt_5_#00']),
                            'watergun_opt_3_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_opt_5_#00']),
                            'watergun_opt_2_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_opt_5_#00']),
                            'watergun_opt_1_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'watergun_opt_5_#00']),
                            'grenade_empty_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'grenade_#00']),
                            'bgrenade_empty_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'bgrenade_#00']),
                            'kalach_#01' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'kalach_#00']),
                        ];

                        $fill_targets = [];
                        $filled = [];

                        foreach ($citizen->getInventory()->getItems() as $i) if (isset($trans[$i->getPrototype()->getName()]))
                            $fill_targets[] = $i;
                        foreach ($citizen->getHome()->getChest()->getItems() as $i) if (isset($trans[$i->getPrototype()->getName()]))
                            $fill_targets[] = $i;

                        foreach ($fill_targets as $i) {
                            $i->setPrototype($trans[$i->getPrototype()->getName()]);
                            if (!isset($filled[$i->getPrototype()->getId()])) $filled[$i->getPrototype()->getId()] = [$i];
                            else $filled[$i->getPrototype()->getId()][] = $i;
                            $execute_info_cache['items_spawn'][] = $i->getPrototype();
                        }

                        if (empty($filled)) $tags[] = 'fail';
                        break;
                    }

                    // Banned citizen note
                    case 15: {
                        $zones = $this->zone_handler->getZoneWithHiddenItems($citizen->getTown());
                        if(count($zones) > 0) {
                            $zone = $this->random_generator->pick($zones);
                            $tags[] = 'bannote_ok';
                            $execute_info_cache['zone'] = $zone;
                        } else {
                            $tags[] = 'bannote_fail';
                        }
                        break;
                    }

                    // Vote for a role
                    case 18:case 19: {
                        $role_name = "";
                        switch($result->getCustom()){
                            case 18:
                                $role_name = "shaman";
                                break;
                            case 19:
                                $role_name = "guide";
                                break;
                        }

                        if (!is_a( $target, Citizen::class )) break;

                        if(!$target->getAlive()) break;

                        $role = $this->entity_manager->getRepository(CitizenRole::class)->findOneBy(['name' => $role_name]);
                        if(!$role) break;

                        if ($this->entity_manager->getRepository(CitizenVote::class)->findOneByCitizenAndRole($citizen, $role))
                            break;

                        if (!$this->town_handler->is_vote_needed($citizen->getTown(), $role)) break;
                        
                        // Add our vote !
                        $citizenVote = (new CitizenVote())
                            ->setAutor($citizen)
                            ->setVotedCitizen($target)
                            ->setRole($role);

                        $citizen->addVote($citizenVote);

                        // Persist
                        $this->entity_manager->persist($citizenVote);
                        $this->entity_manager->persist($citizen);
                        
                        break;
                    }

                    // Sandballs, bitches!
                    case 20: {

                        if ($target === null) {
                            // Hordes-like - there is no target, there is only ZUUL
                            $list = $citizen->getZone()->getCitizens()->filter( function(Citizen $c) use ($citizen): bool {
                                return $c->getAlive() && $c !== $citizen && ($c->getSpecificActionCounter(ActionCounter::ActionTypeSandballHit)->getLast() === null || $c->getSpecificActionCounter(ActionCounter::ActionTypeSandballHit)->getLast()->getTimestamp() < (time() - 1800));
                            } )->getValues();
                            $sandball_target = $this->random_generator->pick( $list );

                        } else $sandball_target = $target;

                        /** @var EventActivationMarker[] $eam */
                        $eam = $this->entity_manager->getRepository(EventActivationMarker::class)->findBy(['citizen' => $citizen, 'active' => true]);
                        $b = false;
                        foreach ($eam as $m) if ($m->getEvent() === 'christmas') $b = true;
                        if (!$b) $sandball_target = null;

                        if ($sandball_target !== null) {
                            $this->picto_handler->give_picto($citizen, 'r_sandb_#00');

                            $this->inventory_handler->forceRemoveItem( $item );
                            $execute_info_cache['items_consume'][] = $item->getPrototype();

                            $execute_info_cache['citizen'] = $sandball_target;
                            $sandball_target->getSpecificActionCounter(ActionCounter::ActionTypeSandballHit)->increment();

                            $hurt = !$this->citizen_handler->isWounded($sandball_target) && $this->random_generator->chance( $town_conf->get(TownConf::CONF_MODIFIER_SANDBALL_NASTYNESS, 0.0) );
                            if ($hurt) $this->citizen_handler->inflictWound($sandball_target);

                            $this->entity_manager->persist( $this->log->sandballAttack( $citizen, $sandball_target, $hurt ) );
                            $this->entity_manager->persist($sandball_target);


                        } else $tags[] = 'fail';

                        break;
                    }

                    // Flare
                    case 21 :
                        $criteria = new Criteria();
                        $criteria->andWhere($criteria->expr()->eq('town', $citizen->getTown()));
                        $criteria->andWhere($criteria->expr()->neq('discoveryStatus', Zone::DiscoveryStateCurrent));
                        $zones = $this->entity_manager->getRepository(Zone::class)->matching($criteria)->getValues();
                        if(count($zones) > 0) {
                            $zone = $this->random_generator->pick($zones);
                            $zone->setDiscoveryStatus(Zone::DiscoveryStateCurrent);
                            $zone->setZombieStatus( max( $zone->getZombieStatus(), $this->town_handler->getBuilding($citizen->getTown(), 'item_electro_#00', true) ? Zone::ZombieStateExact : Zone::ZombieStateEstimate ) );
                            $this->entity_manager->persist($zone);
                            $this->inventory_handler->forceRemoveItem( $item );
                            $execute_info_cache['items_consume'][] = $item->getPrototype();
                            $tags[] = 'flare_ok';
                            $execute_info_cache['zone'] = $zone;
                        } else {
                            $tags[] = 'flare_fail';
                        }
                        break;

                    // Chance to infect in a contaminated zone
                    case 22:
                        if ($town_conf->get(TownConf::CONF_FEATURE_ALL_POISON, false)) {

                            if ($this->random_generator->chance(0.05) && !$this->citizen_handler->hasStatusEffect($citizen, 'infection')) {

                                $inflict = true;
                                if ($this->citizen_handler->hasStatusEffect($citizen, "tg_infect_wtns")) {
                                    $inflict = $this->random_generator->chance(0.5);
                                    $this->citizen_handler->removeStatus( $citizen, 'tg_infect_wtns' );
                                    if ($inflict){
                                        $execute_info_cache['message'][] = T::__("Ein Opfer der Großen Seuche zu sein hat dir diesmal nicht viel gebracht... und es sieht nicht gut aus...", "items");
                                    } else {
                                        $execute_info_cache['message'][] = T::__("Da hast du wohl Glück gehabt... Als Opfer der Großen Seuche bist du diesmal um eine unangenehme Infektion herumgekommen.", "items");
                                    }
                                } else {
                                    $execute_info_cache['message'][] = T::__("Schlechte Nachrichten, du hättest das nicht herunterschlucken sollen... du hast dir eine Infektion eingefangen!", "items");
                                }

                                if ($inflict && $this->citizen_handler->inflictStatus($citizen, 'infection')) {
                                    $tags[] = 'stat-up';
                                    $tags[] = "stat-up-infection";
                                }

                            }

                        }
                }

                if ($ap) {
                    $this->citizen_handler->setAP( $citizen, true, 1, 1 );
                    $execute_info_cache['ap'] += 1;
                }

                $prevent_terror = $this->inventory_handler->countSpecificItems([$citizen->getInventory(), $citizen->getHome()->getChest()], 'prevent_terror') > 0;

                if ($terror && !$prevent_terror)
                    $this->citizen_handler->inflictStatus( $citizen, 'terror' );
            }

            if ($result_group = $result->getResultGroup()) {
                $r = $this->random_generator->pickResultsFromGroup( $result_group );
                foreach ($r as $sub_result) $execute_result( $sub_result );
            }

            if($result->getMessage()){

                if ($result->getMessage()->getEscort() === null || $result->getMessage()->getEscort() === $escort_mode) {
                    $index = $result->getMessage()->getOrdering();
                    while(isset($execute_info_cache['message'][$index]) && !empty($execute_info_cache['message'][$index])) {
                        $index++;
                    }
                    $execute_info_cache['message'][$index] = $result->getMessage()->getText();
                }

            }

            return self::ErrorNone;
        };

        foreach ($action->getResults() as $result) {
            $res = $execute_result($result); // Here, we process AffectMessages AND $kill_by_poison var
            if($res !== self::ErrorNone)
                return $res;
        }

        if ($spread_poison) $item->setPoison( true );
        if ($kill_by_poison && $citizen->getAlive()) {
            $this->death_handler->kill( $citizen, CauseOfDeath::Poison, $r );
            foreach ($r as $r_entry) $remove[] = $r_entry;
            $this->entity_manager->persist( $this->log->citizenDeath( $citizen ) );
        }

        if ($kill_by_poison) {
            $execute_info_cache['message'] = [];
        }

        if(!empty($execute_info_cache['message'])) {
        	// We order the messages
        	ksort($execute_info_cache['message']);
        	// We translate & replace placeholders in each messages
        	$addedContent = [];

        	foreach ($execute_info_cache['message'] as $contentMessage) {
                $placeholders = [
	                '{ap}'            => $execute_info_cache['ap'],
	                '{minus_ap}'      => -$execute_info_cache['ap'],
	                '{well}'          => $execute_info_cache['well'],
	                '{zombies}'       => $execute_info_cache['zombies'],
	                '{item}'          => $this->wrap($execute_info_cache['item']),
	                '{target}'        => $execute_info_cache['target'] ? $this->wrap($execute_info_cache['target']) : "-",
	                '{citizen}'       => $execute_info_cache['citizen'] ? $this->wrap($execute_info_cache['citizen']) : "-",
	                '{user}'          => $execute_info_cache['user'] ? $this->wrap($execute_info_cache['user']) : "-",
	                '{item_from}'     => $execute_info_cache['item_morph'][0] ? ($this->wrap($execute_info_cache['item_morph'][0])) : "-",
	                '{item_to}'       => $execute_info_cache['item_morph'][1] ? ($this->wrap($execute_info_cache['item_morph'][1])) : ( $execute_info_cache['items_spawn'] ? ($this->wrap($execute_info_cache['items_spawn'][0])) : "-" ),
	                '{target_from}'   => $execute_info_cache['item_target_morph'][0] ? ($this->wrap($execute_info_cache['item_target_morph'][0])) : "-",
	                '{target_to}'     => $execute_info_cache['item_target_morph'][1] ? ($this->wrap($execute_info_cache['item_target_morph'][1])) : "-",
                    '{item_tool}'     => $execute_info_cache['item_tool'] ? ($this->wrap($execute_info_cache['item_tool'])) : "-",
	                '{items_consume}' => $this->wrap_concat($execute_info_cache['items_consume']),
                    '{tamer_dog}'     => $execute_info_cache['tamer_dog'],
	                '{items_spawn}'   => $this->wrap_concat($execute_info_cache['items_spawn']),
	                '{bp_spawn}'      => $this->wrap_concat($execute_info_cache['bp_spawn']),
	                '{bp_parent}'     => $this->wrap_concat_hierarchy($execute_info_cache['bp_parent']),
	                '{rp_text}'       => $this->wrap( $execute_info_cache['rp_text'] ),
	                '{zone}'          => $execute_info_cache['zone'] ? $this->wrap( "{$execute_info_cache['zone']->getX()} / {$execute_info_cache['zone']->getY()}" ) : '',
	                '{casino}'        => $execute_info_cache['casino'],
	                '{kills}'         => $execute_info_cache['kills'],
	                '{bury_count}'    => $execute_info_cache['bury_count'],
	                '{hr}'            => "<hr />",
                    '{items_count}'   => $execute_info_cache['items_count'],
                    '{size}'          => $execute_info_cache['size'],
                    '{home_storage}'  => $execute_info_cache['home_storage'],
                    '{home_defense}'  => $execute_info_cache['home_defense'],
	            ];

                // How many indexes we need for array placeholders seeks
                // Currently only items_consume, more can be added in this loop as needed
                $seekIndexes = 2;
                for($currentIndex = 0; $currentIndex < $seekIndexes; $currentIndex++) {
                    $placeholders['{items_consume_'.$currentIndex.'}'] = isset($execute_info_cache['items_consume'][$currentIndex]) ? ($this->wrap($execute_info_cache['items_consume'][$currentIndex])) : "-";
                }

                $contentMessage = $this->translator->trans( $contentMessage, $placeholders, 'items' );
	        	do {
	                $contentMessage = preg_replace_callback( '/<t-(.*?)>(.*?)<\/t-\1>/' , function(array $m) use ($tags): string {
	                    [, $tag, $text] = $m;
	                    return in_array( $tag, $tags ) ? $text : '';
	                }, $contentMessage, -1, $c);
	                $contentMessage = preg_replace_callback( '/<nt-(.*?)>(.*?)<\/nt-\1>/' , function(array $m) use ($tags): string {
	                    [, $tag, $text] = $m;
	                    return !in_array( $tag, $tags ) ? $text : '';
	                }, $contentMessage, -1, $d);
	            } while ($c > 0 || $d > 0);
                $addedContent[] = $contentMessage;
        	}

        	// We remove empty elements
        	$addedContent = array_filter($addedContent);
            $message = implode('<hr />', $addedContent);
        }


        return self::ErrorNone;
    }

    public function execute_recipe( Citizen &$citizen, Recipe $recipe, ?array &$remove, ?string &$message ): int {
        $town = $citizen->getTown();
        $c_inv = $citizen->getInventory();
        $t_inv = $citizen->getTown()->getBank();

        switch ( $recipe->getType() ) {
            case Recipe::WorkshopType:case Recipe::WorkshopTypeShamanSpecific:case Recipe::ManualInside:
                if ($citizen->getZone()) return ErrorHelper::ErrorActionNotAvailable;
                break;
            case Recipe::ManualOutside:
                if (!$citizen->getZone()) return ErrorHelper::ErrorActionNotAvailable;
                break;
            default: break;
        }

        $remove = [];

        if (in_array($recipe->getType(), [Recipe::WorkshopType, Recipe::WorkshopTypeShamanSpecific])) {
            $have_saw  = $this->inventory_handler->countSpecificItems( $c_inv, $this->entity_manager->getRepository( ItemPrototype::class )->findOneBy(['name' => 'saw_tool_#00']), false, false ) > 0;
            $have_manu = $this->town_handler->getBuilding($town, 'small_factory_#00', true) !== null;

            $ap = 3 - ($have_saw ? 1 : 0) - ($have_manu ? 1 : 0);
        } else $ap = 0;


        if ( in_array($recipe->getType(), [Recipe::WorkshopType, Recipe::WorkshopTypeShamanSpecific]) && (($citizen->getAp() + $citizen->getBp()) < $ap || $this->citizen_handler->isTired( $citizen )) )
            return ErrorHelper::ErrorNoAP;

        $source_inv = in_array($recipe->getType(), [Recipe::WorkshopType, Recipe::WorkshopTypeShamanSpecific]) ? [ $t_inv ] : ($citizen->getZone() ? [$c_inv] : [$c_inv, $citizen->getHome()->getChest() ]);
        $target_inv = in_array($recipe->getType(), [Recipe::WorkshopType, Recipe::WorkshopTypeShamanSpecific]) ? [ $t_inv ] : ($citizen->getZone() ? ($citizen->getZone()->getX() != 0 || $citizen->getZone()->getY() != 0 ? [$c_inv,$citizen->getZone()->getFloor()] : [$c_inv])  : [$c_inv, $citizen->getHome()->getChest(), $t_inv]);

        if (!in_array($recipe->getType(), [Recipe::WorkshopType, Recipe::WorkshopTypeShamanSpecific]) && $citizen->getZone() && $this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_FLOOR_ASMBLY, false))
            $source_inv[] = $citizen->getZone()->getFloor();

        $items = $this->inventory_handler->fetchSpecificItems( $source_inv, $recipe->getSource() );
        if (empty($items)) return ErrorHelper::ErrorItemsMissing;

        $list = [];
        foreach ($items as $item) {
            if($recipe->getKeep()->contains($item->getPrototype())) continue;
            $r = $recipe->getSource()->findEntry( $item->getPrototype()->getName() );
            $this->inventory_handler->forceRemoveItem( $item, $r->getChance() );
            $list[] = $item->getPrototype();
        }

        $this->citizen_handler->deductAPBP( $citizen, $ap );

        $new_item = $this->random_generator->pickItemPrototypeFromGroup( $recipe->getResult() );
        $this->inventory_handler->placeItem( $citizen, $this->item_factory->createItem( $new_item ) , $target_inv, true );

        if (in_array($recipe->getType(), [Recipe::WorkshopType, Recipe::WorkshopTypeShamanSpecific]))
            $this->entity_manager->persist( $this->log->workshopConvert( $citizen, array_map( function(Item $e) { return array($e->getPrototype()); }, $items  ), array([$new_item]) ) );

        switch ( $recipe->getType() ) {
            case Recipe::WorkshopType:
            case Recipe::WorkshopTypeShamanSpecific:
              switch($recipe->getAction()) {
                case "Öffnen":
                  $base = T::__('Du hast {item_list} in der Werkstatt geöffnet und erhälst {item}.', 'game');
                  break;
                case "Zerlegen":
                  $base = T::__('Du hast {item_list} in der Werkstatt zu {item} zerlegt.', 'game');
                  break;
                default:
                  $base = T::__('Du hast {item_list} in der Werkstatt zu {item} umgewandelt.', 'game');
              }
              $this->picto_handler->give_picto($citizen, "r_refine_#00");
              break;
            case Recipe::ManualOutside:case Recipe::ManualInside:case Recipe::ManualAnywhere:default:
                $base = T::__('Du hast {item_list} zu {item} umgewandelt.', 'game');
                break;
        }

        if($recipe->getPictoPrototype() !== null) {
            $this->picto_handler->give_picto($citizen, $recipe->getPictoPrototype());
        }

        $message = $this->translator->trans( $base, [
            '{item_list}' => $this->wrap_concat( $list ),
            '{item}' => $this->wrap( $new_item ),
            '{ap}' => $ap,
        ], 'game' );

        return self::ErrorNone;
    }
}
