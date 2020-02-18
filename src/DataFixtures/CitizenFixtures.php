<?php

namespace App\DataFixtures;

use App\Entity\BuildingPrototype;
use App\Entity\CitizenHomePrototype;
use App\Entity\CitizenHomeUpgradeCosts;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\CitizenProfession;
use App\Entity\CitizenStatus;
use App\Entity\ItemGroup;
use App\Entity\ItemGroupEntry;
use App\Entity\ItemPrototype;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class CitizenFixtures extends Fixture implements DependentFixtureInterface
{
    public static $profession_data = [
        ['icon' => 'looser', 'name'=>'none'        ,'label'=>'Gammler',    'items' => ['basic_suit_#00'], 'items_alt' => ['basic_suit_dirt_#00'] ],
        ['icon' => 'basic',  'name'=>'basic'       ,'label'=>'Einwohner',  'items' => ['basic_suit_#00'], 'items_alt' => ['basic_suit_dirt_#00'] ],
        ['icon' => 'dig',    'name'=>'collec'      ,'label'=>'Buddler',    'items' => ['basic_suit_#00','pelle_#00'], 'items_alt' => ['basic_suit_dirt_#00'] ],
        ['icon' => 'shield', 'name'=>'guardian'    ,'label'=>'Wächter',    'items' => ['basic_suit_#00','shield_#00'], 'items_alt' => ['basic_suit_dirt_#00'] ],
        ['icon' => 'vest',   'name'=>'hunter'      ,'label'=>'Aufklärer',  'items' => ['basic_suit_#00','vest_off_#00'], 'items_alt' => ['basic_suit_dirt_#00','vest_on_#00'] ],
        ['icon' => 'tamer',  'name'=>'tamer'       ,'label'=>'Dompteur',   'items' => ['basic_suit_#00','tamed_pet_#00'], 'items_alt' => ['basic_suit_dirt_#00','tamed_pet_drug_#00','tamed_pet_off_#00'] ],
        ['icon' => 'tech',   'name'=>'tech'        ,'label'=>'Techniker',  'items' => ['basic_suit_#00','keymol_#00'], 'items_alt' => ['basic_suit_dirt_#00'] ],
        ['icon' => 'shaman', 'name'=>'shaman'      ,'label'=>'Schamane',   'items' => ['basic_suit_#00','shaman_#00'], 'items_alt' => ['basic_suit_dirt_#00'] ],
        ['icon' => 'book',   'name'=>'survivalist' ,'label'=>'Einsiedler', 'items' => ['basic_suit_#00','surv_book_#00'], 'items_alt' => ['basic_suit_dirt_#00'] ],
    ];

    public static $citizen_status = [
        ['name' => 'clean', 'label' => 'Clean'],
        ['name' => 'hasdrunk', 'label' => 'Getrunken'],
        ['name' => 'haseaten', 'label' => 'Satt'],
        ['name' => 'camper', 'label' => 'Umsichtiger Camper'],
        ['name' => 'immune', 'label' => 'Immunisiert'],
        ['name' => 'hsurvive', 'label' => 'Den Tod besiegen'],
        ['name' => 'tired', 'label' => 'Erschöpfung'],
        ['name' => 'terror', 'label' => 'Angststarre'],
        ['name' => 'thirst1', 'label' => 'Durst'],
        ['name' => 'thirst2', 'label' => 'Dehydriert'],
        ['name' => 'drugged', 'label' => 'Rauschzustand'],
        ['name' => 'addict', 'label' => 'Drogenabhängig'],
        ['name' => 'infection', 'label' => 'Infektion'],
        ['name' => 'drunk', 'label' => 'Trunkenheit'],
        ['name' => 'hungover', 'label' => 'Kater'],
        ['name' => 'wound1', 'label' => 'Verwundung - Kopf'],
        ['name' => 'wound2', 'label' => 'Verwundung - Hände'],
        ['name' => 'wound3', 'label' => 'Verwundung - Arme'],
        ['name' => 'wound4', 'label' => 'Verwundung - Bein'],
        ['name' => 'wound5', 'label' => 'Verwundung - Auge'],
        ['name' => 'wound6', 'label' => 'Verwundung - Fuß'],
        ['name' => 'ghul', 'label' => 'Ghul'],
        ['name' => 'healed', 'label' => 'Bandagiert'],

        ['name' => 'tg_dice' ],
        ['name' => 'tg_cards'],
    ];

    public static $home_levels = [
        0 => [ 'label' => 'Feldbett',              'icon' => 'home_lv0', 'def' =>  0, 'ap' => 0, 'resources' => [], 'building' => null, 'upgrades' => false, 'theft' => false ],
        1 => [ 'label' => 'Zelt',                  'icon' => 'home_lv1', 'def' =>  1, 'ap' => 2, 'resources' => [], 'building' => null, 'upgrades' => true,  'theft' => false ],
        2 => [ 'label' => 'Baracke',               'icon' => 'home_lv2', 'def' =>  3, 'ap' => 6, 'resources' => [], 'building' => null, 'upgrades' => true,  'theft' => false ],
        3 => [ 'label' => 'Hütte',                 'icon' => 'home_lv3', 'def' =>  6, 'ap' => 4, 'resources' => ['wood2_#00' => 1], 'building' => null, 'upgrades' => true,  'theft' => false ],
        4 => [ 'label' => 'Haus',                  'icon' => 'home_lv4', 'def' => 10, 'ap' => 6, 'resources' => ['metal_#00' => 1], 'building' => null, 'upgrades' => true,  'theft' => false ],
        5 => [ 'label' => 'Umzäuntes Haus',        'icon' => 'home_lv5', 'def' => 14, 'ap' => 6, 'resources' => ['wood2_#00' => 3, 'metal_#00' => 2], 'building' => 'small_strategy_#01', 'upgrades' => true, 'theft' => true ],
        6 => [ 'label' => 'Befestigte Unterkunft', 'icon' => 'home_lv6', 'def' => 20, 'ap' => 7, 'resources' => ['concrete_wall_#00' => 1, 'wood2_#00' => 3, 'metal_#00' => 4], 'building' => 'small_strategy_#01', 'upgrades' => true, 'theft' => true ],
        7 => [ 'label' => 'Bunker',                'icon' => 'home_lv7', 'def' => 28, 'ap' => 7, 'resources' => ['meca_parts_#00' => 3, 'concrete_wall_#00' => 2, 'plate_raw_#00' => 1, 'metal_#00' => 6], 'building' => 'small_strategy_#01', 'upgrades' => true, 'theft' => true ],
        8 => [ 'label' => 'Schloss',               'icon' => 'home_lv8', 'def' => 50, 'ap' => 7, 'resources' => ['meca_parts_#00' => 5, 'concrete_wall_#00' => 2, 'plate_raw_#00' => 3, 'wood2_#00' => 5, 'metal_#00' => 10], 'building' => 'small_strategy_#01', 'upgrades' => true, 'theft' => true ],
    ];

    public static $home_upgrades = [
        [ 'name' => 'curtain', 'label' => 'Großer Vorhang', 'desc' => 'Mit dieser alten, schmutzigen Jutesackleinwand kannst du deine Habseligkeiten vor den neugierigen Blicken deiner Nachbarn schützen.', 'levels' => [
            1 => [ 4, [] ]
        ] ],
        [ 'name' => 'lab', 'label' => 'Hobbylabor', 'desc' => 'Ein in dein Wohnzimmer geschaufeltes Loch dient dir als Versuchsküche für deine pharmazeutischen Experimente.', 'levels' => [
            1 => [ 6, ['machine_1_#00' => 1] ], 2 => [ 4, ['electro_#00' => 1] ], 3 => [ 4, ['tube_#00' => 1] ], 4 => [ 6, ['engine_#00' => 1] ]
        ] ],
        [ 'name' => 'kitchen', 'label' => 'Küche', 'desc' => 'In dieser notdürftig zusammengeschraubten Küche können schmackhafte und \'gesunde\' Speisen zubereitet werden.', 'levels' => [
            1 => [ 6, [] ], 2 => [ 3, ['small_knife_#00' => 1]], 3 => [ 4, ['machine_2_#00' => 1] ], 4 => [ 4, ['machine_3_#00' => 1]]
        ] ],
        [ 'name' => 'alarm', 'label' => 'Primitives Alarmsystem', 'desc' => 'Eisenteile, die an einem Faden hängen - so einfach und so effektiv kann ein Alarmsystem sein. Wenn jemand versuchen sollte, bei dir einzubrechen, wird er zwangsläufig die halbe Stadt aufwecken...', 'levels' => [
            1 => [ 4, ['metal_#00' => 1] ]
        ] ],
        [ 'name' => 'rest', 'label' => 'Ruheecke', 'desc' => 'Was hier als \'Ruhe-Ecke\' bezeichnet wird, ist in Wahrheit nichts anderes als ein mit Kartons gefülltes Loch im Boden... der ideale Ort, wenn deine Kräfte schwinden und du dich für ein Nickerchen zurückziehen willst.', 'levels' => [
            1 => [ 6, [] ], 2 => [ 3, ['wood2_#00' => 1] ], 3 => [ 4, ['bed_#00' => 1] ]
        ] ],
        [ 'name' => 'lock', 'label' => 'Türschloss', 'desc' => 'Dieses rudimentäre Schließsystem schützt dein Haus vor Diebstahl.', 'levels' => [
            1 => [ 6, ['chain_#00' => 1] ]
        ] ],
        [ 'name' => 'fence', 'label' => 'Zaun', 'desc' => 'Wenn dich deine Wände nicht mehr ausreichend schützen, solltest du den Bau eines Zauns erwägen.', 'levels' => [
            1 => [ 3, ['chain_#00' => 1, 'metal_beam_#00' => 1] ]
        ] ],
        [ 'name' => 'chest', 'label' => 'Stauraum', 'desc' => 'Deine persönliche Truhe vergrößert sich. ', 'levels' => [
            1 => [ 2, [] ], 2 => [ 2, [] ], 3 => [ 2, [] ], 4 => [ 3, [] ], 5 => [ 4, [] ], 6 => [ 6, [] ], 7 => [ 6, [] ], 8 => [ 6, [] ], 9 => [ 6, [] ], 10 => [ 6, [] ], 11 => [ 6, [] ], 12 => [ 6, [] ], 13 => [ 6, [] ]
        ] ],
        [ 'name' => 'defense', 'label' => 'Verstärkungen', 'desc' => 'Dein Haus wird mit allen zur Verfügung stehenden Mitteln technisch verstärkt und auf Vordermann gebraucht. Diese Maßnahmen verlängern dein Leben... zumindest ein wenig.', 'levels' => [
            1 => [ 3, [] ], 2 => [ 3, ['fence_#00' => 1] ], 3 => [ 3, ['fence_#00' => 1] ], 4 => [ 3, ['fence_#00' => 1] ], 5 => [ 6, ['fence_#00' => 1] ], 6 => [ 6, ['fence_#00' => 1] ], 7 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ], 8 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ], 9 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ], 10 => [ 6, ['fence_#00' => 1, 'metal_#00' => 1] ]
        ] ],
    ];

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @throws Exception
     */
    protected function insert_professions(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Citizen professions: ' . count(static::$profession_data) . ' fixture entries available.</comment>' );

        // Set up console
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$profession_data) );

        // Iterate over all entries
        foreach (static::$profession_data as $entry) {
            // Get existing entry, or create new one
            /** @var CitizenProfession $entity */
            $entity = $this->entityManager->getRepository(CitizenProfession::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new CitizenProfession();
            else {
                $entity->getProfessionItems()->clear();
                $entity->getAltProfessionItems()->clear();
            }

            // Set property
            $entity
                ->setName( $entry['name'] )
                ->setLabel( $entry['label'] )
                ->setIcon( $entry['icon'] );

            foreach ( $entry['items'] as $p_item ) {
                $i = $manager->getRepository(ItemPrototype::class)->findOneByName( $p_item );
                if (!$i) throw new Exception('Item prototype not found: ' . $p_item);
                $entity->addProfessionItem($i);
            }

            foreach ( $entry['items_alt'] as $p_item ) {
                $i = $manager->getRepository(ItemPrototype::class)->findOneByName( $p_item );
                if (!$i) throw new Exception('Item prototype not found: ' . $p_item);
                $entity->addAltProfessionItem($i);
            }

            $manager->persist( $entity );

            // Set table entry
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
    }

    protected function insert_status_types(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Status: ' . count(static::$citizen_status) . ' fixture entries available.</comment>' );

        // Set up console
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$citizen_status) );

        // Iterate over all entries
        foreach (static::$citizen_status as $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(CitizenStatus::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new CitizenStatus();

            // Set property
            $entity->setName( $entry['name'] );
            $entity->setLabel( isset($entry['label']) ? $entry['label'] : $entry['name'] );
            $entity->setIcon( isset($entry['icon']) ? $entry['icon'] : $entry['name'] );
            $entity->setHidden( !isset($entry['label']) );

            $manager->persist( $entity );

            // Set table entry
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @throws Exception
     */
    protected function insert_home_prototypes(ObjectManager $manager, ConsoleOutputInterface $out)
    {
        $out->writeln('<comment>Home Prototypes: ' . count(static::$home_levels) . ' fixture entries available.</comment>');

        // Set up console
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$citizen_status) );

        // Iterate over all entries
        foreach (static::$home_levels as $level => $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(CitizenHomePrototype::class)->findOneByLevel( $level );
            if ($entity === null) $entity = new CitizenHomePrototype();

            $entity->setLevel($level)->setAp( $entry['ap'] )->setIcon( $entry['icon'] )
                ->setAllowSubUpgrades( $entry['upgrades'] )->setDefense( $entry['def'] )->setLabel( $entry['label'] )
                ->setTheftProtection( $entry['theft'] );

            $building = empty($entry['building']) ? null : $manager->getRepository(BuildingPrototype::class)->findOneByName( $entry['building'] );
            if (!empty($building) && !$building) throw new Exception("Unable to locate building prototype '{$entry['building']}'");
            $entity->setRequiredBuilding( $building );

            if (empty($entry['resources'])) {
                if ($entity->getResources()) {
                    $manager->remove( $entity->getResources() );
                    $entity->setResources( null );
                }
            } else {

                if ($entity->getResources()) $entity->getResources()->getEntries()->clear();
                else $entity->setResources( (new ItemGroup())->setName( "hu_{$level}_res" ) );

                foreach ( $entry['resources'] as $item => $count ) {

                    $ip = $manager->getRepository(ItemPrototype::class)->findOneByName( $item );
                    if (!$item) throw new Exception("Unable to locate item prototype '{$item}'");
                    $entity->getResources()->addEntry( (new ItemGroupEntry())->setPrototype( $ip )->setChance( $count ) );

                }

            }
            // Persist
            $manager->persist($entity);

            // Set table entry
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
    }

    /**
     * @param ObjectManager $manager
     * @param ConsoleOutputInterface $out
     * @throws Exception
     */
    protected function insert_home_upgrades(ObjectManager $manager, ConsoleOutputInterface $out)
    {
        $out->writeln('<comment>Home Upgrades: ' . count(static::$home_upgrades) . ' fixture entries available.</comment>');

        // Set up console
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$citizen_status) );

        // Iterate over all entries
        foreach (static::$home_upgrades as $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(CitizenHomeUpgradePrototype::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new CitizenHomeUpgradePrototype();

            $entity->setName( $entry['name'] )->setLabel( $entry['label'] )->setDescription( $entry['desc'] )
                ->setIcon( $entry['icon'] ?? $entry['name'] );

            // Persist & flush
            $manager->persist($entity);
            $manager->flush();

            // Refresh
            $entity = $this->entityManager->getRepository(CitizenHomeUpgradePrototype::class)->findOneByName( $entry['name'] );

            foreach ( $entry['levels'] as $level => $res ) {
                $lv_entry = $manager->getRepository(CitizenHomeUpgradeCosts::class)->findOneByPrototype( $entity, $level );
                if (!$lv_entry) $lv_entry = (new CitizenHomeUpgradeCosts())->setPrototype($entity)->setLevel( $level );

                $lv_entry->setAp( $res[0] );
                if (empty($res[1])) {
                    if ($lv_entry->getResources()) {
                        $manager->remove( $lv_entry->getResources() );
                        $lv_entry->setResources( null );
                    }
                } else {

                    if ($lv_entry->getResources()) $lv_entry->getResources()->getEntries()->clear();
                    else $lv_entry->setResources( (new ItemGroup())->setName( "hu_{$entry['name']}_{$level}_res" ) );

                    foreach ( $res[1] as $item => $count ) {

                        $ip = $manager->getRepository(ItemPrototype::class)->findOneByName( $item );
                        if (!$item) throw new Exception("Unable to locate item prototype '{$item}'");
                        $lv_entry->getResources()->addEntry( (new ItemGroupEntry())->setPrototype( $ip )->setChance( $count ) );

                    }
                }

                $manager->persist( $lv_entry );
            }

            // Set table entry
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
    }


    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();

        try {
            $output->writeln( '<info>Installing fixtures: Citizen Database</info>' );
            $output->writeln("");

            $this->insert_professions( $manager, $output );
            $output->writeln("");
            $this->insert_status_types( $manager, $output );
            $output->writeln("");

            $this->insert_home_prototypes($manager, $output);
            $output->writeln("");
            $this->insert_home_upgrades($manager, $output);
        } catch (Exception $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
        }

    }

    public function getDependencies()
    {
        return [ RecipeFixtures::class, ItemFixtures::class ];
    }
}
