<?php


namespace App\Service;


use App\Entity\Citizen;
use App\Entity\CitizenHome;
use App\Entity\CitizenProfession;
use App\Entity\Inventory;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\WellCounter;
use App\Entity\Zone;
use App\Entity\ZonePrototype;
use Doctrine\ORM\EntityManagerInterface;

class GameFactory
{
    private $entity_manager;
    private $validator;
    private $locksmith;
    private $item_factory;
    private $status_factory;

    const ErrorNone = 0;
    const ErrorTownClosed          = ErrorHelper::BaseTownSelectionErrors + 1;
    const ErrorUserAlreadyInGame   = ErrorHelper::BaseTownSelectionErrors + 2;
    const ErrorUserAlreadyInTown   = ErrorHelper::BaseTownSelectionErrors + 3;
    const ErrorNoDefaultProfession = ErrorHelper::BaseTownSelectionErrors + 4;

    public function __construct( EntityManagerInterface $em, GameValidator $v, Locksmith $l, ItemFactory $if, StatusFactory $sf)
    {
        $this->entity_manager = $em;
        $this->validator = $v;
        $this->locksmith = $l;
        $this->item_factory = $if;
        $this->status_factory = $sf;
    }

    private static $town_name_snippets = [
        [
            ['Tödliches','Modriges','Schimmliges','Eisiges','Rotes','Einsames','Ghulverseuchtes','Zombifiziertes','Bekanntes','Abgenagtes','Verstörendes','Letztes'],
            ['Wasserloch','Hospital','Trainingslager','Pony','Niemandsland','Gericht','Reich','Dreckloch','Gehirn','Rattenloch','Gebiet','Lager'],
        ],
        [
            ['Eifriger','Weinender','Schimmliger','Alberner','Autoritärer','Einsamer','Triefender','Kontaminierter','Verschlafener','Masochistischer','Besoffener','Kontaminierter'],
            ['Müllberg','Seelenfänger','Monarch','Fels','Untergang','Wald','Folterkeller','Bezirk','Bunker','Tisch','Husten','Laster'],
        ],
        [
            ['Eifrige','Modrige','Glitschige','Eisige','Drogensüchtige','Gespenstische','Ghulverseuchte','Zombifizierte','Bewegte','Betrunkene','Virulente','Betroffene'],
            ['Metzger','Zombieforscher','Gestalten','Wächter','Todesgesänge','Schaffner','Soldaten','Zwillinge','Regionen','Oberfläche','Schmarotzer','Entwickler'],
        ],
        [
            ['Ghulgebeine','Gesänge','Schmerzen','Schreie','Räume','Meute','Ghetto','Bürger','Hinterlassenschaft','Revier','Folterkeller','Alkoholpanscher'],
            ['des Todes','der Verdammnis','ohne Zukunft','am Abgrund','der Verwirrten','ohne Ideen','der Versager','der Ghule','der Superhelden','der Mutlosen','der Fröhlichen','der Revolutionäre'],
        ],
    ];

    public function createTownName(): string {
        return implode(' ', array_map(function(array $list): string {
            return $list[ array_rand( $list ) ];
        }, static::$town_name_snippets[array_rand( static::$town_name_snippets )]));
    }

    private function getDefaultWell(TownClass $town_type): int {
        return mt_rand( $town_type->getWellMin(), $town_type->getWellMax() );
    }

    private function getDefaultRuinCount(TownClass $town_type): int {
        return mt_rand( $town_type->getRuinsMin(), $town_type->getRuinsMax() );
    }

    private function getDefaultZoneResolution( TownClass $town_type, ?int &$offset_x, ?int &$offset_y ): int {
        $resolution = mt_rand( $town_type->getMapMin(), $town_type->getMapMax() );
        $safe_border = ceil($resolution/4.0);
        $offset_x = $safe_border + mt_rand(0, $resolution - 2*$safe_border);
        $offset_y = $safe_border + mt_rand(0, $resolution - 2*$safe_border);
        return $resolution;
    }

    const RespawnModeNone = 0;
    const RespawnModeAuto = 1;
    const RespawnModeForce = 2;

    public function dailyZombieSpawn( Town &$town, int $cycles = 1, int $mode = self::RespawnModeAuto ) {

        /** @var Zone[] $zones */
        $zones = $town->getZones()->getValues();
        $zone_db = []; $empty_zones = [];
        foreach ($zones as &$zone) {
            $despair = max(0,( $zone->getInitialZombies() - $zone->getZombies() - 1 ) / 2);
            if (!isset($zone_db[$zone->getX()])) $zone_db[$zone->getX()] = [];
            $zone_db[$zone->getX()][$zone->getY()] = max(0,$zone->getZombies() - $despair);
            if ($zone_db[$zone->getX()][$zone->getY()] == 0) $empty_zones[] = $zone;
        }

        // Respawn
        if ($mode === self::RespawnModeForce || ($mode === self::RespawnModeAuto && count($empty_zones) > (count($zones)* 18/20))) {
            $d = $town->getDay();
            $keys = $d == 1 ? [array_rand($empty_zones)] : array_rand($empty_zones, $d);
            foreach ($keys as $spawn_zone_id)
                /** @var Zone $spawn_zone */
                $zone_db[ $zones[$spawn_zone_id]->getX() ][ $zones[$spawn_zone_id]->getY() ] = mt_rand(1,6);
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

                        $spread_chance = 1 - pow($zone_original_db[$x][$y] == 0 ? 0.875 : 0.875, $zone_zed_difference);
                        if (mt_rand(0,100) > (100*$spread_chance)) continue;

                        $max_zeds = ceil($zone_zed_difference/$adj_zones_total);
                        $min_zeds = floor($max_zeds * ($adj_zones_infected / $adj_zones_total));
                        $current_zone_zombies += mt_rand($min_zeds, $max_zeds);
                    }

                }

            foreach ($zone_db as $x => &$zone_row)
                foreach ($zone_row as $y => &$current_zone_zombies) {
                    if ($x === 0 && $y === 0) continue;
                    if ($current_zone_zombies > 0) $current_zone_zombies += max(0,mt_rand(-1, 2));
                }
        }

        foreach ($town->getZones() as &$zone) {
            if ($zone->getX() === 0 && $zone->getY() === 0) continue;

            $zombies = $zone_db[$zone->getX()][$zone->getY()];
            $zone->setZombies( $zombies );
            $zone->setInitialZombies( $zombies );
        }

    }

    public function createTown( ?string $name, int $population, string $type ): ?Town {
        if (!$this->validator->validateTownType($type) || !$this->validator->validateTownPopulation( $population, $type ))
            return null;

        $townClass = $this->entity_manager->getRepository(TownClass::class)->findOneByName( $type );
        $town = new Town();
        $town
            ->setType( $townClass )
            ->setPopulation( $population )
            ->setName( $name ?: $this->createTownName() )
            ->setBank( new Inventory() )
            ->setWell( $this->getDefaultWell($townClass) );

        $map_resolution = $this->getDefaultZoneResolution( $townClass, $ox, $oy );
        for ($x = 0; $x < $map_resolution; $x++)
            for ($y = 0; $y < $map_resolution; $y++) {
                $zone = new Zone();
                $zone
                    ->setX( $x - $ox )
                    ->setY( $y - $oy )
                    ->setFloor( new Inventory() )
                    ->setZombies( 0 )->setInitialZombies( 0 );
                $town->addZone( $zone );
            }

        $ruin_types = $this->entity_manager->getRepository(ZonePrototype::class)->findAll();
        $spawn_ruins = $this->getDefaultRuinCount( $townClass );
        /** @var Zone[] $zone_list */
        $zone_list = array_filter($town->getZones()->getValues(), function(Zone $z) {return $z->getX() !== 0 || $z->getY() !== 0;});
        shuffle($zone_list);
        for ($i = 0; $i < min($spawn_ruins+2,count($zone_list)); $i++) {
            $zombies_base = 0;
            if ($i < $spawn_ruins) {
                $zombies_base = 1 + floor(min(1,sqrt( pow($zone_list[$i]->getX(),2) + pow($zone_list[$i]->getY(),2) )/18) * 9);
                $zone_list[$i]->setPrototype( $ruin_types[array_rand($ruin_types)] );
            } else
                $zombies_base = 1 + floor(min(1,sqrt( pow($zone_list[$i]->getX(),2) + pow($zone_list[$i]->getY(),2) )/18) * 3);

            if ($zombies_base > 0) {
                $zombies_base = max(1, mt_rand( floor($zombies_base * 0.8), ceil($zombies_base * 1.2) ) );
                $zone_list[$i]->setZombies( $zombies_base )->setInitialZombies( $zombies_base );
            }
        }

        $this->dailyZombieSpawn( $town, 2, self::RespawnModeNone );
        return $town;
    }

    public function createCitizen( Town &$town, User &$user, ?int &$error ): ?Citizen {
        $error = self::ErrorNone;
        $lock = $this->locksmith->waitForLock('join-town');

        $active_citizen = $this->entity_manager->getRepository(Citizen::class)->findActiveByUser( $user );
        if ($active_citizen !== null) {
            $error = self::ErrorUserAlreadyInGame;
            return null;
        }

        if (!$town->isOpen()) {
            $error = self::ErrorTownClosed;
            return null;
        }
        foreach ($town->getCitizens() as $existing_citizen)
            if ($existing_citizen->getUser()->getId() === $user->getId()) {
                $error = self::ErrorUserAlreadyInTown;
                return null;
            }

        $base_profession = $this->entity_manager->getRepository(CitizenProfession::class)->findDefault();
        if ($base_profession === null) {
            $error = self::ErrorNoDefaultProfession;
            return null;
        }

        $home = new CitizenHome();
        $home->setChest( $chest = new Inventory() );

        $citizen = new Citizen();
        $citizen->setUser( $user )
            ->setTown( $town )
            ->setProfession( $base_profession )
            ->setInventory( new Inventory() )
            ->setHome( $home )
            ->setWellCounter( new WellCounter() )
            ->addStatus( $this->status_factory->createStatus( 'clean' ) );

        $chest
            ->addItem( $this->item_factory->createItem( 'chest_citizen_#00' ) )
            ->addItem( $this->item_factory->createItem( 'food_bag_#00' ) );

        return $citizen;
    }
}