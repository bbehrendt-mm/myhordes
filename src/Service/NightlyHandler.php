<?php
namespace App\Service;

use App\Entity\Building;
use App\Entity\CauseOfDeath;
use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\CitizenRole;
use App\Entity\CitizenStatus;
use App\Entity\CitizenVote;
use App\Entity\CitizenWatch;
use App\Entity\CouncilEntryTemplate;
use App\Entity\EscapeTimer;
use App\Entity\Gazette;
use App\Entity\GazetteEntryTemplate;
use App\Entity\GazetteLogEntry;
use App\Entity\HeroicActionPrototype;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemGroup;
use App\Entity\ItemInfoAttachment;
use App\Entity\ItemPrototype;
use App\Entity\HeroSkillPrototype;
use App\Entity\LogEntryTemplate;
use App\Entity\PictoPrototype;
use App\Entity\PrivateMessage;
use App\Entity\SpecialActionPrototype;
use App\Entity\Town;
use App\Entity\TownLogEntry;
use App\Entity\TownRankingProxy;
use App\Entity\ZombieEstimation;
use App\Entity\Zone;
use App\Entity\ZoneTag;
use App\Structures\EventConf;
use App\Structures\ItemRequest;
use App\Structures\MyHordesConf;
use App\Structures\TownConf;
use App\Structures\TownDefenseSummary;
use DateInterval;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NightlyHandler
{
    private array $cleanup = [];
    private array $skip_reanimation = [];
    private array $skip_infection = [];
    private bool $exec_firework = false;
    private ?Building $upgraded_building = null;
    private bool $exec_reactor = false;
    private array $deferred_log_entries = [];

    private EntityManagerInterface $entity_manager;
    private LoggerInterface $log;
    private CitizenHandler $citizen_handler;
    private RandomGenerator $random;
    private DeathHandler $death_handler;
    private TownHandler $town_handler;
    private ZoneHandler $zone_handler;
    private InventoryHandler $inventory_handler;
    private PictoHandler $picto_handler;
    private ItemFactory $item_factory;
    private LogTemplateHandler $logTemplates;
    private ConfMaster $conf;
    private ActionHandler $action_handler;
    private MazeMaker $maze;
    private CrowService $crow;
    private UserHandler $user_handler;
    private GameFactory $game_factory;
    private GazetteService $gazette_service;
    private GameProfilerService $gps;
    private TimeKeeperService $timeKeeper;

    public function __construct(EntityManagerInterface $em, LoggerInterface $log, CitizenHandler $ch, InventoryHandler $ih,
                              RandomGenerator $rg, DeathHandler $dh, TownHandler $th, ZoneHandler $zh, PictoHandler $ph,
                              ItemFactory $if, LogTemplateHandler $lh, ConfMaster $conf, ActionHandler $ah, MazeMaker $maze,
                              CrowService $crow, UserHandler $uh, GameFactory $gf, GazetteService $gs, GameProfilerService $gps,
                              TimeKeeperService $timeKeeper )
    {
        $this->entity_manager = $em;
        $this->citizen_handler = $ch;
        $this->death_handler = $dh;
        $this->inventory_handler = $ih;
        $this->random = $rg;
        $this->town_handler = $th;
        $this->zone_handler = $zh;
        $this->picto_handler = $ph;
        $this->item_factory = $if;
        $this->log = $log;
        $this->logTemplates = $lh;
        $this->conf = $conf;
        $this->action_handler = $ah;
        $this->maze = $maze;
        $this->crow = $crow;
        $this->user_handler = $uh;
        $this->game_factory = $gf;
        $this->gazette_service = $gs;
        $this->gps = $gps;
        $this->timeKeeper = $timeKeeper;
    }

    private function check_town(Town $town): bool {
        if ($town->isOpen()) {
            $this->log->debug('The town lobby is <comment>open</comment>!');
            if ($town->getCitizenCount() > 0) $this->entity_manager->persist($this->logTemplates->nightlyAttackCancelled($town));
            return false;
        }

        $alive = false;
        foreach ($town->getCitizens() as $citizen)
            if ($citizen->getAlive()) {
                $alive = true;
                break;
            }

        if (!$alive) {
            $this->log->debug('The town has <comment>no</comment> living citizen!');
            return false;
        }

        $this->gazette_service->check_gazettes($town);

        return true;
    }

    private function kill_wrap( Citizen &$citizen, CauseOfDeath $cod, bool $skip_reanimation = false, int $zombies = 0, $skip_log = false, ?int $day = null ) {
        $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> dies of <info>{$cod->getLabel()}</info>.");
        $this->death_handler->kill($citizen,$cod,$rr);

        if (!$skip_log) $this->entity_manager->persist( $this->logTemplates->citizenDeath( $citizen, $zombies, null, $day ) );
        foreach ($rr as $r) $this->cleanup[] = $r;
        if ($skip_reanimation) $this->skip_reanimation[] = $citizen->getId();
    }

    private function stage0_stranger(Town $town) {
        $stranger_ts = $this->timeKeeper->getCurrentAttackTime()->sub(DateInterval::createFromDateString('1sec'));

        $stranger_ap = $town->getStrangerPower() * 6 + mt_rand( 0, $town->getStrangerPower() * 3 );
        $this->log->debug( "The stranger's power of <info>{$town->getStrangerPower()}</info> grants him <info>{$stranger_ap} AP</info>." );

        // Partition zones
        $close_zones = []; $medium_zones = []; $far_zones = [];
        foreach ( $town->getZones() as $zone )
            if     ($zone->getApDistance() <= 1) $close_zones[] = $zone;
            elseif ($zone->getApDistance() <= 6) $medium_zones[] = $zone;
            elseif ($zone->getApDistance() <= 9) $far_zones[] = $zone;

        $ap_for_digging = max(0, min(mt_rand( floor($stranger_ap * 0.4), ceil( $stranger_ap * 0.6 ) ), $stranger_ap));
        $ap_for_building = $stranger_ap - $ap_for_digging;
        $this->log->debug( "The stranger will use <info>{$ap_for_digging} AP</info> for scavenging and <info>{$ap_for_building} AP</info> on the construction site." );

        // Digging
        $empty_group = $this->entity_manager->getRepository(ItemGroup::class)->findOneBy(['name' => 'empty_dig']);
        $base_group = $this->entity_manager->getRepository(ItemGroup::class)->findOneBy(['name' => 'base_dig']);
        $items_found = [];

        for ($i = 0; $i < $ap_for_digging; $i++) {
            /** @var Zone $zone */
            $zone = $this->random->pick( $this->random->pickEntryFromRawRandomArray( [ [$close_zones, 1], [$medium_zones, 2], [$far_zones, 1] ] ) );
            if ($zone && $zone->getDigs() > 0 && $this->random->chance( 0.6 )) {
                $items_found[] = $this->random->pickItemPrototypeFromGroup( $base_group );
                $zone->setDigs( $zone->getDigs() - 1 );
            } elseif ($zone && $zone->getDigs() <= 0 && $this->random->chance( 0.3 ))
                $items_found[] = $this->random->pickItemPrototypeFromGroup( $empty_group );
        }

        $this->log->debug( 'The stranger has found <info>' . count($items_found) . '</info> items.' );
        foreach ($items_found as $item) {
            $this->inventory_handler->forceMoveItem( $town->getBank(), $this->item_factory->createItem($item) );
            $this->entity_manager->persist( $this->logTemplates->strangerBankItemLog( $town, $item, $stranger_ts ) );
        }

        // Building
        $all_res = [];
        foreach ($town->getBank()->getItems() as $bank_item) {
            if (!isset( $all_res[ $bank_item->getPrototype()->getName() ] ))
                $all_res[ $bank_item->getPrototype()->getName() ] = 0;

            if (!$bank_item->getBroken() && !$bank_item->getPoison()->poisoned())
                $all_res[ $bank_item->getPrototype()->getName() ] += $bank_item->getCount();
        }


        $repair_sites = [];
        $available_sites = [];
        $enable_log = $this->town_handler->getBuilding($town, 'item_rp_book2_#00', true);
        foreach ($town->getBuildings() as $building) {
            if ($building->getComplete() && $building->getHp() < $building->getPrototype()->getHp()) {
                $repair_sites[] = $building;
            } elseif ($building->getComplete() || $building->getAp() >= ($building->getPrototype()->getAp() - 1)) continue;

            // Check if all parent buildings are completed
            $parents_complete = true;
            $current = $building->getPrototype();
            while ($parent = $current->getParent()) {
                if (!$this->town_handler->getBuilding($town, $parent, true))
                    $parents_complete = false;
                $current = $parent;
            }
            if (!$parents_complete) continue;

            // Get all resources needed for this building
            $res = [];
            if (!$building->getComplete() && $building->getPrototype()->getResources())
                foreach ($building->getPrototype()->getResources()->getEntries() as $entry)
                    if (!isset($res[ $entry->getPrototype()->getName() ]))
                        $res[ $entry->getPrototype()->getName() ] = new ItemRequest( $entry->getPrototype()->getName(), $entry->getChance(), false, false, false );
                    else $res[ $entry->getPrototype()->getName() ]->addCount( $entry->getChance() );

            // If the building needs resources, check if they are present in the bank; otherwise fail
            $has_res = true;
            foreach ($res as $key => $resource) if ($resource->getCount() > 0)
                if (!isset($all_res[ $key ]) || $all_res[$key] < $resource->getCount())
                    $has_res = false;

            if ($has_res) $available_sites[] = $building;
        }

        shuffle($repair_sites);
        foreach ($repair_sites as $building) if ($ap_for_building > 0) {
            $required = ceil(($building->getPrototype()->getHp() - $building->getHp()) / 2);
            $invest = min( $required, $ap_for_building );

            if ($invest > 0) {
                $building->setHp( min($building->getHp() + 2 * $invest, $building->getPrototype()->getHp()) );
                $this->log->debug( "The stranger invests <info>{$invest} AP</info> into repairing <info>{$building->getPrototype()->getLabel()}</info>." );
                if ($enable_log) $this->entity_manager->persist( $this->logTemplates->strangerConstructionsInvestRepair( $town, $building, $stranger_ts ) );
            }

            $ap_for_building -= $invest;
        }

        if ($ap_for_building > 0) {
            $order_cache = [];
            foreach ($town->getCitizens() as $citizen) if ($citizen->getActive() && $citizen->getBuildingVote()) {
                $bid = $citizen->getBuildingVote()->getBuilding()->getId();
                if (!isset($order_cache[$bid])) $order_cache[$bid] = 0;
                $order_cache[$bid]++;
            }

            shuffle($available_sites);
            usort( $available_sites, fn(Building $a, Building $b) => ( $order_cache[$b->getId()] ?? 0 ) <=> ( $order_cache[$a->getId()] ?? 0 ) );

            foreach ($available_sites as $building) if ($ap_for_building > 0) {

                $required = $building->getPrototype()->getAp() - $building->getAp() ;
                $invest = min( $required - 1, $ap_for_building );

                if ($invest > 0) {
                    $building->setAp( min($building->getAp() + $invest, $building->getPrototype()->getAp() - 1) );
                    $this->log->debug( "The stranger invests <info>{$invest} AP</info> into constructing <info>{$building->getPrototype()->getLabel()}</info>." );
                    if ($enable_log) $this->entity_manager->persist( $this->logTemplates->strangerConstructionsInvest( $town, $building->getPrototype(), $stranger_ts ) );
                }

                $ap_for_building -= $invest;
            }
        }

    }

    private function stage1_prepare(Town $town) {
        // Initialize spiritual guide on D1
        $town_conf = $this->conf->getTownConfiguration($town);
        if ($town_conf->get(TownConf::CONF_GUIDE_ENABLED, false)) {

            $this->log->debug( "This town is eligible for the <info>spiritual guide</info> picto, checking citizens..." );

            // When the guide is enabled and enough citizens are below the SP threshold...
            $th = $town_conf->get(TownConf::CONF_GUIDE_SP_LIMIT, 100);
            if ($town->getCitizens()->filter(function (Citizen $c) use ($th) {
                return $this->user_handler->fetchSoulPoints( $c->getUser(), true, true ) < $th;
            })->count() >= ($town_conf->get(TownConf::CONF_GUIDE_CTC_LIMIT, 0.5) * $town->getPopulation()))

                // Each citizen above the threshold gets assigned the potential guide status
                foreach ($town->getCitizens()->filter(function (Citizen $c) use ($th) {
                    return $this->user_handler->fetchSoulPoints( $c->getUser(), true, true ) >= $th;
                }) as $spiritual_guide) {
                    $this->citizen_handler->inflictStatus( $spiritual_guide, 'tg_spirit_guide' );
                    $this->log->debug( "Registered <info>{$spiritual_guide->getName()}</info> as potential spiritual leader." );
                }
            else {
                // Remove guide status from all citizens
                foreach ($town->getCitizens() as $citizen)
                    $this->citizen_handler->removeStatus( $citizen, 'tg_spirit_guide' );
                $this->log->debug("Not enough citizen are below <info>$th SP</info>.");
            }
        }
    }

    private function stage1_vanish(Town $town) {
        $this->log->info('<info>Vanishing citizens</info> ...');
        $cod = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::Vanished]);

        $camp_1 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_hide']);
        $camp_2 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_tomb']);

        foreach ($town->getCitizens() as $citizen)
            if ($citizen->getAlive() && $citizen->getZone()) {
                $zone = $citizen->getZone();
                $cp_ok = $this->zone_handler->check_cp($zone);

                // We force the auto-search to be processed
                $this->zone_handler->updateZone($zone);

                $citizen_hidden = $citizen->getStatus()->contains( $camp_1 ) || $citizen->getStatus()->contains( $camp_2 );
                if ($citizen_hidden) {
                    // This poor soul wants to camp outside.
                    $survival_chance = $citizen->getCampingChance();

                    if (!$this->random->chance($survival_chance)) {
                        $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> was at <info>{$citizen->getZone()->getX()}/{$citizen->getZone()->getY()}</info> and died while camping (survival chance was " . ($survival_chance * 100) . "%)!");
                        $this->kill_wrap($citizen, $cod, false, 0, true, $town->getDay()+1);
                    }
                    else {
                        $citizen->setCampingCounter($citizen->getCampingCounter() + 1);
                        $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> survived camping at <info>{$citizen->getZone()->getX()}/{$citizen->getZone()->getY()}</info> with a survival chance of <info>" . ($survival_chance * 100) . "%</info>.");

                        // Grant blueprint if first camping on a ruin.
                        if ($citizen->getZone()->getBlueprint() === Zone::BlueprintAvailable && $citizen->getZone()->getBuryCount() <= 0) {
                            // Spawn BP.
                            $bp_name = ($this->zone_handler->getZoneKm($citizen->getZone()) < 10)
                                ? 'bplan_u_#00'
                                : 'bplan_r_#00';
                            $bp_item_prototype = $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => $bp_name]);
                            $bp_item = $this->item_factory->createItem( $bp_item_prototype );
                            $citizen->getZone()->getFloor()->addItem($bp_item);
                            // Set zone blueprint.
                            $citizen->getZone()->setBlueprint(Zone::BlueprintFound);
                            $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> dropped a blueprint <info>{$bp_name}</info>.");

                        }
                    }
                }
                else {
                  $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> is at <info>{$citizen->getZone()->getX()}/{$citizen->getZone()->getY()}</info> without protection!");
                  $this->kill_wrap($citizen, $cod, false, 0, true, $town->getDay()+1);
                }

                $this->zone_handler->handleCitizenCountUpdate($zone, $cp_ok);
            }
    }

    private function stage1_status(Town $town) {
        $this->log->info('<info>Processing status-related deaths</info> ...');
        $cod_thirst = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::Dehydration]);
        $cod_addict = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::Addiction]);
        $cod_infect = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::Infection]);
        $cod_ghoul  = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::GhulStarved]);

        $status_infected  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'infection']);
        $status_survive   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'hsurvive']);
        $status_thirst2   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'thirst2']);
        $status_drugged   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'drugged']);
        $status_addicted  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'addict']);

        foreach ($town->getCitizens() as $citizen) {

            if (!$citizen->getAlive()) continue;

            $ghoul = $citizen->hasRole('ghoul');

            if ($citizen->getStatus()->contains( $status_survive )) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> is <info>protected</info> by <info>{$status_survive->getLabel()}</info>." );
                continue;
            }

            if ($citizen->getStatus()->contains( $status_thirst2 ) && !$ghoul) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>{$status_thirst2->getLabel()}</info>." );
                $this->kill_wrap( $citizen, $cod_thirst, true, 0, false, $town->getDay()+1 );
                continue;
            }

            if ($citizen->getStatus()->contains( $status_addicted ) && !$citizen->getStatus()->contains( $status_drugged )) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>{$status_addicted->getLabel()}</info>, but not <info>{$status_drugged->getLabel()}</info>." );
                $this->kill_wrap( $citizen, $cod_addict, true, 0, false, $town->getDay()+1 );
                continue;
            }

            if ($citizen->getStatus()->contains( $status_infected ) && !$ghoul) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>{$status_infected->getLabel()}</info>." );
                if ($this->random->chance($this->conf->getTownConfiguration($town)->get( TownConf::CONF_MODIFIER_INFECT_DEATH, 0.5 ))) $this->kill_wrap( $citizen, $cod_infect, true, 0, false, $town->getDay()+1 );
                continue;
            }

            if ($ghoul && $citizen->getGhulHunger() > 40) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> is a <info>hungry ghoul</info>." );
                $this->kill_wrap( $citizen, $cod_ghoul, true, 0, false, $town->getDay()+1 );
                continue;
            }
        }
    }

    private function stage2_pre_attack_buildings(Town &$town){
        $this->log->info('<info>Processing building before the attack</info> ...');

        $reactor = $this->town_handler->getBuilding($town, 'small_arma_#00', true);
        $cod = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::Radiations]);

        if ($reactor) {
            $damages = mt_rand(50, 125);
            $this->gps->recordBuildingDamaged( $reactor->getPrototype(), $town, min( $damages, $reactor->getHp() ) );
            $reactor->setHp(max(0, $reactor->getHp() - $damages));

            $newDef = round(max(0, $reactor->getPrototype()->getDefense() * $reactor->getHp() / $reactor->getPrototype()->getHp()));

            $this->log->debug("The <info>reactor</info> has taken <info>$damages</info> damages. It now has $newDef defense...");

            $reactor->setDefense($newDef);

            if($reactor->getHp() <= 0){

                $gazette = $town->findGazette( $town->getDay(), true );
                $this->deferred_log_entries[] = $this->logTemplates->constructionsDestroy($town, $reactor->getPrototype(), $damages );
                $this->town_handler->destroy_building($town, $reactor);
                $this->gps->recordBuildingDestroyed( $reactor->getPrototype(), $town, 'attack' );

                $this->log->debug("The reactor is destroyed. Everybody dies !");

                // It is destroyed, let's kill everyone with the good cause of death
                foreach ($this->town_handler->get_alive_citizens($town) as $citizen) {
                    $gazette->setDeaths($gazette->getDeaths() + 1);
                    $this->kill_wrap($citizen, $cod, true, 0, true, $town->getDay());
                }

                $gazette->setReactorExplosion(true);
                $this->exec_reactor = true;
                $this->entity_manager->persist($gazette);

            } else {
                $this->deferred_log_entries[] = $this->logTemplates->constructionsDamage($town, $reactor->getPrototype(), $damages );
            }
        }
    }

    private function stage2_building_effects(Town $town) {
        $this->log->info('<info>Processing building functions</info> ...');

        if (!$town->getDevastated()) {
            $buildings = []; $max_votes = -1;
            foreach ($town->getBuildings() as $b) if ($b->getComplete())
                if ($b->getPrototype()->getMaxLevel() > 0 && $b->getPrototype()->getMaxLevel() > $b->getLevel()) {
                    $v = $b->getDailyUpgradeVotes()->count();
                    $this->log->debug("<info>{$v}</info> citizens voted for <info>{$b->getPrototype()->getLabel()}</info>.");
                    if ($v > $max_votes) {
                        $buildings = [$b];
                        $max_votes = $v;
                    } elseif ($v === $max_votes) $buildings[] = $b;
                }


            if (!empty($buildings) && $max_votes > 0) {
                /** @var Building $target_building */
                $this->upgraded_building = $target_building = $this->random->pick( $buildings );
                $target_building->setLevel( $target_building->getLevel() + 1 );

                $this->log->debug("Increasing level of <info>{$target_building->getPrototype()->getLabel()}</info> to Level <info>{$target_building->getLevel()}</info>.");

                switch ($target_building->getPrototype()->getName()) {
                    case 'small_gather_#00':
                        $def_add = [0,13,21,32,33,51];
                        $target_building->setDefenseBonus( $target_building->getDefenseBonus() + $def_add[ $target_building->getLevel() ] );
                        $this->log->debug("Leveling up <info>{$target_building->getPrototype()->getLabel()}</info>: Increasing variable defense by <info>{$def_add[ $target_building->getLevel() ] }</info>.");
                        break;
                    case 'small_water_#00':
                        $water_add = [5,20,20,30,30,40];
                        $town->setWell( $town->getWell() + $water_add[$target_building->getLevel()] );
                        $this->entity_manager->persist( $this->logTemplates->nightlyAttackUpgradeBuildingWell( $target_building, $water_add[$target_building->getLevel()] ) );

                        $this->log->debug("Leveling up <info>{$target_building->getPrototype()->getLabel()}</info>: Increasing well count by <info>{$water_add[ $target_building->getLevel() ] }</info>.");
                        break;
                    case 'item_home_def_#00':
                        $def_add = [0,30,35,50,65,80];
                        $target_building->setDefenseBonus( $target_building->getDefenseBonus() + $def_add[ $target_building->getLevel() ] );
                        $this->log->debug("Leveling up <info>{$target_building->getPrototype()->getLabel()}</info>: Increasing variable defense by <info>{$def_add[ $target_building->getLevel() ] }</info>.");
                        break;
                    case 'item_tube_#00':
                        $def_mul = [0, 0.8, 1.6, 2.4, 3.2, 4];
                        $target_building->setDefenseBonus( $target_building->getDefense() * $def_mul[ $target_building->getLevel() ] );
                        $this->log->debug("Leveling up <info>{$target_building->getPrototype()->getLabel()}</info>: Increasing variable defense by <info>{$def_mul[ $target_building->getLevel() ] }</info>.");
                        break;
                }
            }
        }

        $watertower = $this->town_handler->getBuilding( $town, 'item_tube_#00', true );
        if ($watertower && $watertower->getLevel() > 0) {
            $n = [0,2,4,6,9,12];
            if ($town->getWell() >= $n[ $watertower->getLevel() ]) {
                $town->setWell( $town->getWell() - $n[ $watertower->getLevel() ] );
                $gazette = $town->findGazette( $town->getDay(), true );
                $gazette->setWaterlost($gazette->getWaterlost() + $n[$watertower->getLevel()]);
                $this->entity_manager->persist($gazette);
                $this->entity_manager->persist( $this->logTemplates->nightlyAttackBuildingDefenseWater( $watertower, $n[ $watertower->getLevel() ] ) );
                $this->log->debug( "Deducting <info>{$n[$watertower->getLevel()]}</info> water from the well to operate the <info>{$watertower->getPrototype()->getLabel()}</info>." );
            } else {
                $watertower->setTempDefenseBonus(0 - $watertower->getDefense() - $watertower->getDefenseBonus());
            }
        }
    }

    private function stage2_post_attack_building_effects(Town $town) {
        $this->log->info('<info>Processing post-attack building functions</info> ...');

        $spawn_default_blueprint = $this->town_handler->getBuilding($town, 'small_refine_#01', true) !== null;

        $gazette = $town->findGazette( $town->getDay(), true );
        if (!$town->getDevastated()) {

            if ($this->upgraded_building !== null) {

                switch ($this->upgraded_building->getPrototype()->getName()) {
                    case 'small_refine_#01':
                        $spawn_default_blueprint = $this->upgraded_building->getLevel() === 1;
                        $bps = [
                            ['bplan_c_#00' => 1],
                            ['bplan_c_#00' => 4],
                            ['bplan_c_#00' => 2,'bplan_u_#00' => 2],
                            ['bplan_u_#00' => 2,'bplan_r_#00' => 2],
                        ];
                        $opt_bp = [null,null,'bplan_r_#00','bplan_e_#00'];

                        $plans = [];
                        foreach ($bps[$this->upgraded_building->getLevel()] as $id => $count) {
                            $proto = $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => $id]);
                            $plans[$proto->getId()] = [
                                'item' => $proto,
                                'count' => $count
                            ];
                        }
                        if ( $opt_bp[$this->upgraded_building->getLevel()] !== null && $this->random->chance( 0.5 ) ) {
                            $proto = $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => $opt_bp[$this->upgraded_building->getLevel()]]);
                            if(isset($plans[$proto->getId()])) {
                                $plans[$proto->getId()]['count'] += 1;
                            }
                            else {
                                $plans[] = [
                                    'item' => $proto,
                                    'count' => 1
                                ];
                            }
                        }


                        $tx = [];
                        foreach ($plans as $plan) {
                            for($i = 0; $i < $plan['count'] ; $i++)
                                $this->inventory_handler->forceMoveItem( $town->getBank(), $this->item_factory->createItem($plan['item']) );
                            $tx[] = "<info>{$plan['item']->getLabel()} x{$plan['count']}</info>";
                        }
                        if (!$gazette->getReactorExplosion())
                            $this->entity_manager->persist( $this->logTemplates->nightlyAttackUpgradeBuildingItems( $this->upgraded_building, $plans ));
                        $this->log->debug("Leveling up <info>{$this->upgraded_building->getPrototype()->getLabel()}</info>: Placing " . implode(', ', $tx) . " in the bank.");
                        break;
                }
            }
        }

        $daily_items = []; $tx = [];
        if ($spawn_default_blueprint) {
            if (!$gazette->getReactorExplosion())
                $this->entity_manager->persist( $this->logTemplates->nightlyAttackProductionBlueprint( $town, $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'bplan_c_#00']), $this->town_handler->getBuilding($town, 'small_refine_#01')->getPrototype()));
            $daily_items['bplan_c_#00'] = 1;
        }

        $has_fertilizer = $this->town_handler->getBuilding( $town, 'item_digger_#00', true ) !== null;

        $db = [
            'small_appletree_#00'      => [ 'apple_#00' => mt_rand(3,5) ],
            'item_vegetable_tasty_#00' => [ 'vegetable_#00' => !$has_fertilizer ? mt_rand(4,7) : mt_rand(6,8), 'vegetable_tasty_#00' => !$has_fertilizer ? mt_rand(0,2) : mt_rand(3,5) ],
            'item_bgrenade_#01'        => [ 'boomfruit_#00' => !$has_fertilizer ? mt_rand(3,7) : mt_rand(5,8) ],
            'small_chicken_#00'        => [ 'egg_#00' => 3 ],
        ];

        foreach ($db as $b_class => $spawn)
            if (($b = $this->town_handler->getBuilding( $town, $b_class, true )) !== null) {
                $local = [];
                foreach ( $spawn as $item_id => $count ) {
                    if (!isset($daily_items[$item_id])) $daily_items[$item_id] = $count;
                    else $daily_items[$item_id] += $count;
                    if ($count > 0) $local[] = ['item' => $item_id, 'count' => $count];
                }
                $this->entity_manager->persist( $this->logTemplates->nightlyAttackProduction( $b, array_map( function($e) {
                    return [ 'item' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName($e['item']), 'count' => $e['count'] ];
                }, $local ) ) );
            }


        foreach ($daily_items as $item_id => $count)
            for ($i = 0; $i < $count; $i++) {
                $item = $this->item_factory->createItem( $item_id );
                $this->inventory_handler->forceMoveItem( $town->getBank(), $item );
                $tx[] = "<info>{$item->getPrototype()->getLabel()}</info>";
            }

        if (!empty($daily_items))
            $this->log->debug("Daily items: Placing " . implode(', ', $tx) . " in the bank.");
    }

    private function stage2_day(Town $town) {
        $this->log->info('<info>Updating survival information</info> ...');
        foreach ($town->getCitizens() as $citizen) {
            if (!$citizen->getAlive()) continue;

            // Spiritual leader
            if ($this->citizen_handler->hasStatusEffect($citizen, 'tg_spirit_guide')) {
                $c = 0;
                for ($d = 1; $d < $town->getDay(); $d++) $c += $d;
                $this->picto_handler->give_picto($citizen, 'r_guide_#00', $c);
            }

            if (!$citizen->getProfession()->getHeroic())
                continue;

            // Check hero skills
            $nextSkill = $this->entity_manager->getRepository(HeroSkillPrototype::class)->getNextUnlockable($citizen->getUser()->getAllHeroDaysSpent());

            $citizen->getUser()->setHeroDaysSpent($citizen->getUser()->getHeroDaysSpent() + 1);

            if($nextSkill !== null && $citizen->getUser()->getAllHeroDaysSpent() >= $nextSkill->getDaysNeeded()){
                $this->log->info("Citizen <info>{$citizen->getUser()->getUsername()}</info> has unlocked a new skill : <info>{$nextSkill->getTitle()}</info>");

                $null = null;

                switch($nextSkill->getName()){
                    case "brothers":
                        //TODO: add the heroic power
                        break;
                    case "largechest1":
                    case "largechest2":
                        $citizen->getHome()->setAdditionalStorage($citizen->getHome()->getAdditionalStorage() + 1);
                        break;
                    case "secondwind":
                        $heroic_action = $this->entity_manager->getRepository(HeroicActionPrototype::class)->findOneBy(['name' => "hero_generic_ap"]);
                        $citizen->addHeroicAction($heroic_action);
                        break;
                    case "cheatdeath":
                        $heroic_action = $this->entity_manager->getRepository(HeroicActionPrototype::class)->findOneBy(['name' => "hero_generic_immune"]);
                        $citizen->addHeroicAction($heroic_action);
                        break;
                    case 'luckyfind':
                        $oldfind = $this->entity_manager->getRepository(HeroicActionPrototype::class)->findOneBy(['name' => "hero_generic_find"]);
                        if($citizen->getHeroicActions()->contains($oldfind)) {
                            // He didn't used the Find, we replace it with the lucky find
                            $citizen->removeHeroicAction($oldfind);
                            $newfind = $this->entity_manager->getRepository(HeroicActionPrototype::class)->findOneBy(['name' => "hero_generic_find_lucky"]);
                            $citizen->addHeroicAction($newfind);
                        }
                        break;
                }
                $this->entity_manager->persist($citizen);
                $this->entity_manager->persist($citizen->getHome());
            }
        }
    }

    private function stage2_surprise_attack(Town $town) {
        $this->log->info('<info>Awakening the dead</info> ...');
        /** @var Citizen[] $houses */
        $houses = [];
        /** @var Citizen[] $targets */
        $targets = [];
        /** @var Building[] $buildings */
        $buildings = [];

        $cod = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::NightlyAttack]);

        foreach ($town->getCitizens() as $citizen) {
            /** @var Citizen $citizen */
            if ($citizen->getAlive() && !$citizen->getZone())
                $targets[] = $citizen;
            elseif (!$citizen->getAlive() && $citizen->getHome()->getHoldsBody() && !in_array($citizen->getId(), $this->skip_reanimation))
                $houses[] = $citizen;
        }
        foreach ($town->getBuildings() as $building)
            if ($building->getAp() > 0 && !$building->getComplete())
                $buildings[] = $building;

        $this->log->debug( '<info>' . count($houses) . '</info> corpses have been reanimated!' );
        $targets = $this->random->pick($targets, min(count($houses),count($targets)), true);
        $buildings = $this->random->pick($buildings, min(count($houses),count($buildings)), true);

        if(count($houses) > 0){
            $this->entity_manager->persist( $this->logTemplates->nightlyInternalAttackStart($town) );
        }

        $gazette = $town->findGazette( $town->getDay(), true );
        $useless = 0;

        foreach ($houses as $id => $corpse) {
            /** @var Citizen $corpse */

            $opts = [];
            $opts[] = 0;
            if (!empty( $targets )) $opts[] = 1;
            if (!empty( $buildings )) $opts[] = 2;
            if ($town->getWell() > 0) $opts[] = 3;

            switch ($this->random->pick($opts, 1)) {
                case 0:
                    $useless++;
                    $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> has nothing to do.");
                    $this->entity_manager->persist( $this->logTemplates->nightlyInternalAttackNothing( $corpse ) );
                    break;
                case 1:
                    $victim = array_pop($targets);
                    $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> attacks and kills <info>{$victim->getUser()->getUsername()}</info>.");
                    $this->entity_manager->persist( $this->logTemplates->nightlyInternalAttackKill( $corpse, $victim ) );
                    $corpse->setHasEaten(true);
                    $this->entity_manager->persist($corpse);
                    $this->kill_wrap( $victim, $cod, false, 1, true );
                    break;
                case 2:
                    $construction = array_pop($buildings);
                    $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> destroys the progress on <info>{$construction->getPrototype()->getLabel()}</info>.");
                    $this->entity_manager->persist( $this->logTemplates->nightlyInternalAttackDestroy( $corpse, $construction ) );
                    $construction->setAp(0);
                    break;
                case 3:
                    $d = min($town->getWell(), 20);
                    $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> removes <info>{$d} water rations</info> from the well.");
                    // Disabled because in Hordes, this is not shown as lost
                    // $gazette->setWaterlost($gazette->getWaterlost() + $d);
                    if($d > 0){
                        $this->entity_manager->persist( $this->logTemplates->nightlyInternalAttackWell( $corpse, $d ) );
                        $town->setWell( $town->getWell() - $d );
                    }
                    break;
            }
        }

        if ($useless > 0 && $useless === count($houses) && !$town->getDevastated() )
            $this->entity_manager->persist( $this->logTemplates->nightlyInternalAttackNothingSummary( $town, $useless ) );
        else if ($useless > 0 && $town->getDevastated())
            $this->entity_manager->persist( $this->logTemplates->nightlyInternalAttackNothingSummary( $town, $useless, true ) );

        $this->entity_manager->persist($gazette);
    }

    private function stage2_attack(Town &$town) {
        $this->log->info('<info>Marching the horde</info> ...');
        $cod = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneBy(['ref' => CauseOfDeath::NightlyAttack]);
        $status_terror  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'terror']);

        // Do not enable this effect for now until we know how it is handled on Hordes
        $has_kino = false;//$this->town_handler->getBuilding($town, 'small_cinema_#00', true);

        // Day already advanced, let's get today's gazette!
        /** @var Gazette $gazette */
        $gazette = $town->findGazette( $town->getDay(), true );
        $gazette->setDoor($town->getDoor());

        /** @var TownDefenseSummary|null $def_summary */
        $def_summary = null;
        $gazette->setDefense($def = $town->getDevastated() ? 0 : $this->town_handler->calculate_town_def( $town, $def_summary ));

        /** @var ZombieEstimation $est */
        $est = $this->entity_manager->getRepository(ZombieEstimation::class)->findOneByTown($town,$town->getDay()-1);
        $zombies = $est ? $est->getZombies() : 0;

        $redsouls = $this->town_handler->get_red_soul_count($town);
        $soulFactor = min(1 + (0.04 * $redsouls), (float)$this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_RED_SOUL_FACTOR, 1.2));

        $zombies *= $soulFactor;
        $zombies = round($zombies);
        $gazette->setAttack($zombies);

        $overflow = !$town->getDoor() ? max(0, $zombies - $def) : $zombies;
        $this->log->debug("The town has <info>{$def}</info> defense and is attacked by <info>{$zombies}</info> Zombies (<info>{$est->getZombies()}</info> x <info>{$soulFactor}</info>, from <info>{$redsouls}</info> red souls). The door is <info>" . ($town->getDoor() ? 'open' : 'closed') . "</info>!", $def_summary ? $def_summary->toArray() : []);
        $this->log->debug("<info>{$overflow}</info> Zombies have entered the town!");

        $gazette->setInvasion($overflow);

        $this->entity_manager->persist( $this->logTemplates->nightlyAttackBegin($town, $zombies) );

        foreach ($this->deferred_log_entries as $deferred_log_entry)
            $this->entity_manager->persist( $deferred_log_entry );

        $this->log->debug("Getting watchers for day " . $town->getDay());

        $has_nightwatch = $this->town_handler->getBuilding($town, 'small_round_path_#00');

        $count_zombified_citizens = 0;
        $count_garbaged_citizens = 0;
        $last_zombified_citizen = null;
        $last_garbaged_citizen = null;
        foreach ($town->getCitizens() as $citizen)
            if (!$citizen->getAlive() && $citizen->getCauseOfDeath()->getRef() === CauseOfDeath::Vanished) {
                $count_zombified_citizens++;
                $last_zombified_citizen = $citizen;
            } elseif (!$citizen->getAlive() && $citizen->getDisposed() === Citizen::Thrown && $citizen->getSurvivedDays() === $town->getDay() - 2) {
                $count_garbaged_citizens++;
                $last_garbaged_citizen = $citizen;
            }

        if ($count_garbaged_citizens > 0)
            $this->entity_manager->persist( $this->logTemplates->nightlyAttackBegin($town, $count_garbaged_citizens, true, $count_garbaged_citizens === 1 ? $last_garbaged_citizen : null, true) );
        elseif ($count_zombified_citizens > 0)
            $this->entity_manager->persist( $this->logTemplates->nightlyAttackBegin($town, $count_zombified_citizens, true, $count_zombified_citizens === 1 ? $last_zombified_citizen : null, false) );

        $this->stage2_surprise_attack($town);

        /** @var CitizenWatch[] $watchers */
        $watchers = $this->entity_manager->getRepository(CitizenWatch::class)->findWatchersOfDay($town, $town->getDay() - 1); // -1 because day has been advanced before stage2

        $inactive_watchers = array_filter( $watchers, fn(CitizenWatch $w) => $w->getCitizen()->getZone() !== null || !$w->getCitizen()->getAlive() );
        $watchers = array_filter( $watchers, fn(CitizenWatch $w) => $w->getCitizen()->getZone() === null && $w->getCitizen()->getAlive() );

        $this->entity_manager->persist( $this->logTemplates->nightlyAttackBegin2($town) );
        $this->entity_manager->persist( $this->logTemplates->nightlyAttackSummary($town, $town->getDoor(), $overflow, count($watchers) > 0 && $has_nightwatch));
        $post_summary = $this->logTemplates->nightlyAttackSummaryPost($town, $town->getDoor(), $overflow, count($watchers) > 0 && $has_nightwatch);
        if ($post_summary) $this->entity_manager->persist( $post_summary );

        if ($overflow > 0 && count($watchers) > 0 && $has_nightwatch)
            $this->entity_manager->persist( $this->logTemplates->nightlyAttackWatchersCount($town, count($watchers)) );

        if(count($watchers) > 0)
            $this->entity_manager->persist($this->logTemplates->nightlyAttackWatchers($town, $watchers));
        else if ($overflow > 0 && $has_nightwatch) {
            $this->entity_manager->persist($this->logTemplates->nightlyAttackNoWatchers($town));
        }

        if ($overflow <= 0 && $count_zombified_citizens > 0)
            $this->entity_manager->persist( $this->logTemplates->nightlyAttackDisappointed($town, $count_zombified_citizens === 1 ? $last_zombified_citizen : null) );

        $def_scale = $def_summary ? $def_summary->overall_scale : 1.0;
        $total_watch_def = floor($this->town_handler->calculate_watch_def($town, $town->getDay() - 1) * $def_scale);
        $this->log->debug("There are <info>".count($watchers)."</info> watchers (with <info>{$total_watch_def}</info> watch defense) in town, against <info>$overflow</info> zombies.");

        $has_shooting_gallery = (bool)$this->town_handler->getBuilding($town, 'small_tourello_#00', true);
        $has_trebuchet        = (bool)$this->town_handler->getBuilding($town, 'small_catapult3_#00', true);
        $has_ikea             = (bool)$this->town_handler->getBuilding($town, 'small_ikea_#00', true);
        $has_armory           = (bool)$this->town_handler->getBuilding($town, 'small_armor_#00', true);

        $wounded_citizens = [];
        $terrorized_citizens = [];
        $no_watch_items_citizens = [];

        foreach ($watchers as $watcher) {
            $defBonus = $overflow > 0 ? floor($this->citizen_handler->getNightWatchDefense($watcher->getCitizen(), $has_shooting_gallery, $has_trebuchet, $has_ikea, $has_armory) * $def_scale) : 0;

            $deathChances = $this->citizen_handler->getDeathChances($watcher->getCitizen(), true);
            $woundOrTerrorChances = $deathChances + $this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_WOUND_TERROR_PENALTY, 0.05);
            $ctz = $watcher->getCitizen();

            $this->log->debug("Watcher {$watcher->getCitizen()->getUser()->getName()} chances are <info>{$deathChances}</info> for death and <info>{$woundOrTerrorChances}</info> for wound or terror.");

            if ($this->random->chance($deathChances)) {
                $this->log->debug("Watcher <info>{$watcher->getCitizen()->getUser()->getUsername()}</info> is now <info>dead</info> because of the watch");
                $skip = false;

                // too sad, he died by falling from the edge
                if ($overflow <= 0) {
                    $this->entity_manager->persist($this->logTemplates->citizenDeathOnWatch($watcher->getCitizen(), 0));
                    $skip = true;
                }

                // Remove all night watch items
                foreach ($ctz->getInventory()->getItems() as $item)
                    if ($item->getPrototype()->getWatchpoint() > 0 || $item->getPrototype()->getName() === 'chkspk_#00') $this->inventory_handler->forceRemoveItem( $item );

                $this->kill_wrap($ctz, $cod, false, ceil($overflow / count($watchers)), $skip);

            } else if($overflow > 0 && $this->random->chance($woundOrTerrorChances)) {

                if($this->random->chance(0.5)){
                    // Wound
                    if (!$this->citizen_handler->isWounded($ctz)) {
                        $this->citizen_handler->inflictWound($ctz);
                        $this->log->debug("Watcher <info>{$ctz->getUser()->getUsername()}</info> is now <info>wounded</info>");
                        $this->skip_infection[] = $ctz->getId();
                        $wounded_citizens[] = $ctz;
                        $this->crow->postAsPM($ctz, '', '', PrivateMessage::TEMPLATE_CROW_NIGHTWATCH_WOUND, $defBonus);
                    }
                } elseif (!$this->town_handler->getBuilding($town, 'small_catapult3_#00', true)) {
                    // Terror
                    if (!$this->citizen_handler->hasStatusEffect($ctz, $status_terror)) {
                        $this->citizen_handler->inflictStatus($ctz, $status_terror);
                        $this->log->debug("Watcher <info>{$ctz->getUser()->getUsername()}</info> now suffers from <info>{$status_terror->getLabel()}</info>");
                        $gazette->setTerror($gazette->getTerror() + 1);
                        $terrorized_citizens[] = $ctz;
                        $this->crow->postAsPM($ctz, '', '', PrivateMessage::TEMPLATE_CROW_NIGHTWATCH_TERROR, $defBonus);
                    }
                }
            }

            if($overflow > 0){
                $this->log->debug("Watcher <info>{$watcher->getCitizen()->getUser()->getUsername()}</info> has stopped <info>$defBonus</info> zombies from his watch");

                $null = null;
                $used_items = 0;
                foreach ($watcher->getCitizen()->getInventory()->getItems() as $item)
                    if ($item->getPrototype()->getNightWatchAction()) {
                        $this->log->debug("Executing night watch action for '<info>{$item->getPrototype()->getLabel()}</info> : '<info>{$item->getPrototype()->getNightWatchAction()->getName()}</info>' held by Watcher <info>{$watcher->getCitizen()->getUser()->getUsername()}</info>.");
                        $this->action_handler->execute( $ctz, $item, $null, $item->getPrototype()->getNightWatchAction(), $msg, $r, true);
                        $used_items++;
                        foreach ($r as $rr) $this->entity_manager->remove($rr);
                    }

                if ($used_items === 0) $no_watch_items_citizens[] = $watcher->getCitizen();

            } else {
                $watcher->setSkipped(true);
                $this->entity_manager->persist($watcher);
            }
        }

        foreach ($inactive_watchers as $inactive_watcher) {
            $inactive_watcher->setSkipped(true);
            $this->entity_manager->persist($inactive_watcher);
        }

        if ($total_watch_def > 0 && $overflow > 0) {
            $this->entity_manager->persist($this->logTemplates->nightlyAttackWatchersZombieStopped($town, min($overflow, $total_watch_def)));
        }

        if (!empty($no_watch_items_citizens))
            foreach ($no_watch_items_citizens as $wctx) $this->entity_manager->persist($this->logTemplates->nightlyAttackWatcherNoItem($town, $wctx));

        if (!empty($wounded_citizens)) {
            foreach ($wounded_citizens as $wctx) $this->entity_manager->persist($this->logTemplates->nightlyAttackWatcherWound($town, $wctx));
            $this->entity_manager->persist($this->logTemplates->nightlyAttackWatcherWound($town, null));
        }

        if (!empty($terrorized_citizens)) {
            foreach ($terrorized_citizens as $tctx) $this->entity_manager->persist($this->logTemplates->nightlyAttackWatcherTerror($town, $tctx));
            $this->entity_manager->persist($this->logTemplates->nightlyAttackWatcherTerror($town, null));
        }

        $initial_overflow = $overflow;

        $overflow = max(0, $overflow - max(0, $total_watch_def));

        if ($overflow > 0 && $total_watch_def > 0) {
            $this->entity_manager->persist($this->logTemplates->nightlyAttackWatchersZombieThrough($town, $overflow));
        } else if ($total_watch_def > 0 && $initial_overflow > 0) {
            $this->entity_manager->persist($this->logTemplates->nightlyAttackWatchersZombieAllStopped($town));
        }

        if ($this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_BUILDING_DAMAGE, false)) {
            // In panda, built buildings get damaged every night
            // Only 20% of the attack is inflicted to buildings
            // zombies - amount of zombies killed by the watch
            $damageInflicted = round(($zombies - ( $initial_overflow - $overflow )) * 0.2);

            $this->log->info("Inflicting <info>$damageInflicted</info> damage to the buildings in town...");

            $targets = [];

            foreach ($town->getBuildings() as $building) {
                // Only built buildings AND buildings with HP can get damaged
                if (!$building->getComplete() || $building->getPrototype()->getHp() <= 0 || $building->getPrototype()->getImpervious()) continue;

                $targets[] = $building;
            }

            shuffle($targets);

            while ($damageInflicted > 0 && !empty($targets)) {
                $target = array_pop($targets);

                //$damages = min($damageInflicted, $target->getHp(), mt_rand(ceil($target->getPrototype()->getHp() * 0.1), ceil($target->getPrototype()->getHp() * 0.7)));
                $damages = min($damageInflicted, $target->getHp(), mt_rand(ceil($target->getPrototype()->getHp() * 0.1), $target->getPrototype()->getHp()));

                if ($damages <= 0) continue;

                $realDamage = min($damages, ceil($target->getPrototype()->getHp() * 0.7));

                $this->log->info("The <info>{$target->getPrototype()->getLabel()}</info> has taken <info>$realDamage</info> damages.");
                $target->setHp(max(0, $target->getHp() - $realDamage));

                $this->gps->recordBuildingDamaged( $target->getPrototype(), $town, $realDamage );

                if($target->getPrototype()->getDefense() > 0){
                    $newDef = round(max(0, $target->getPrototype()->getDefense() * $target->getHp() / $target->getPrototype()->getHp()));
                    $this->log->debug("It now has <info>$newDef</info> defense...");
                    $target->setDefense($newDef);
                }

                if($target->getHp() <= 0){
                    $this->log->info("<info>{$target->getPrototype()->getLabel()}</info> is now destroyed !");
                    $this->entity_manager->persist($this->logTemplates->constructionsDestroy($town, $target->getPrototype(), $realDamage ));
                    $this->town_handler->destroy_building($town, $target);
                    $this->gps->recordBuildingDestroyed( $target->getPrototype(), $town, 'attack' );
                } else {
                    $this->entity_manager->persist($this->logTemplates->constructionsDamage($town, $target->getPrototype(), $realDamage ));
                }

                $damageInflicted -= $damages;
            }
        }

        if ($this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_DO_DESTROY, false)) {
            // Panda towns sees their defense object in the bank destroyed
            $number = $def_summary ? max(1, min(ceil($est->getZombies() - $def_summary->withoutItemDefense()) * 0.5, 20)) : 0;
            $items = $this->inventory_handler->fetchSpecificItems($town->getBank(), [new ItemRequest('defence', $number, false, null, true)]);
            $this->log->info("We destroy <info>$number</info> items");
            $this->log->info("We fetched <info>". count($items) . "</info> items");
            shuffle($items);
            $destroyed_count = 0;
            $itemsForLog = [];
            while($destroyed_count < $number && count($items) > 0) {
                foreach ($items as $item) {
                    if ($destroyed_count >= $number) break;

                    $this->log->debug("selecting between 1 and " . min($item->getCount(), $number - $destroyed_count));
                    $delete = mt_rand(1, min($item->getCount(), $number - $destroyed_count));
                    $destroyed_count += $delete;
                    $this->log->info("Destroying $delete <info>{$item->getPrototype()->getName()}</info> due to the attack");
                    $this->inventory_handler->forceRemoveItem($item, $delete);
                    if(isset($itemsForLog[$item->getPrototype()->getId()])) {
                        $itemsForLog[$item->getPrototype()->getId()]['count']+= $delete;
                    } else {
                        $itemsForLog[$item->getPrototype()->getId()] = [
                            'item' => $item->getPrototype(),
                            'count' => $delete
                        ];
                    }
                    if ($delete === $item->getCount()) {
                        array_pop($items);
                    }
                }
            }

            $total = 0;
            foreach ($itemsForLog as $item) {
                $total += $item["count"];
            }

            if (!empty($itemsForLog)) {
                $this->entity_manager->persist($this->logTemplates->nightlyAttackBankItemsDestroy($town, $itemsForLog, $total));
            }
        }

        if ($overflow <= 0) {
            $this->entity_manager->persist($gazette);
            return;
        }

        $survival_count = 0;
        /** @var Citizen[] $targets */
        $targets = [];
        foreach ($town->getCitizens() as $citizen) {
            if ($citizen->getAlive()) {
                $survival_count++;
                if (!$citizen->getZone())
                    $targets[] = $citizen;
            }
        }
					
		
        shuffle($targets);
		
        $attack_day = $town->getDay();
		if ($attack_day <= 3) $max_active = round($zombies*0.5*mt_rand(90,140)/100); 
		elseif ($attack_day <= 14) $max_active = $attack_day * 15;
		elseif ($attack_day <= 18) $max_active = ($attack_day + 4)*15;
		elseif ($attack_day <= 23) $max_active = ($attack_day + 5)*15;
		else                       $max_active = ($attack_day + 6)*15;
		
		
		//$in_town = $town->getChaos() ? max(10,count($targets)) : count($targets);
		$in_town = min(10, ceil(count($targets) * 0.85));
		
		$attacking = min($max_active, $overflow);

		$targets = $this->random->pick($targets, $in_town, true);

        $this->log->debug("<info>{$attacking}</info> Zombies are attacking <info>" . count($targets) . "</info> citizens!");
        if (!empty($targets)) $this->entity_manager->persist( $this->logTemplates->nightlyAttackLazy($town, $attacking) );

		$repartition = array_fill(0, count($targets), 0);
		for ($i = 0; $i < count($repartition); $i++) {
			$repartition[$i] = mt_rand() / mt_getrandmax(); //random value between 0 and 1.0 with many decimals
		}
		
		if(count($repartition) != 0) {
			//one citizen gets especially unlucky
			$repartition[mt_rand(0, count($repartition)-1)] += 0.3;
		}
		
		$sum = array_sum($repartition);

		$attacking_cache = $attacking;
		for ($i = 0; $i < count($repartition); $i++) {
			$repartition[$i] /= $sum;
			$repartition[$i] = max(0,min($attacking_cache, round($repartition[$i]*$attacking)));
            $attacking_cache -= $repartition[$i];
		}

		while ($attacking_cache > 0 && count($repartition) > 0) {
            $repartition[mt_rand(0, count($repartition)-1)] += 1;
            $attacking_cache--;
        }

		//remove citizen receiving 0 zombie
		foreach (array_keys($repartition, 0) as $key) {
			unset($repartition[$key]);
		}

        $deaths = 0;

		rsort($repartition);

        for ($i = 0; $i < count($repartition); $i++) {
            $home = $targets[$i]->getHome();
			
			$force = $repartition[$i];
			
            $def = $this->town_handler->calculate_home_def($home);
            $this->log->debug("Citizen <info>{$targets[$i]->getUser()->getUsername()}</info> is attacked by <info>{$force}</info> zombies and protected by <info>{$def}</info> home defense!");
		
            if ($force > $def){
                $this->kill_wrap($targets[$i], $cod, false, $force);
                $deaths++;
				
                // citizen dies from the attack, citizen validate the new day
                $gazette->setDeaths($gazette->getDeaths() + 1);
            }
            else {
                $this->entity_manager->persist($this->logTemplates->citizenZombieAttackRepelled( $targets[$i], $def, $force));
                // Calculate decoration
                $deco = $this->citizen_handler->getDecoPoints($targets[$i]);

                if (!$has_kino && !$this->citizen_handler->hasStatusEffect($targets[$i], $status_terror)) {
                    if ($this->random->chance(1 - ($deco / 100))) {
                        $this->citizen_handler->inflictStatus( $targets[$i], $status_terror );
                        $this->log->debug("Citizen <info>{$targets[$i]->getUser()->getUsername()}</info> now suffers from <info>{$status_terror->getLabel()}</info>");

                        $this->crow->postAsPM($targets[$i], '', '', PrivateMessage::TEMPLATE_CROW_TERROR, $force);

                        $gazette->setTerror($gazette->getTerror() + 1);
                    } else {
                        $this->crow->postAsPM($targets[$i], '', '', PrivateMessage::TEMPLATE_CROW_AVOID_TERROR, $force);
                    }
                }
            }
        }

        if ($deaths > 0)
            $this->entity_manager->persist($this->logTemplates->citizenDeathsDuringAttack($town, $deaths));
        $this->entity_manager->persist($gazette);
    }

    private function stage2_post_attack_buildings(Town &$town){
        $this->log->info('Inflicting damages to buildings after the attack');
        $fireworks = $this->town_handler->getBuilding($town, 'small_fireworks_#00');
        if($fireworks){
            $this->gps->recordBuildingDamaged( $fireworks->getPrototype(), $town, min( 20, $fireworks->getHp() ) );
            $fireworks->setHp(max(0, $fireworks->getHp() - 20));

            $this->log->debug("The <info>fireworks</info> has taken <info>20</info> damages...");
            $newDef = round(max(0, $fireworks->getPrototype()->getDefense() * $fireworks->getHp() / $fireworks->getPrototype()->getHp()));

            $this->log->debug("It now has $newDef defense...");

            $fireworks->setDefense($newDef);
            if($fireworks->getHp() <= 0) {
                // It is destroyed, let's do this !
                $this->entity_manager->persist($this->logTemplates->fireworkExplosion($town, $fireworks->getPrototype()));

                $this->town_handler->destroy_building($town, $fireworks);
                $this->gps->recordBuildingDestroyed( $fireworks->getPrototype(), $town, 'attack' );

                $this->log->debug("The fireworks are destroyed. Half of citizens in town gets infected !");

                // Fetching alive citizens
                $citizens = $this->town_handler->get_alive_citizens($town);
                $toInfect = [];
                // Keeping citizens in town
                foreach ($citizens as $citizen) {
                    /** @var Citizen $citizen */
                    if($citizen->getZone() || !$citizen->getAlive()) continue;
                    $toInfect[] = $citizen;
                }

                // Randomness
                shuffle($toInfect);
                // We infect the first half of the list
                for ($i=0; $i < count($toInfect) / 2; $i++)
                    $this->citizen_handler->inflictStatus($toInfect[$i], "tg_meta_ginfect");

                $this->exec_firework = true;
                
                $ratio = 1 - mt_rand(13, 16) / 100;

                /** @var ZombieEstimation $est */
                $est = $this->entity_manager->getRepository(ZombieEstimation::class)->findOneByTown($town,$town->getDay());
                $est->setZombies($est->getZombies() * $ratio);
                $this->entity_manager->persist($est);

            } else {
                $this->entity_manager->persist($this->logTemplates->constructionsDamage($town, $fireworks->getPrototype(), 20 ));
            }
            $this->entity_manager->persist($fireworks);
        }

        foreach ($town->getBuildings() as $b) if ($b->getComplete()) {
            if ($b->getPrototype()->getTemp()){
                $this->log->debug("Destroying building <info>{$b->getPrototype()->getLabel()}</info> as it is a temp building.");
                $this->entity_manager->persist( $this->logTemplates->nightlyAttackDestroyBuilding($town, $b));
                $b->setComplete(false)->setAp(0);
                $this->gps->recordBuildingCollapsed( $b->getPrototype(), $town );
            }
            $b->setTempDefenseBonus(0);
        }

        $town->setTempDefenseBonus(0);
    }

    private function stage3_status(Town $town) {
        $this->log->info('<info>Processing status changes</info> ...');

        $status_survive   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy( ['name' => 'hsurvive'] );
        $status_hasdrunk  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy( ['name' => 'hasdrunk'] );
        $status_infection = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy( ['name' => 'infection'] );
        $status_camping   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy( ['name' => 'camper'] );

        $status_wound_infection = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy( ['name' => 'tg_meta_winfect'] );

        /* April fools states */
        $status_ooze = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy( ['name' => 'tg_april_ooze'] );
        $status_paranoid = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy( ['name' => 'tg_paranoid'] );

        $status_clear_list = ['hasdrunk','haseaten','immune','hsurvive','drunk','drugged','healed','hungover','tg_dice','tg_cards','tg_clothes','tg_teddy','tg_guitar','tg_sbook','tg_steal','tg_home_upgrade','tg_hero','tg_chk_forum','tg_chk_active', 'tg_chk_workshop', 'tg_chk_build', 'tg_chk_movewb', 'tg_hide','tg_tomb', 'tg_home_clean', 'tg_home_shower', 'tg_home_heal_1', 'tg_home_heal_2', 'tg_home_defbuff', 'tg_rested', 'tg_shaman_heal', 'tg_ghoul_eat', 'tg_no_hangover', 'tg_ghoul_corpse', 'tg_betadrug', 'tg_build_vote', 'tg_insurrection', 'tg_april_ooze', 'tg_novlamps', 'tg_tried_pp'];

        $aliveCitizenInTown = 0;
        $aliveCitizen = 0;

        $ghoul_mode  = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_FEATURE_GHOUL_MODE, 'normal');
        $ghoul_begin = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_AUTOGHOUL_FROM, 5);

        // Check if we need to ghoulify someone
        if (in_array($ghoul_mode, ['airborne', 'airbnb']) && $town->getDay() >= $ghoul_begin) {

            // Starting with the auto ghoul begin day, every 3 days a new ghoul is added
            if (($town->getDay() - $ghoul_begin) % 3 === 0) {
                $this->log->debug("Distributing the <info>airborne ghoul infection</info>!");
                $this->citizen_handler->pass_airborne_ghoul_infection(null,$town);
            }


        }

        foreach ($town->getCitizens() as $citizen) {

            if ($citizen->getDailyUpgradeVote()) {
                $this->cleanup[] = $citizen->getDailyUpgradeVote();
                $citizen->setDailyUpgradeVote(null);
            }

            if ($citizen->getBuildingVote()) {
                $this->cleanup[] = $citizen->getBuildingVote();
                $citizen->setBuildingVote(null);
            }

            $citizen->getExpeditionRoutes()->clear();
            foreach ($citizen->getZoneActivityMarkers() as $marker)
                if ($marker->getType()->daily()) {
                    $marker->getZone()->removeActivityMarker($marker);
                    $citizen->removeZoneActivityMarker($marker);
                    $this->cleanup[] = $marker;
                }

            if (!$citizen->getAlive()) continue;

            $aliveCitizen++;
            $citizen->setHasSeenGazette(false);

            if($citizen->getZone() === null)
                $aliveCitizenInTown++;

            if ($citizen->getStatus()->contains($status_survive))
                $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> is <info>protected</info> by <info>{$status_survive->getLabel()}</info>.");
            else
            {
                if (!$citizen->getStatus()->contains($status_hasdrunk)) {
                    $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>not</info> drunk today. <info>Increasing</info> thirst level.");
                    $this->citizen_handler->increaseThirstLevel( $citizen );
                }
                if (!$citizen->getStatus()->contains($status_infection) && $this->citizen_handler->isWounded( $citizen ) && !in_array( $citizen->getId(), $this->skip_infection )) {
                    $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> is <info>wounded</info>. Adding an <info>infection</info>.");
                    $this->citizen_handler->inflictStatus($citizen, $status_wound_infection);
                }
                if (!$citizen->getStatus()->contains($status_infection) && $citizen->getStatus()->contains($status_ooze)) {
                    $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> has consumed the <info>ooze</info>. Adding an <info>infection</info>.");
                    $this->citizen_handler->inflictStatus($citizen, $status_wound_infection);
                }
            }

            if (!$citizen->getStatus()->contains($status_paranoid) && $citizen->getStatus()->contains($status_ooze)) {
                $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> has consumed the <info>ooze</info>. Adding the <info>paranoid</info> state.");
                $this->citizen_handler->inflictStatus($citizen, $status_paranoid);
            }

            if ($citizen->hasRole('ghoul')) {
                $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> is a <info>ghoul</info>. <info>Increasing</info> hunger.");
                $citizen->setGhulHunger( $citizen->getGhulHunger() + (($town->getChaos() || $town->getDevastated()) ? 15 : 25));
            }

            $this->log->debug("Setting appropriate camping status for citizen <info>{$citizen->getUser()->getUsername()}</info> (who is <info>" . ($citizen->getZone() ? 'outside' : 'inside') . "</info> the town)...");
            if ($citizen->getZone()) {
                $citizen->addStatus( $status_camping );
                $citizen->setCampingTimestamp(0);
                $citizen->setCampingChance(0);
            }
            else $citizen->removeStatus( $status_camping );

            $this->log->debug("Removing volatile counters for citizen <info>{$citizen->getUser()->getUsername()}</info>...");
            $citizen->setWalkingDistance(0);
            $this->citizen_handler->setAP($citizen,false,$this->citizen_handler->getMaxAP( $citizen ),0);
            $this->citizen_handler->setBP($citizen,false,$this->citizen_handler->getMaxBP( $citizen ),0);
            $this->citizen_handler->setPM($citizen,false,$this->citizen_handler->getMaxPM( $citizen ));
            foreach ($citizen->getActionCounters() as $counter)
                if ($counter->getDaily()) {
                    $citizen->removeActionCounter($counter);
                    $this->entity_manager->remove($counter);
                }
            $citizen->getDigTimers()->clear();
            if ($citizen->getEscortSettings()) $this->entity_manager->remove($citizen->getEscortSettings());
            $citizen->setEscortSettings(null);
            
            foreach ($this->entity_manager->getRepository( EscapeTimer::class )->findAllByCitizen( $citizen ) as $et)
                $this->cleanup[] = $et;

            $add_hangover = ($this->citizen_handler->hasStatusEffect($citizen, 'drunk') && !$this->citizen_handler->hasStatusEffect($citizen, 'tg_no_hangover'));
            foreach ($citizen->getStatus() as $st)
                if (in_array($st->getName(),$status_clear_list)) {
                    $this->log->debug("Removing volatile status from citizen <info>{$citizen->getUser()->getUsername()}</info>: <info>{$st->getLabel()}</info>.");
                    $this->citizen_handler->removeStatus( $citizen, $st );
                }
            if ($add_hangover) $this->citizen_handler->inflictStatus($citizen, 'hungover');

            if ($citizen->hasRole('ghoul')) $this->citizen_handler->removeStatus($citizen, 'infection');

            $alarm = $this->inventory_handler->fetchSpecificItems($citizen->getInventory(), [new ItemRequest("alarm_on_#00")]);
            if (count($alarm) > 0) {
                $this->citizen_handler->setAP($citizen, true, 1);
                $alarm[0]->setPrototype($this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'alarm_off_#00']));
                $this->entity_manager->persist($alarm[0]);
            }

            if ($this->citizen_handler->hasStatusEffect($citizen, 'tg_air_infected') && !$citizen->hasRole('ghoul')) {
                $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> has been infected by the <info>airborne ghoul disease</info>!: Turning them into a <info>ghoul</info>!");
                $this->citizen_handler->removeStatus($citizen, 'tg_air_infected');
                $this->citizen_handler->addRole($citizen, 'ghoul');
                $this->citizen_handler->inflictStatus($citizen, 'tg_air_ghoul');
                if ($this->conf->getTownConfiguration( $town )->get( TownConf::CONF_FEATURE_GHOULS_HUNGRY, false ))
                    $citizen->setGhulHunger(45);
            }
        }

        if($town->getDevastated()){
            // Each day as devastated, the town lose water as zombies are entering town.
            $d = min($town->getWell(), rand(20, 40));
            
            if($d > 0){
                $this->log->debug("Town is devastated, the zombies entering town removed <info>{$d} water rations</info> from the well.");
                $this->entity_manager->persist($this->logTemplates->nightlyDevastationAttackWell($d, $town));
                $town->setWell($town->getWell() - $d);
            }
        } else {
            $this->log->debug("Town is not yet devastated, and has <info>$aliveCitizen</info> alive citizens (including <info>$aliveCitizenInTown</info> in town)");

            if ($aliveCitizen > 0 && $aliveCitizen <= 10 && $aliveCitizenInTown > 0 && !$town->getDevastated() && $town->getDay() > 3) {
                $this->log->debug("There is <info>$aliveCitizen</info> citizens alive, the town is not devastated, setting the town to <info>chaos</info> mode");
                $town->setChaos(true);

                foreach ($town->getCitizens() as $target_citizen)
                    $target_citizen->setBanished(false);
            }

            if ($aliveCitizenInTown == 0) {
                $this->log->debug("There is <info>$aliveCitizenInTown</info> citizens alive AND in town, setting the town to <info>devastated</info> mode and to <info>chaos</info> mode");

                $last_stand_day = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_FEATURE_LAST_DEATH_DAY, 5);
                if($town->getDay() >= $last_stand_day){
                    $this->log->debug("Town has lived for $last_stand_day days or more, we give the <info>Last Man Standing</info> picto to a lucky citizen that died in town");
                    $citizen_eligible = [];
                    foreach ($town->getCitizens() as $citizen) {
                        /** @var Citizen $citizen */
                        if($citizen->getAlive() || $citizen->getZone())
                            continue;

                        if($citizen->getSurvivedDays() < $town->getDay() - 1)
                            continue;

                        if($citizen->getCauseOfDeath()->getRef() !== CauseOfDeath::NightlyAttack && $citizen->getCauseOfDeath()->getRef() !== CauseOfDeath::Radiations)
                            continue;
                        $citizen_eligible[] = $citizen;
                    }

                    $last_stand_pictos = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_FEATURE_LAST_DEATH, ['r_surlst_#00']);
                    if (!empty($last_stand_pictos) && count($citizen_eligible) > 0) {
                        /** @var Citizen $winner */
                        $winner = $this->random->pick($citizen_eligible);
                        foreach ($last_stand_pictos as $last_stand_picto) {
                            $this->log->debug("We give the picto <info>$last_stand_picto</info> to the lucky citizen {$winner->getUser()->getUsername()}");
                            $this->picto_handler->give_validated_picto($winner, $last_stand_picto);
                        }
                    }

                    foreach ($citizen_eligible as $citizen)
                        $this->picto_handler->give_validated_picto($citizen, "r_surgrp_#00");

                }

                $gazette = $town->findGazette($town->getDay(), true);
                if (!$gazette->getReactorExplosion())
                    $this->entity_manager->persist($this->logTemplates->nightlyAttackDevastated($town));

                $this->town_handler->devastateTown($town);

                if (!$gazette->getReactorExplosion()) {
                    $townTemplate = $this->entity_manager->getRepository(GazetteEntryTemplate::class)->findOneBy(['name' => 'gazetteTownLastAttack']);
                    $news = new GazetteLogEntry();
                    $news->setDay($town->getDay())->setGazette($gazette)->setTemplate($townTemplate)->setVariables(['town' => $town->getName()]);
                    $this->entity_manager->persist($news);
                }

                foreach ($town->getCitizens() as $target_citizen)
                    $target_citizen->setBanished(false);

                // The town lose water as zombies are entering town.
                $d = min($town->getWell(), rand(20, 40));
                
                if($d > 0){
                    $this->log->debug("The zombies entering town removed <info>{$d} water rations</info> from the well.");
                    $this->entity_manager->persist($this->logTemplates->nightlyDevastationAttackWell($d, $town));
                    $town->setWell($town->getWell() - $d);
                }
                
                //foreach ($town->getBuildings() as $target_building)
                //    if (!$target_building->getComplete()) $target_building->setAp(0);
            }
        }
    }

    private function stage3_zones(Town &$town) {
        $this->log->info('<info>Processing changes in the World Beyond</info> ...');

        $this->log->debug('Spreading zombies ...');
        $this->zone_handler->dailyZombieSpawn($town);

        if ($this->exec_firework)
            // Kill zombies around the town (all at 1km, none beyond 10km)
            foreach ($town->getZones() as $zone) {
                $km = $this->zone_handler->getZoneKm($zone);
                if($km >= 10) continue;

                $factor = 1 - 0.1 * (10 - $km);
                $zone->setZombies(max(0, round($zone->getZombies() * $factor, 0)));
            }

        $research_tower = $this->town_handler->getBuilding($town, 'small_gather_#02', true);
        $watchtower     = $this->town_handler->getBuilding($town, 'item_tagger_#00',  true);

        $upgraded_map = $this->town_handler->getBuilding($town,'item_electro_#00', true);

        $zone_tag_none = $this->entity_manager->getRepository(ZoneTag::class)->findOneBy(['ref' => ZoneTag::TagNone]);

        $gazette = $town->findGazette($town->getDay(), true);

        if ($watchtower) switch ($watchtower->getLevel()) {
            case 0: $discover_range  = 0;  break;
            case 1: $discover_range  = 3;  break;
            case 2: $discover_range  = 6;  break;
            default: $discover_range = 10; break;
        } else $discover_range = 0;

        if ($research_tower) switch ($research_tower->getLevel()) {
            case 0: $recovery_chance  = 0.25; break;
            case 1: $recovery_chance  = 0.37; break;
            case 2: $recovery_chance  = 0.49; break;
            case 3: $recovery_chance  = 0.61; break;
            case 4: $recovery_chance  = 0.73; break;
            default: $recovery_chance = 0.85; break;
        } else $recovery_chance = 0.25;

        $wind = $this->random->pick( [Zone::DirectionNorthWest, Zone::DirectionNorth, Zone::DirectionNorthEast, Zone::DirectionWest, Zone::DirectionEast, Zone::DirectionSouthWest, Zone::DirectionSouth, Zone::DirectionSouthEast] );

        $this->log->debug('Processing individual zones ...');
        $this->log->debug('Modifiers - <info>Search Tower</info>: <info>' . ($research_tower ? $research_tower->getLevel() : 'No') . '</info>, <info>Watch Tower</info>: <info>' . ($watchtower ? $watchtower->getLevel() : 'No') . '</info>' );
        $this->log->debug("Wind Direction is <info>{$wind}</info>." );

        if ($research_tower)
            $gazette->setWindDirection($wind);

        $this->entity_manager->persist($gazette);

        $reco_counter = [0,0];

        $maze_zeds = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_EXPLORABLES_ZOMBIES_DAY, 5);
        $wind_dist = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_MODIFIER_WIND_DISTANCE, 2);

        foreach ($town->getZones() as $zone) {
            /** @var Zone $zone */
            if ($zone->getPrototype() && $zone->getPrototype()->getExplorable()) {
                foreach ($zone->getExplorerStats() as $ex) {
                    $ex->getCitizen()->removeExplorerStat( $ex );
                    $this->entity_manager->remove($ex);
                }

                foreach ($zone->getChatSilenceTimers() as $timer) {
                    $zone->removeChatSilenceTimer($timer);
                    $this->entity_manager->remove($timer);
                }
                $this->maze->populateMaze( $zone, $maze_zeds * $zone->getExplorableFloorFactor(), true, true );
            }

            $distance = sqrt( pow($zone->getX(),2) + pow($zone->getY(),2) );
            if ($zone->getCitizens()->count() || round($distance) <= $discover_range) {
                if ($zone->getDiscoveryStatus() !== Zone::DiscoveryStateCurrent) {
                    $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Set discovery state to <info>current</info>." );
                    $zone->setDiscoveryStatus(Zone::DiscoveryStateCurrent);
                    $zone->setZombieStatus( $upgraded_map ? Zone::ZombieStateExact : Zone::ZombieStateEstimate );
                }
            } elseif ($zone->getDiscoveryStatus() === Zone::DiscoveryStateCurrent) {
                $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Set discovery state to <info>past</info>." );
                $zone->setDiscoveryStatus(Zone::DiscoveryStatePast);
                $zone->setZombieStatus( Zone::ZombieStateUnknown );
            }

            if ($zone->getDirection() === $wind && round($distance) > $wind_dist) {
                $reco_counter[1]++;
                if ($this->random->chance( $recovery_chance )) {
                    $digs = mt_rand( $this->conf->getTownConfiguration($town)->get(TownConf::CONF_ZONE_ITEMS_RE_MIN, 2) , $this->conf->getTownConfiguration($town)->get(TownConf::CONF_ZONE_ITEMS_RE_MAX, 5));
                    if ($zone->getDigs() >= $this->conf->getTownConfiguration($town)->get(TownConf::CONF_ZONE_ITEMS_THROTTLE_AT, 12))
                        $digs = ceil(($digs-1) / 2);
                    $zone->setDigs( min( $zone->getDigs() + $digs, $this->conf->getTownConfiguration($town)->get(TownConf::CONF_ZONE_ITEMS_TOTAL_MAX, 30) ) );
                    $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Recovering by <info>{$digs}</info> to <info>{$zone->getDigs()}</info>." );
                    $reco_counter[0]++;
                }

                if ($zone->getPrototype() && $this->random->chance( $recovery_chance ) ) {
                    $rdigs = mt_rand(1, 5);
                    $zone->setRuinDigs( min( $zone->getRuinDigs() + $rdigs, 10 ) );
                    $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Recovering ruin by <info>{$rdigs}</info> to <info>{$zone->getRuinDigs()}</info>." );
                }
            }

            if ($zone->getImprovementLevel() > 0) {
              $zone->setImprovementLevel(max(($zone->getImprovementLevel() - 3), 0));
              $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Improvement Level has been reduced to <info>{$zone->getImprovementLevel()}</info>." );
            }

            if ($zone->getTag() !== null && $zone->getTag()->getTemporary()) {
                $zone->setTag($zone_tag_none);
            }
        }
        $this->log->debug("Recovered <info>{$reco_counter[0]}</info>/<info>{$reco_counter[1]}</info> zones." );

        if ($this->conf->getTownConfiguration($town)->is( TownConf::CONF_FEATURE_SHAMAN_MODE, ['normal','both'], 'normal' )) {
            $this->log->debug("Processing <info>souls</info> mutations.");

            $blue_souls = $this->inventory_handler->getAllItems($town, 'soul_blue_#00');

            $red_soul_proto = $this->entity_manager->getRepository(ItemPrototype::class)->findOneByName('soul_red_#00');
            if (!$red_soul_proto) throw new \Exception('No red soul prototype!');

            $soul_transformation_rate = [0.10,0.25,0.50,0.75,1.00];
            foreach ($blue_souls as $soul) {

                $data = $this->entity_manager->getRepository(ItemInfoAttachment::class)->findOneBy(['item' => $soul]) ?? (new ItemInfoAttachment)->setItem($soul);
                $survived_nights = max(0, min( $data->get('blue_soul_survival_count', 0), 4 ) );

                if ($this->random->chance($soul_transformation_rate[$survived_nights])) {
                    $this->log->debug("Mutation: <info>Mutating</info> a soul! Mutation chance was <info>{$soul_transformation_rate[$survived_nights]}</info>.");
                    $this->entity_manager->persist( $soul->setPrototype( $red_soul_proto ) );
                } else {
                    $this->log->debug("Mutation: <info>Ignoring</info> a soul! Mutation chance was <info>{$soul_transformation_rate[$survived_nights]}</info>.");
                    $data->set('blue_soul_survival_count', $survived_nights + 1);
                    $this->entity_manager->persist( $data );
                }
            }
        }
    }

    private function stage3_items(Town $town) {
        $this->log->info('<info>Processing item changes</info> ...');

        /** @var Inventory[] $inventories */
        $inventories = [];

        $inventories[] = $town->getBank();

        foreach ($town->getCitizens() as $citizen) {
            $inventories[] = $citizen->getInventory();
            $inventories[] = $citizen->getHome()->getChest();
        }

        foreach ($town->getZones() as $zone) {
            $inventories[] = $zone->getFloor();
            foreach ($zone->getRuinZones() as $ruinZone) {
                $inventories[] = $ruinZone->getFloor();
                if ($ruinZone->getRoomFloor()) $inventories[] = $ruinZone->getRoomFloor();
            }
        }

        $c = count($inventories);
        $this->log->debug( "Number of inventories: <info>{$c}</info>." );

        $morph = [
            'torch_#00'    => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'torch_off_#00']),
            'lamp_on_#00'  => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'lamp_#00']),
            // 'radio_on_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'radio_off_#00']),
            'tamed_pet_off_#00'  => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'tamed_pet_#00']),
            'tamed_pet_drug_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'tamed_pet_#00']),
            'maglite_2_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'maglite_1_#00']),
            'maglite_1_#00' => $this->entity_manager->getRepository(ItemPrototype::class)->findOneBy(['name' => 'maglite_off_#00']),
        ];

        foreach ($morph as $source => $target) {
            $items = $this->inventory_handler->fetchSpecificItems($inventories, [(new ItemRequest($source))->fetchAll(true)]);

            $c = count($items);
            $this->log->debug( "Morphing <info>{$c}</info> items to type '<info>{$target->getLabel()}</info>'." );

            foreach ($items as $item)
                $item->setPrototype( $target );
        }
    }

    private function stage3_pictos(Town $town){

        $status_camping           = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'camper']);
        $picto_camping            = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => 'r_camp_#00']);
        $picto_camping_devastated = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => 'r_cmplst_#00']);
        $picto_nightwatch         = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => 'r_guard_#00']);
        $this->log->info('<info>Processing Pictos functions</info> ...');

        // Marking pictos as obtained not-today
        $citizens = $town->getCitizens();
        foreach ($citizens as $citizen) {
            // If the citizen is not alive anymore, the calculation is not to be done here
            if(!$citizen->getAlive())
                continue;

            // Giving picto camper if he camped
            if ($citizen->getStatus()->contains($status_camping)) {
                $this->picto_handler->give_picto($citizen, $picto_camping);
                if ($town->getDevastated() && $town->getDay() >= 10){
                    $this->picto_handler->give_picto($citizen, $picto_camping_devastated);
                }
            }

            // Giving picto nightwatch if he's watcher
            $watch = $this->entity_manager->getRepository(CitizenWatch::class)->findWatchOfCitizenForADay($citizen, $town->getDay() - 1);
            if($watch !== null && !$watch->getSkipped()){
                // You must be in town to be considered a watcher !
                $this->picto_handler->give_picto($citizen, $picto_nightwatch);
            }

            $this->picto_handler->nightly_validate_picto( $citizen );
        }
    }

    private function stage3_roles(Town $town){
        if($town->getChaos()) {
            $this->log->info( "Town is in <info>Chaos</info>, no more votes." );
            return;
        }

        $this->log->info( "Processing elections..." );
        $citizens = $town->getCitizens();
        $roles = $this->entity_manager->getRepository(CitizenRole::class)->findVotable();

        /** @var CitizenRole $role */
        $last_mc = null;
        $all_winners = [];
        foreach ($roles as $role) {
            $this->log->info("Processing votes for role {$role->getLabel()}");
            if(!$this->town_handler->is_vote_needed($town, $role, true)) {
                $this->log->info("The role {$role->getLabel()} doesn't need vote, skipping");
                continue;
            }

            // Getting vote per role per citizen
            $votes = array();
            foreach ($citizens as $citizen)
                if($citizen->getAlive() && !in_array( $citizen, $all_winners ) && ($c = $this->entity_manager->getRepository(CitizenVote::class)->count( ['votedCitizen' => $citizen, 'role' => $role] )) > 0)
                    $votes[$citizen->getId()] = $c;  //  ->countCitizenVotesFor($citizen, $role);

            if (empty($votes)) {
                $this->log->debug("No citizen placed votes for the role!");
                foreach ($citizens as $citizen)
                    if ($citizen->getAlive()) $votes[$citizen->getId()] = 0;
            }

            $partition = [
                '_council?' => [],
                'voted' => [],
                '_winner' => [],
            ];

            $flags = [];

            $valid_citizens = 0;
            foreach ($citizens as $citizen) {
                // Dead citizen cannot vote
                if(!$citizen->getAlive()) continue;
                $valid_citizens++;

                if (!$citizen->getZone()) $partition['_council?'][] = $citizen;

                $voted = $this->entity_manager->getRepository(CitizenVote::class)->findOneBy(['autor' => $citizen, 'role' => $role]); //findOneByCitizenAndRole($citizen, $role);
                /** @var CitizenVote $voted */
                if ($voted === null || !$voted->getVotedCitizen()->getAlive() || in_array( $voted->getVotedCitizen(), $all_winners )) {
                    $this->log->debug("Citizen {$citizen->getName()} didn't vote, or voted for a dead citizen. We replace the vote.");
                    // He has not voted, or the citizen he voted for is now dead, let's give his vote to someone who has votes
                    $vote_for_id = $this->random->pick(array_keys($votes), 1);
                    $votes[$vote_for_id]++;

                    $voted_for = $this->entity_manager->getRepository(Citizen::class)->find($vote_for_id);
                    $partition['voted'][$voted_for->getId()] = $voted_for;
                    $this->log->debug("Citizen {$citizen->getName()} then voted for citizen " . $voted_for->getName());
                } else {
                    $partition['voted'][$voted->getVotedCitizen()->getId()] = $voted->getVotedCitizen();
                    $this->log->debug("Citizen {$citizen->getName()} voted for {$voted->getVotedCitizen()->getName()}");
                }
            }

            // Let's get the winner
            $citizenWinnerId = 0;
            $citizenWinnerCount = 0;

            foreach ($votes as $idCitizen => $count) {
                if($citizenWinnerCount <= $count) {
                    $citizenWinnerCount = $count;
                    $citizenWinnerId = $idCitizen;
                }
            }

            // We give him the related status
            $winningCitizen = $citizenWinnerId > 0 ? $this->entity_manager->getRepository(Citizen::class)->find($citizenWinnerId) : null;
            if($winningCitizen !== null){
                $this->log->info( "Citizen <info>{$winningCitizen->getUser()->getUsername()}</info> has been elected as <info>{$role->getLabel()}</info>." );
                $this->citizen_handler->addRole($winningCitizen, $role);
                $this->entity_manager->persist($winningCitizen);

                $partition['_winner'] = [$winningCitizen];
                if ($town->getDay() <= 2) $all_winners[] = $winningCitizen;

                $partition['_council?'] = array_diff( $partition['_council?'], array_slice($partition['voted'], 0, max(0,count($partition['_council?']) - 7)), $partition['_winner'] );
                $partition['voted'] = array_diff( $partition['voted'], $partition['_winner'] );
                shuffle($partition['_council?']);
                shuffle($partition['voted']);

                if (!empty($partition['_council?'])) {
                    if ($last_mc !== null && in_array( $last_mc, $partition['_council?'] ) && $this->random->chance(0.5)) {
                        $partition['_mc'] = [ $last_mc ];
                        $partition['_council?'] = array_filter( $partition['_council?'], fn(Citizen $cc) => $cc !== $last_mc );
                    } else $partition['_mc'] = [ array_pop( $partition['_council?'] ) ];

                    $flags['same_mc'] = $partition['_mc'][0] === $last_mc;
                    $last_mc = $partition['_mc'][0];

                } else $last_mc = null;
                $semantic = null;
                switch ($role->getName()) {
                    case 'shaman':
                        if ($valid_citizens === 1)
                            $semantic = CouncilEntryTemplate::CouncilNodeRootShamanSingle;
                        elseif ( $valid_citizens < 10 )
                            $semantic = CouncilEntryTemplate::CouncilNodeRootShamanFew;
                        else
                            $semantic = $this->entity_manager->getRepository(Citizen::class)->findLastOneByRoleAndTown($role, $town) ? CouncilEntryTemplate::CouncilNodeRootShamanNext : CouncilEntryTemplate::CouncilNodeRootShamanFirst;
                        break;
                    case 'guide':
                        if ($valid_citizens === 1)
                            $semantic = CouncilEntryTemplate::CouncilNodeRootGuideSingle;
                        elseif ( $valid_citizens < 10 )
                            $semantic = CouncilEntryTemplate::CouncilNodeRootGuideFew;
                        else
                            $semantic = $this->entity_manager->getRepository(Citizen::class)->findLastOneByRoleAndTown($role, $town) ? CouncilEntryTemplate::CouncilNodeRootGuideNext : CouncilEntryTemplate::CouncilNodeRootGuideFirst;
                        break;
                }
                $this->gazette_service->generateCouncilNodeList( $town, $town->getDay(), $semantic, $partition, $flags );
            } else {
                switch ($role->getName()) {
                    case 'shaman':
                        $this->gazette_service->generateCouncilNodeList( $town, $town->getDay(), CouncilEntryTemplate::CouncilNodeRootShamanNone, [], [] );
                        break;
                    case 'guide':
                        $this->gazette_service->generateCouncilNodeList( $town, $town->getDay(), CouncilEntryTemplate::CouncilNodeRootGuideNone, [], [] );
                        break;
                }
            }

            // we remove the votes
            foreach ($citizens as $citizen) {
                /** @var Citizen $citizen */
                $vote = $this->entity_manager->getRepository(CitizenVote::class)->findOneBy(['autor' => $citizen, 'role' => $role]);
                if ($vote) $this->entity_manager->remove($vote);
            }
        }
    }

    private function stage3_building_effects(Town $town) {
        $this->log->info('<info>Processing post-attack building functions</info> ...');

        $novelty_lamps = $this->town_handler->getBuilding( $town, 'small_novlamps_#00', true );

        if ($novelty_lamps) {
            $bats = $novelty_lamps->getLevel() > 0 ? ($novelty_lamps->getLevel() >= 2 ? 2 : 1) : 0;

            $ok = $bats === 0;

            $this->log->debug("Novelty Lamps: Building needs <info>{$bats}</info> batteries to operate.");

            if ($bats > 0) {
                $n = $bats;
                $items = $this->inventory_handler->fetchSpecificItems( $town->getBank(), [new ItemRequest('pile_#00', $bats)] );

                if ($items) {
                    while (!empty($items) && $n > 0) {
                        $item = array_pop($items);
                        $c = $item->getCount();
                        $this->inventory_handler->forceRemoveItem( $item, $n );
                        $n -= $c;
                    }
                    $this->entity_manager->persist( $this->logTemplates->nightlyAttackBuildingBatteries( $novelty_lamps, $bats ) );
                    $ok = true;
                } else $this->log->debug("Novelty Lamps: Not enough batteries in the bank, building is <info>disabled</info>.");

            }

            if ($ok) {
                $this->log->debug("Novelty Lamps: Building is <info>enabled</info>, setting status for all citizens.");
                $novlamp_status = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName('tg_novlamps');
                foreach ($town->getCitizens() as $citizen)
                    if ($citizen->getAlive()) $this->citizen_handler->inflictStatus($citizen, $novlamp_status);
            }
        }
    }

    private function stage4_stranger(Town $town) {
        $town->setStrangerPower( max(0, $town->getStrangerPower() - max(1, ceil($town->getStrangerPower() * 0.2))) );

        $this->log->info("Reducing the stranger's power to <info>{$town->getStrangerPower()}</info> ...");
        if ($town->getStrangerPower() <= 0) $this->entity_manager->persist( $this->logTemplates->strangerDeath( $town ) );
    }

    /**
     * @param Town $town
     * @param EventConf[] $events
     * @return bool
     */
    public function advance_day(Town $town, array $events): bool {
        $this->skip_reanimation = [];

        $this->log->info( "Nightly attack request received for town <info>{$town->getId()}</info> (<info>{$town->getName()}</info>)." );
        if (!$this->check_town($town)) {
            $this->log->info("Precondition failed. Attack is <info>cancelled</info>.");
            if (!empty($town->getCitizens()))
                $town->setDayWithoutAttack($town->getDayWithoutAttack() + 1);
            return false;
        } else $this->log->info("Precondition checks passed. Attack can <info>commence</info>.");

        foreach ($events as $event) $event->hook_nightly_pre($town);

        $this->town_handler->triggerAlways( $town, true );

        if ($town->getStrangerPower() > 0) {
            $this->log->info('Entering <comment>The Stranger\'s Phase</comment>');
            $this->stage0_stranger($town);
        }

        $this->log->info('Entering <comment>Phase 1</comment> - Pre-attack processing');
        $this->stage1_prepare($town);
        $this->stage1_vanish($town);
        $this->stage1_status($town);

        $town->setDay( $town->getDay() + 1);
        $this->log->info('Entering <comment>Phase 2</comment> - The Attack');
        $this->stage2_pre_attack_buildings($town);
        $this->stage2_building_effects($town);
        $this->stage2_day($town);

        if (!$this->exec_reactor) {
            $this->stage2_attack($town);
        } else foreach ($this->deferred_log_entries as $deferred_log_entry)
            $this->entity_manager->persist( $deferred_log_entry );

        $this->stage2_post_attack_buildings($town);
        $this->stage2_post_attack_building_effects($town);

        $this->log->info('Entering <comment>Phase 3</comment> - Dawn of a New Day');
        $this->stage3_status($town);
        $this->stage3_roles($town);
        $this->stage3_zones($town);
        $this->stage3_items($town);
        $this->stage3_pictos($town);
        $this->stage3_building_effects($town);

        if ($town->getStrangerPower() > 0) {
            $this->log->info('Entering <comment>The Second Stranger\'s Phase</comment>');
            $this->stage4_stranger($town);
        }

        foreach ($events as $event) $event->hook_nightly_post($town);

        $this->game_factory->updateTownScore( $town );
        $this->entity_manager->persist( TownRankingProxy::fromTown( $town, true ) );
        foreach ($town->getCitizens() as $citizen) CitizenRankingProxy::fromCitizen( $citizen, true );

        $c = count($this->cleanup);
        $this->log->info("It is now <comment>Day {$town->getDay()}</comment> in <info>{$town->getName()}</info>.");
        $this->town_handler->calculate_zombie_attacks( $town, 3 );
        $this->log->debug( "<info>{$c}</info> entities have been marked for removal." );
        $this->log->info( "<comment>Script complete.</comment>" );

        return true;
    }

    public function get_cleanup_container(): array {
        $cc = $this->cleanup;
        $this->cleanup = [];
        return $cc;
    }
}
