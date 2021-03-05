<?php


namespace App\Service;

use App\Entity\Citizen;
use App\Entity\CitizenRole;
use App\Entity\DigTimer;
use App\Entity\EscapeTimer;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemGroup;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemPrototype;
use App\Entity\LogEntryTemplate;
use App\Entity\PictoPrototype;
use App\Entity\RuinExplorerStats;
use App\Entity\RuinZone;
use App\Entity\Town;
use App\Entity\TownLogEntry;
use App\Entity\Zone;
use App\Structures\EventConf;
use App\Structures\TownConf;
use App\Translation\T;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Asset\Packages;
use Symfony\Contracts\Translation\TranslatorInterface;

class ZoneHandler
{
    private $entity_manager;
    private $item_factory;
    private $status_factory;
    private $random_generator;
    private $inventory_handler;
    private $citizen_handler;
    private $picto_handler;
    private $trans;
    private $log;
    private $conf;
    private $asset;

    public function __construct(
        EntityManagerInterface $em, ItemFactory $if, LogTemplateHandler $lh, TranslatorInterface $t,
        StatusFactory $sf, RandomGenerator $rg, InventoryHandler $ih, CitizenHandler $ch, PictoHandler $ph, Packages $a,
        ConfMaster $conf)
    {
        $this->entity_manager = $em;
        $this->item_factory = $if;
        $this->status_factory = $sf;
        $this->random_generator = $rg;
        $this->inventory_handler = $ih;
        $this->citizen_handler = $ch;
        $this->picto_handler = $ph;
        $this->trans = $t;
        $this->log = $lh;
        $this->asset = $a;
        $this->conf = $conf;
    }

    public function updateRuinZone(?RuinExplorerStats $ex) {
        if ($ex === null || !$ex->getActive()) return false;

        $eject = $ex->getTimeout()->getTimestamp() < time() || $this->citizen_handler->isWounded( $ex->getCitizen() ) || $this->citizen_handler->hasStatusEffect($ex->getCitizen(), 'terror');
        $wound = $ex->getTimeout()->getTimestamp() < time();

        if ($eject) {
            $citizen = $ex->getCitizen();
            $ruinZone = $this->entity_manager->getRepository(RuinZone::class)->findOneByPosition($citizen->getZone(), $ex->getX(), $ex->getY());

            foreach ($citizen->getInventory()->getItems() as $item)
                $this->inventory_handler->moveItem( $citizen, $citizen->getInventory(), $item, [$ruinZone->getFloor()] );

            if ($wound) $this->citizen_handler->inflictWound( $citizen );

            $ex->setActive( false );

            $this->entity_manager->persist( $citizen );
            $this->entity_manager->persist( $ex );
            $this->entity_manager->persist( $ruinZone );

            return true;
        } else return false;
    }

    public function updateZone( Zone $zone, ?DateTime $up_to = null, ?Citizen $active = null ): ?string {

        $now = new DateTime();
        if ($up_to === null || $up_to > $now) $up_to = $now;

        $chances_by_player = 0;
        $chances_by_escorts = 0;
        $found_by_player = [];
        $found_by_escorts = [];
        $ret_str = [];

        /** @var DigTimer[] $dig_timers */
        $dig_timers = [];
        $cp = 0;

        foreach ($zone->getCitizens() as $citizen) {
            $timer = $citizen->getCurrentDigTimer();
            if ($timer && !$timer->getPassive() && $timer->getTimestamp() < $up_to)
                $dig_timers[] = $timer;
            $cp += $this->citizen_handler->getCP( $citizen );
        }

        if ($cp < $zone->getZombies()) {
            foreach ($dig_timers as $timer) {
                $timer->setPassive(true);
                $this->entity_manager->persist($timer);
            }

            return null;
        }

        $sort_func = function(DigTimer $a, DigTimer $b): int {
            return $a->getTimestamp()->getTimestamp() == $b->getTimestamp()->getTimestamp() ? 0 :
                ($a->getTimestamp() < $b->getTimestamp() ? -1 : 1);
        };

        $empty_group = $this->entity_manager->getRepository(ItemGroup::class)->findOneBy(['name' => 'empty_dig']);
        $base_group = $this->entity_manager->getRepository(ItemGroup::class)->findOneBy(['name' => 'base_dig']);
        $event_group = null;

        // Get event specific items
        $event = $this->conf->getCurrentEvent($zone->getTown())->get(EventConf::EVENT_DIG_DESERT_GROUP, null);
        $event_chance = $this->conf->getCurrentEvent($zone->getTown())->get(EventConf::EVENT_DIG_DESERT_CHANCE, 1.0);
        if ($event && $event_chance > 0) $event_group = $this->entity_manager->getRepository(ItemGroup::class)->findOneBy(['name' => $event]);

        $wrap = function(array $a) {
            return implode(', ', array_map(function(ItemPrototype $p) {
                return "<span class='tool'><img alt='' src='{$this->asset->getUrl( "build/images/item/item_{$p->getIcon()}.gif" )}'> {$this->trans->trans($p->getLabel(), [], 'items')}</span>";
            }, $a));
        };

        $conf = $this->conf->getTownConfiguration( $zone->getTown() );

        $zone_update = false;

        $active_dig_timers = $active ? array_filter($dig_timers, function(DigTimer $t) use ($active) {
            return $active === $t->getCitizen() || ($t->getCitizen()->getEscortSettings() && $t->getCitizen()->getEscortSettings()->getLeader() === $active);
        }) : $dig_timers;

        $not_up_to_date = !empty($active_dig_timers);
        while ($not_up_to_date) {

            usort( $active_dig_timers, $sort_func );
            foreach ($active_dig_timers as &$timer)
                if ($timer->getTimestamp() <= $up_to) {

                    $current_citizen = $timer->getCitizen();

                    if ($active && $active !== $current_citizen && $current_citizen->getEscortSettings() && $current_citizen->getEscortSettings()->getLeader() !== $active)
                        continue;

                    $factor = 1.0;
                    if ($timer->getCitizen()->getProfession()->getName() === 'collec') $factor += 0.3;
                    if ($this->citizen_handler->hasStatusEffect( $timer->getCitizen(), 'camper' )) $factor += 0.1;
                    if ($this->citizen_handler->hasStatusEffect( $timer->getCitizen(), 'wound5' )) $factor -= 0.3; // Totally arbitrary
                    if ($this->citizen_handler->hasStatusEffect( $timer->getCitizen(), 'drunk'  )) $factor -= 0.3; // Totally arbitrary
                    if ($conf->get(TownConf::CONF_FEATURE_NIGHTMODE, true) && $timer->getCitizen()->getTown()->isNight() && $this->inventory_handler->countSpecificItems($zone->getFloor(), 'prevent_night', true) == 0) $factor -= 0.2;

                    $total_dig_chance = max(0.1, $factor * ($zone->getDigs() > 0 ? 0.6 : 0.3 ));

                    $item_prototype = $this->random_generator->chance($total_dig_chance)
                        ? $this->random_generator->pickItemPrototypeFromGroup( $zone->getDigs() > 0 ? $base_group : $empty_group )
                        : ($event_group && $zone->getDigs() > 0 && $this->random_generator->chance($total_dig_chance * $event_chance)
                            ? $this->random_generator->pickItemPrototypeFromGroup( $event_group )
                            : null);

                    if ($active && $current_citizen->getId() === $active->getId()) {
                        $chances_by_player++;
                        if ($item_prototype)
                            $found_by_player[] = $item_prototype;
                    }

                    if ($active && $current_citizen->getEscortSettings() && $current_citizen->getEscortSettings()->getLeader() && $current_citizen->getEscortSettings()->getLeader()->getId() === $active->getId()) {
                        $chances_by_escorts++;
                        if ($item_prototype)
                            $found_by_escorts[] = $item_prototype;
                    }

                    if ($item_prototype) {
                        // If we get a Chest XL, we earn a picto
                        if ($item_prototype->getName() == 'chest_xl_#00') {
                            $pictoPrototype = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => "r_chstxl_#00"]);
                            $this->picto_handler->give_picto($current_citizen, $pictoPrototype);
                        }

                        $item = $this->item_factory->createItem($item_prototype);
                        if ($inventoryDest = $this->inventory_handler->placeItem( $current_citizen, $item, [ $current_citizen->getInventory(), $timer->getZone()->getFloor() ] )) {
                            if($inventoryDest->getId() === $timer->getZone()->getFloor()->getId()){
                                $this->entity_manager->persist($this->log->beyondItemLog($current_citizen, $item->getPrototype(), true));
                                if ($active && $current_citizen->getEscortSettings() && $current_citizen->getEscortSettings()->getLeader() && $current_citizen->getEscortSettings()->getLeader() === $active)
                                    $ret_str[] = $this->trans->trans('Er kann den Gegenstand momentan nicht aufnehmen und hat ihn auf dem Boden abgelegt.', [], 'game');
                                elseif ($active && $current_citizen === $active)
                                    $ret_str[] = $this->trans->trans('Der Gegenstand, den du soeben gefunden hast, passt nicht in deinen Rucksack, darum bleibt er erstmal am Boden...', [], 'game');
                            }
                            $this->entity_manager->persist( $item );
                            $this->entity_manager->persist( $current_citizen->getInventory() );
                            $this->entity_manager->persist( $timer->getZone()->getFloor() );
                        }
                    } else {
                        //TODO: Persist log only if it is an automatic search
                        $this->entity_manager->persist( $this->log->outsideDig( $current_citizen, $item_prototype, $timer->getTimestamp() ) ); 
                    }

                    $zone->setDigs( max(($item_prototype || $zone->getDigs() <= 0) ? 0 : 1, $zone->getDigs() - 1) );
                    $zone_update = true;

                    try {
                        $timer->setTimestamp(
                            (new DateTime())->setTimestamp(
                                $timer->getTimestamp()->getTimestamp()
                            )->modify($conf->get( $timer->getCitizen()->getProfession()->getName() === 'collec' ?
                                TownConf::CONF_TIMES_DIG_COLLEC :
                                TownConf::CONF_TIMES_DIG_NORMAL, '+2hour')) );

                    } catch (Exception $e) {
                        $timer->setTimestamp( new DateTime('+1min') );
                    }

                    // Banished citizen's stach check
                    if(!$timer->getCitizen()->getBanished() && $this->hasHiddenItem($timer->getZone()) && $this->random_generator->chance(0.05)){
                        $items = $timer->getZone()->getFloor()->getItems();
                        $itemsproto = array_map( function($e) {return $e->getPrototype(); }, $items->toArray() );
                        $ret_str[] = $this->trans->trans('Beim Graben bist du auf eine Art... geheimes Versteck mit %items% gestoßen! Es wurde vermutlich von einem verbannten Mitbürger angelegt...', ['%items%' => $wrap($itemsproto) ], 'game');
                        foreach ($items as $item) {
                            if($item->getHidden()){
                                $item->setHidden(false);
                                $this->entity_manager->persist($item);
                            }
                        }
                    }
                }
            $not_up_to_date = $active_dig_timers[0]->getTimestamp() < $up_to;
        }

        if ($zone_update) $this->entity_manager->persist($zone);
        foreach ($dig_timers as $timer) $this->entity_manager->persist( $timer );

        if ($chances_by_player > 0) {
            if (empty($found_by_player)){
                if ($this->citizen_handler->hasStatusEffect( $active, 'wound5' ))
                    array_unshift($ret_str, $this->trans->trans( 'Deine Verletzung am Auge macht dir die Suche nicht gerade leichter.', [], 'game'));
                if ($this->citizen_handler->hasStatusEffect( $active, 'drunk' ))
                    array_unshift($ret_str, $this->trans->trans( 'Deine Trunkenheit macht dir die Suche nicht gerade leichter.', [], 'game'));

                array_unshift($ret_str, $this->trans->trans( 'Trotz all deiner Anstrengungen hast du hier leider nichts gefunden ...', [], 'game' ));
            }
            elseif (count($found_by_player) === 1)
                array_unshift($ret_str, $this->trans->trans( 'Nach einigen Anstrengungen hast du folgendes gefunden: %item%!', [
                    '%item%' => $wrap($found_by_player)
                ], 'game' ));
            else array_unshift($ret_str, $this->trans->trans( 'Du gräbst schon seit einiger Zeit und hast mehrere Gegenstände gefunden: %items%', ['%items%' => $wrap($found_by_player)], 'game' ));
        }

        if ($chances_by_escorts > 0) {
            if (empty($found_by_escorts) && $chances_by_escorts === 1) array_unshift($ret_str, $this->trans->trans( 'Trotz all seiner Anstrengungen hat dein Freund hier leider nichts gefunden...', [], 'game' ));
            elseif (empty($found_by_escorts) && $chances_by_escorts > 1) array_unshift($ret_str, $this->trans->trans( 'Trotz all ihrer Anstrengungen hat deine Expedition hier leider nichts gefunden...', [], 'game' ));
            elseif ($chances_by_escorts === 1) array_unshift($ret_str, $this->trans->trans( 'Nach einigen Anstrengungen hat dein Freund folgendes gefunden: %item%!', ['%item%' => $wrap($found_by_escorts)], 'game' ));
            else array_unshift($ret_str, $this->trans->trans( 'Nach einigen Anstrengungen hat deine Expedition folgendes gefunden: %item%!', ['%item%' => $wrap($found_by_escorts)], 'game' ));
        }

        if(($chances_by_player > 0 || $chances_by_escorts > 0) && $zone->getDigs() <= 0) {
            $ret_str[] = $this->trans->trans("Diese Zone ist leergesucht. Du wirst hier keine wertvollen Gegenstände mehr finden können.", [], "game");
        }

        $ret_str = array_unique($ret_str);

        return empty($ret_str) ? null : implode('<hr />', $ret_str);

    }

    const RespawnModeNone = 0;
    const RespawnModeAuto = 1;
    const RespawnModeForce = 2;

    public function dailyZombieSpawn( Town &$town, int $cycles = 1, int $mode = self::RespawnModeAuto ) {

        /** @var Zone[] $zones */
        $zones = $town->getZones()->getValues();
        $zone_db = []; $empty_zones = []; $despair_db = [];
        $killedZombies = 0;
        foreach ($zones as &$zone) {
            $killedZombies += ($zone->getInitialZombies() - $zone->getZombies());

            $despair = max(0,( $zone->getInitialZombies() - $zone->getZombies() - 1 ) / 2);
            if (!isset($zone_db[$zone->getX()])) $zone_db[$zone->getX()] = [];
            $zone_db[$zone->getX()][$zone->getY()] = $zone->getZombies();
            $despair_db[$zone->getX()][$zone->getY()] = $despair;
            if ($zone_db[$zone->getX()][$zone->getY()] == 0) $empty_zones[] = $zone;

            $zone->setScoutEstimationOffset( mt_rand(-2,2) );
        }

        $factor = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_RESPAWN_FACTOR, 1);

        // Respawn
        $d = $town->getDay();
        if ($mode === self::RespawnModeForce ||
            ($mode === self::RespawnModeAuto && $d >= 3 && (
                (count($empty_zones) > (count($zones)* 18/20)) /*|| ($killedZombies >= $town->getMapSize() * $town->getDay() * $factor)*/
            ))) {
            $keys = $d == 1 ? [array_rand($empty_zones)] : array_rand($empty_zones, min($d,count($empty_zones)));
            foreach ($keys as $spawn_zone_id)
                /** @var Zone $spawn_zone */
                $zone_db[ $zones[$spawn_zone_id]->getX() ][ $zones[$spawn_zone_id]->getY() ] = mt_rand(1,intval(ceil($town->getDay() / 2)));
            $cycles += ceil($d/2);
        }


        for ($c = 0; $c < $cycles; $c++) {
            $zone_original_db = $zone_db;
            foreach ($zone_db as $x => &$zone_row)
                foreach ($zone_row as $y => &$current_zone_zombies) {
                    $adj_zones_total = $adj_zones_infected = $zone_zed_difference = 0;

                    for ($dx = -1; $dx <= 1; $dx++)
                        if (isset($zone_original_db[$x + $dx]))
                            for ($dy = -1; $dy <= 1; $dy++) if ($dx !== 0 || $dy !== 0) {
                                if (isset($zone_original_db[$x + $dx][$y + $dy])) {
                                    $adj_zones_total++;
                                    if ($zone_original_db[$x + $dx][$y + $dy] > $zone_original_db[$x][$y]) {
                                        $adj_zones_infected++;
                                        $dif = $zone_original_db[$x + $dx][$y + $dy] - $zone_original_db[$x][$y];
                                        $zone_zed_difference += ( abs($dx) + abs($dy) == 2 ? ceil($dif/2) : $dif );
                                    }
                                }
                            }

                    if ($adj_zones_infected > 0) {

                        $spread_chance = 1 - pow(0.875, $zone_zed_difference);
                        if (mt_rand(0,100) > (100*$spread_chance)) continue;

                        $max_zeds = ceil($zone_zed_difference / 8 );
                        $min_zeds = min($max_zeds, floor($max_zeds * ($adj_zones_infected / $adj_zones_total)));
                        $current_zone_zombies += mt_rand($min_zeds, $max_zeds);
                    }

                }

            foreach ($zone_db as $x => &$zone_row)
                foreach ($zone_row as $y => &$current_zone_zombies) {
                    if ($x === 0 && $y === 0) continue;
                    if ($current_zone_zombies > 0) $current_zone_zombies += max(0,mt_rand(-2, 1));
                }
        }

        foreach ($town->getZones() as &$zone) {
            if ($zone->getX() === 0 && $zone->getY() === 0) continue;

            $zombies = max( 0, $zone_db[$zone->getX()][$zone->getY()] );
            $zone->setZombies( max(0, $zombies - $despair_db[$zone->getX()][$zone->getY()] ));
            $zone->setInitialZombies( $zombies );
        }

    }

    public function check_cp(Zone $zone, ?int &$cp = null): bool {
        $cp = 0;
        foreach ($zone->getCitizens() as $c)
            if ($c->getAlive())
                $cp += $this->citizen_handler->getCP($c);

        return $cp >= $zone->getZombies();
    }

    /**
     * @param Zone $zone
     * @param $cp_ok_before
     */
    public function handleCitizenCountUpdate(&$zone, $cp_ok_before) {
        // If no citizens remain in a zone, invalidate all associated escape timers and clear the log
        if (!count($zone->getCitizens())) {
            foreach ($zone->getEscapeTimers() as $et)
                $this->entity_manager->remove( $et );
            foreach ($zone->getChatSilenceTimers() as $cst)
                $this->entity_manager->remove( $cst );
            foreach ($this->entity_manager->getRepository(TownLogEntry::class)->findByFilter( $zone->getTown(), null, null, $zone, null, null ) as $entry)
                /** @var TownLogEntry $entry */
                if ($entry->getLogEntryTemplate() === null || $entry->getLogEntryTemplate()->getClass() !== LogEntryTemplate::ClassCritical)
                    $this->entity_manager->remove( $entry );
        }

        // If zombies can take control after leaving the zone and there are citizens remaining, install a grace escape timer
        else if ($cp_ok_before !== null) {
            if ( $cp_ok_before && !$this->check_cp( $zone ) ) {
                $zone->addEscapeTimer( (new EscapeTimer())->setTime( new DateTime('+30min') ) );
                // Disable all dig timers
                foreach ($zone->getDigTimers() as $dig_timer) {
                    $dig_timer->setPassive(true);
                    $this->entity_manager->persist( $dig_timer );
                }
            }
            // If we took back control of the zone, logs it
            elseif (!$cp_ok_before && $this->check_cp($zone)) {
                $this->entity_manager->persist($this->log->zoneUnderControl($zone));
            }
        }
    }

    public function getSoulZones( Town $town ) {
        // Get all zone inventory IDs
        // We're just getting IDs, because we don't want to actually hydrate the inventory instances
        $zone_invs = array_column($this->entity_manager->createQueryBuilder()
            ->select('i.id')
            ->from(Inventory::class, 'i')
            ->join("i.zone", "z")
            ->andWhere('z.id IN (:zones)')->setParameter('zones', $town->getZones())
            ->getQuery()
            ->getScalarResult(), 'id');

        // Get all soul items within these inventories
        $soul_items = $this->entity_manager->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->andWhere('i.inventory IN (:invs)')->setParameter('invs', $zone_invs)
            ->andWhere('i.prototype IN (:protos)')->setParameter('protos', [
                $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName('soul_blue_#00'),
                $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName('soul_blue_#01'),
                $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName('soul_red_#00')
            ])
            ->getQuery()
            ->getResult();

        $cache = [];
        /** @var Item $item */
        foreach ($soul_items as $item)
            if (!isset($cache[$item->getInventory()->getId()]))
                $cache[$item->getInventory()->getId()] = $item->getInventory()->getZone();
        return array_values($cache);
    }

    public function hasHiddenItem(Zone $zone){
        // get hidden item count
        $query = $this->entity_manager->createQueryBuilder()
            ->select('SUM(i.count)')
            ->from(Item::class, 'i')
            ->andWhere('i.inventory = :invs')->setParameter('invs', $zone->getFloor())
            ->andWhere('i.hidden = true')
            ->getQuery();

        return $query->getSingleScalarResult() > 0;
    }

    public function getZoneWithHiddenItems( Town $town ) {
        // Get all zone inventory IDs
        // We're just getting IDs, because we don't want to actually hydrate the inventory instances
        $zone_invs = array_column($this->entity_manager->createQueryBuilder()
            ->select('i.id')
            ->from(Inventory::class, 'i')
            ->join("i.zone", "z")
            ->andWhere('z.id IN (:zones)')->setParameter('zones', $town->getZones())
            ->getQuery()
            ->getScalarResult(), 'id');

        // Get all hidden items within these inventories
        $hidden_items = $this->entity_manager->createQueryBuilder()
            ->select('i')
            ->from(Item::class, 'i')
            ->andWhere('i.inventory IN (:invs)')->setParameter('invs', $zone_invs)
            ->andWhere('i.hidden = true')
            ->getQuery()
            ->getResult();

        $cache = [];
        /** @var Item $item */
        foreach ($hidden_items as $item)
            if (!isset($cache[$item->getInventory()->getId()]))
                $cache[$item->getInventory()->getId()] = $item->getInventory()->getZone();
        return array_values($cache);
    }

    public function getZoneClasses(Town $town, Zone $zone, ?Citizen $citizen = null, bool $soul = false, bool $admin = false) {
        $attributes = ['zone'];

        if ($zone->getX() == 0 && $zone->getY() == 0) {
            $attributes[] = 'town';
        }
        if ($zone->getX() == 0 && $zone->getY() == 0 && $town->getDevastated()) {
            $attributes[] = 'devast';
        }
        if ($citizen && $zone === $citizen->getZone()) {
            $attributes[] = 'active';
        }
        if (!$admin && $zone->getDiscoveryStatus() === Zone::DiscoveryStateNone) {
            $attributes[] = 'unknown';
        } else {
            if (!$admin && $zone->getDiscoveryStatus() === Zone::DiscoveryStatePast) {
                $attributes[] = 'past';
            }
            if ($zone->getPrototype()) {
                $attributes[] = 'ruin';
                if ($zone->getBuryCount() > 0) {
                    $attributes[] = 'buried';
                }
            }
        }
        if (($zone->getDiscoveryStatus() === Zone::DiscoveryStateCurrent && $zone->getZombieStatus() >= Zone::ZombieStateEstimate) || $admin) {
            if ($zone->getZombies() == 0) {
                $attributes[] = 'danger-0';
            }
            else if ($zone->getZombies() <= 2) {
                $attributes[] = 'danger-1';
            }
            else if ($zone->getZombies() <= 5) {
                $attributes[] = 'danger-2';
            }
            else {
                $attributes[] = 'danger-3';
            }
        }

        if($soul)
            $attributes[] = "soul";

        return $attributes;
    }

    public function getZoneAttributes(Zone $zone, ?Citizen $citizen = null) {
        $attributes = ['zone'];
        $attributes['town'] = ($zone->getX() == 0 && $zone->getY() == 0) ? 1 : 0;
        $attributes['devast'] = ($zone->getX() == 0 && $zone->getY() == 0 && $zone->getTown()->getDevastated()) ? 1 : 0;
        $attributes['active'] = $citizen && $zone === $citizen->getZone() ? 1 : 0;
        $attributes['discovery'] = $zone->getDiscoveryStatus();
        if ($zone->getDiscoveryStatus() && $zone->getPrototype()) {
            if ($zone->getBuryCount() > 0) {
                $attributes['building'] = [
                    'name' => T::__("Ein nicht freigeschaufeltes Gebäude.", "game"),
                    'type' => -1,
                    'dig' => $zone->getBuryCount(),
                    'empty' => ($zone->getRuinDigs() == 0),
                    'blueprint' => $zone->getBlueprint(),
                ];
            }
            else {
                $attributes['building'] = [
                    'name' => $this->trans->trans($zone->getPrototype()->getLabel(), [], "game"),
                    'type' => $zone->getPrototype()->getId(),
                    'dig' => 0,
                    'empty' => ($zone->getRuinDigs() == 0),
                    'blueprint' => $zone->getBlueprint(),
                ];
            }
        }

        if ($zone->getDiscoveryStatus() && $zone->getZombieStatus() === Zone::ZombieStateEstimate) {
            if ($zone->getZombies() == 0) {
                $attributes['danger'] = '0';
            }
            else if ($zone->getZombies() <= 2) {
                $attributes['danger'] = '1';
            }
            else if ($zone->getZombies() <= 5) {
                $attributes['danger'] = '2';
            }
            else {
                $attributes['danger'] = '3';
            }
        }

        return $attributes;
    }

    public function getZoneKm(Zone $zone): int {
        return round(sqrt( pow($zone->getX(),2) + pow($zone->getY(),2) ));
    }

    public function getZoneAp(Zone $zone): int {
        return abs($zone->getX()) + abs($zone->getY());
    }

    public function getDigGroupEventName(): ?string {
        /*// Test for easter
        $year = date('Y');
        $base = new \DateTime("$year-03-21");
        $days = easter_days($year);
        $endEaster = new \DateTime("$year-03-21");
        $endEaster = $endEaster->add(new \DateInterval("P{$days}D"));
        $beginEaster = $base->add(new \DateInterval("P" . ($days-2) . "D"));

        $now = new \DateTime();
        if($now >= $beginEaster && $now <= $endEaster)
            return 'easter_dig';

        // Test for christmas
        if($now >= new \DateTime("$year-12-20") && $now <= new \DateTime("$year-12-25"))
            return 'christmas_dig';
*/
        return null;
    }

    public function getZonesWithExplorableRuin($zones): array {
        $explorable_zones = [];
        foreach ($zones as $zone) {
            /** @var Zone $zone */
            if ($zone->getPrototype() && $zone->getPrototype()->getExplorable()) {
                $explorable_zones[] = $zone;
            }
        }

        return $explorable_zones;
    }
}