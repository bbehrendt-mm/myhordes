<?php


namespace App\Service;


use App\Entity\Building;
use App\Entity\CauseOfDeath;
use App\Entity\Citizen;
use App\Entity\CitizenStatus;
use App\Entity\DigRuinMarker;
use App\Entity\EscapeTimer;
use App\Entity\Town;
use App\Entity\ZombieEstimation;
use App\Entity\Zone;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class NightlyHandler
{
    private $cleanup = [];
    private $skip_reanimation = [];

    private $entity_manager;
    private $log;
    private $citizen_handler;
    private $random;
    private $death_handler;
    private $town_handler;
    private $zone_handler;

    public function __construct(EntityManagerInterface $em, LoggerInterface $log, CitizenHandler $ch,
                                RandomGenerator $rg, DeathHandler $dh, TownHandler $th, ZoneHandler $zh)
    {
        $this->entity_manager = $em;
        $this->citizen_handler = $ch;
        $this->death_handler = $dh;
        $this->citizen_handler->upgrade($dh);
        $this->random = $rg;
        $this->town_handler = $th;
        $this->zone_handler = $zh;
        $this->log = $log;
    }

    private function check_town(Town &$town): bool {
        if ($town->isOpen()) {
            $this->log->debug('The town lobby is <comment>open</comment>!');
            return false;
        }
        return true;
    }

    private function kill_wrap( Citizen &$citizen, CauseOfDeath &$cod, bool $skip_reanimation = false ) {
        $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> dies of <info>{$cod->getLabel()}</info>.");
        $this->death_handler->kill($citizen,$cod,$rr);
        foreach ($rr as $r) $this->cleanup[] = $r;
        if ($skip_reanimation) $this->skip_reanimation[] = $citizen->getId();
    }

    private function stage1_vanish(Town &$town) {
        $this->log->info('<info>Vanishing citizens</info> ...');
        $cod = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneByRef(CauseOfDeath::Vanished);
        foreach ($town->getCitizens() as $citizen)
            if ($citizen->getAlive() && $citizen->getZone()) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> is at <info>{$citizen->getZone()->getX()}/{$citizen->getZone()->getY()}</info> without protection!" );
                $this->kill_wrap($citizen,$cod);
            }
    }

    private function stage1_status(Town &$town) {
        $this->log->info('<info>Processing status-related deaths</info> ...');
        $cod_thirst = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneByRef(CauseOfDeath::Dehydration);
        $cod_addict = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneByRef(CauseOfDeath::Addiction);
        $cod_infect = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneByRef(CauseOfDeath::Infection);

        $status_infected  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'infection' );
        $status_survive   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'hsurvive' );
        $status_thirst2   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'thirst2' );
        $status_drugged   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'drugged' );
        $status_addicted  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'addict' );

        foreach ($town->getCitizens() as $citizen) {

            if (!$citizen->getAlive()) continue;

            if ($citizen->getStatus()->contains( $status_survive )) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> is <info>protected</info> by <info>{$status_survive->getLabel()}</info>." );
                continue;
            }

            if ($citizen->getStatus()->contains( $status_thirst2 )) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>{$status_thirst2->getLabel()}</info>." );
                $this->kill_wrap( $citizen, $cod_thirst, true );
                continue;
            }

            if ($citizen->getStatus()->contains( $status_addicted ) && !$citizen->getStatus()->contains( $status_drugged )) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>{$status_addicted->getLabel()}</info>, but not <info>{$status_drugged->getLabel()}</info>." );
                $this->kill_wrap( $citizen, $cod_addict, true );
                continue;
            }

            if ($citizen->getStatus()->contains( $status_infected )) {
                $this->log->debug( "Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>{$status_infected->getLabel()}</info>." );
                if ($this->random->chance(0.5)) $this->kill_wrap( $citizen, $cod_infect, true );
                continue;
            }
        }
    }

    private function stage2_day(Town &$town) {
        $this->log->info('<info>Updating survival information</info> ...');
        foreach ($town->getCitizens() as $citizen) {
            if (!$citizen->getAlive()) continue;
            $citizen->setSurvivedDays( $citizen->getTown()->getDay() + 1 );
        }
    }

    private function stage2_surprise_attack(Town &$town) {
        $this->log->info('<info>Awakening the dead</info> ...');
        /** @var Citizen[] $houses */
        $houses = [];
        /** @var Citizen[] $targets */
        $targets = [];
        /** @var Building[] $buildings */
        $buildings = [];

        $cod = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneByRef(CauseOfDeath::NightlyAttack);

        foreach ($town->getCitizens() as $citizen) {
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

        foreach ($houses as $id => $corpse) {

            $opts = [];
            if (!empty( $targets )) $opts[] = 1;
            if (!empty( $buildings )) $opts[] = 2;
            if ($town->getWell() > 0) $opts[] = 3;

            if (empty($opts)) {
                $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> has nothing to do.");
                continue;
            }

            switch ($this->random->pick($opts, 1)) {
                case 1:
                    $victim = array_pop($targets);
                    $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> attacks and kills <info>{$victim->getUser()->getUsername()}</info>.");
                    $this->kill_wrap( $victim, $cod );
                    break;
                case 2:
                    $construction = array_pop($buildings);
                    $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> destroys the progress on <info>{$construction->getPrototype()->getLabel()}</info>.");
                    $construction->setAp(0);
                    break;
                case 3:
                    $d = min($town->getWell(), 20);
                    $this->log->debug("The corpse of citizen <info>{$corpse->getUser()->getUsername()}</info> removes <info>{$d} water rations</info> from the well.");
                    $town->setWell( $town->getWell() - $d );
                    break;
            }
        }
    }

    private function stage2_attack(Town &$town) {
        $this->log->info('<info>Marching the horde</info> ...');
        $cod = $this->entity_manager->getRepository(CauseOfDeath::class)->findOneByRef(CauseOfDeath::NightlyAttack);
        $status_terror  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'terror' );

        $def  = $this->town_handler->calculate_town_def( $town );
        /** @var ZombieEstimation $est */
        $est = $this->entity_manager->getRepository(ZombieEstimation::class)->findOneByTown($town,$town->getDay());
        $zombies = $est ? $est->getZombies() : 0;

        $overflow = !$town->getDoor() ? max(0, $zombies - $def) : $zombies;
        $this->log->debug("The down has <info>{$def}</info> defense and is attacked by <info>{$zombies}</info> Zombies. The door is <info>" . ($town->getDoor() ? 'open' : 'closed') . "</info>!");
        $this->log->debug("<info>{$overflow}</info> Zombies have entered the town!");

        if ($overflow <= 0) return;

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

        if ($town->getDay() <= 5) {
            $attacking = $overflow;
            $targets = $this->random->pick($targets, mt_rand( ceil(count($targets) * 0.15), ceil(count($targets) * 0.35 )), true);
        } else {
            if     ($town->getDay() <= 14) $x = 1;
            elseif ($town->getDay() <= 18) $x = 4;
            elseif ($town->getDay() <= 23) $x = 5;
            else                           $x = 6;

            $attacking = min(($town->getDay() + $x) * max(10, count($targets)), $overflow);
            $targets = $this->random->pick($targets, ceil(count($targets) * 0.85), true);
        }

        $this->log->debug("<info>{$attacking}</info> Zombies are attacking <info>" . count($targets) . "</info> citizens!");
        $max = ceil(4 * $attacking/count($targets));

        $left = count($targets);
        foreach ($targets as $target) {
            $left--;
            $home = $target->getHome();
            if ($left <= 0) $force = $attacking;
            else $force = $attacking > 0 ? mt_rand(1, max(1,min($max,$attacking-$left)) ) : 0;
            $def = $this->town_handler->calculate_home_def($home);
            $this->log->debug("Citizen <info>{$target->getUser()->getUsername()}</info> is attacked by <info>{$force}</info> zombies and protected by <info>{$def}</info> home defense!");
            if ($force > $def) $this->kill_wrap($target, $cod);
            elseif ($this->random->chance( 0.75 * ($force/max(1,$def)) )) {
                $this->citizen_handler->inflictStatus( $target, $status_terror );
                $this->log->debug("Citizen <info>{$target->getUser()->getUsername()}</info> now suffers from <info>{$status_terror->getLabel()}</info>");
            }
            $attacking -= $force;
        }
    }

    private function stage3_status(Town &$town) {
        $this->log->info('<info>Processing status changes</info> ...');

        $status_survive   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'hsurvive' );
        $status_hasdrunk  = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'hasdrunk' );
        $status_infection = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'infection' );
        $status_camping   = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'camper' );

        $status_clear_list = ['hasdrunk','haseaten','immune','hsurvive','drugged','healed','tg_dice','tg_cards','tg_clothes'];
        $status_morph_list = [
            'drunk' => $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName( 'hungover' ),
        ];

        foreach ($town->getCitizens() as $citizen) {

            if (!$citizen->getAlive()) continue;

            if ($citizen->getStatus()->contains($status_survive))
                $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> is <info>protected</info> by <info>{$status_survive->getLabel()}</info>.");
            else
            {
                if (!$citizen->getStatus()->contains($status_hasdrunk)) {
                    $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> has <info>not</info> drunk today. <info>Increasing</info> thirst level.");
                    $this->citizen_handler->increaseThirstLevel( $citizen );
                }
                if (!$citizen->getStatus()->contains($status_infection) && $this->citizen_handler->isWounded( $citizen )) {
                    $this->log->debug("Citizen <info>{$citizen->getUser()->getUsername()}</info> is <info>wounded</info>. Adding an <info>infection</info>.");
                    $this->citizen_handler->increaseThirstLevel( $citizen );
                }
            }

            $this->log->debug("Setting appropriate camping status for citizen <info>{$citizen->getUser()->getUsername()}</info> (who is <info>" . ($citizen->getZone() ? 'outside' : 'inside') . "</info> the town)...");
            if ($citizen->getZone()) $citizen->addStatus( $status_camping );
            else $citizen->removeStatus( $status_camping );

            $this->log->debug("Removing volatile counters for citizen <info>{$citizen->getUser()->getUsername()}</info>...");
            $citizen->setWalkingDistance(0);
            $this->citizen_handler->setAP($citizen,false,6,0);
            $citizen->getWellCounter()->setTaken(0);
            $citizen->getDigTimers()->clear();
            foreach ($this->entity_manager->getRepository( EscapeTimer::class )->findAllByCitizen( $citizen ) as $et)
                $this->cleanup[] = $et;
            foreach ($this->entity_manager->getRepository( DigRuinMarker::class )->findAllByCitizen( $citizen ) as $drm)
                $this->cleanup[] = $drm;

            foreach ($citizen->getStatus() as $st)
                if (in_array($st->getName(),$status_clear_list)) {
                    $this->log->debug("Removing volatile status from citizen <info>{$citizen->getUser()->getUsername()}</info>: <info>{$st->getLabel()}</info>.");
                    $this->citizen_handler->removeStatus( $citizen, $st );
                }
            foreach ($citizen->getStatus() as $st)
                if (isset($status_morph_list[$st->getName()])) {
                    $this->log->debug("Morphing volatile status from citizen <info>{$citizen->getUser()->getUsername()}</info>: <info>{$st->getLabel()}</info> -> <info>{$status_morph_list[$st->getName()]->getLabel()}</info>.");
                    $this->citizen_handler->removeStatus( $citizen, $st );
                    $this->citizen_handler->inflictStatus( $citizen, $status_morph_list[$st->getName()] );
                }
        }
    }

    private function stage3_zones(Town &$town) {
        $this->log->info('<info>Processing changes in the World Beyond</info> ...');

        $this->log->debug('Spreading zombies ...');
        $this->zone_handler->dailyZombieSpawn($town);

        $research_tower = $this->town_handler->getBuilding($town, 'small_gather_#02', true);
        $watchtower     = $this->town_handler->getBuilding($town, 'item_tagger_#00',  true);

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

        $reco_counter = [0,0];
        foreach ($town->getZones() as $zone) {

            $distance = sqrt( pow($zone->getX(),2) + pow($zone->getY(),2) );
            if ($zone->getCitizens()->count() || round($distance) <= $discover_range) {
                if ($zone->getDiscoveryStatus() !== Zone::DiscoveryStateCurrent) {
                    $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Set discovery state to <info>current</info>." );
                    $zone->setDiscoveryStatus(Zone::DiscoveryStateCurrent);
                    $zone->setZombieStatus( Zone::ZombieStateEstimate );
                }
            } elseif ($zone->getDiscoveryStatus() === Zone::DiscoveryStateCurrent) {
                $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Set discovery state to <info>past</info>." );
                $zone->setDiscoveryStatus(Zone::DiscoveryStatePast);
                $zone->setZombieStatus( Zone::ZombieStateUnknown );
            }

            if ($zone->getDirection() === $wind && round($distance) > 2) {
                $reco_counter[1]++;
                if ($this->random->chance( $recovery_chance )) {
                    $digs = mt_rand(5, 10);
                    $zone->setDigs( min( $zone->getDigs() + $digs, 25 ) );
                    $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Recovering by <info>{$digs}</info> to <info>{$zone->getDigs()}</info>." );
                    $reco_counter[0]++;
                }

                if ($zone->getPrototype() && $this->random->chance( $recovery_chance ) ) {
                    $rdigs = mt_rand(1, 5);
                    $zone->setRuinDigs( min( $zone->getRuinDigs() + $rdigs, 10 ) );
                    $this->log->debug( "Zone <info>{$zone->getX()}/{$zone->getY()}</info>: Recovering ruin by <info>{$rdigs}</info> to <info>{$zone->getRuinDigs()}</info>." );
                }
            }
        }
        $this->log->debug("Recovered <info>{$reco_counter[0]}</info>/<info>{$reco_counter[1]}</info> zones." );
    }

    public function advance_day(Town &$town): bool {
        $this->skip_reanimation = [];

        $this->log->info( "Nightly attack request received for town <info>{$town->getId()}</info> (<info>{$town->getName()}</info>)." );
        if (!$this->check_town($town)) {
            $this->log->info("Precondition failed. Attack will is <info>cancelled</info>.");
            return false;
        } else $this->log->info("Precondition checks passed. Attack can <info>commence</info>.");

        $this->log->info('Entering <comment>Phase 1</comment> - Pre-attack processing');
        $this->stage1_vanish($town);
        $this->stage1_status($town);

        $this->log->info('Entering <comment>Phase 2</comment> - The Attack');
        $this->stage2_day($town);
        $this->stage2_surprise_attack($town);
        $this->stage2_attack($town);

        $this->log->info('Entering <comment>Phase 3</comment> - Dawn of a New Day');
        $this->stage3_status($town);
        $this->stage3_zones($town);

        $this->log->info( "<comment>Script complete.</comment>" );
        $c = count($this->cleanup);
        $this->log->debug( "<info>{$c}</info> entities have been marked for removal." );

        return false;
    }

    public function get_cleanup_container() {
        $cc = $this->cleanup;
        $this->cleanup = [];
        return $cc;
    }
}