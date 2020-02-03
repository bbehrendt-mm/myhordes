<?php

namespace App\DataFixtures;

use App\Entity\CitizenProfession;
use App\Entity\CitizenStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class CitizenFixtures extends Fixture
{
    public static $profession_data = [
        ['name'=>'none'        ,'label'=>'Gammler' ],
        ['name'=>'basic'       ,'label'=>'Einwohner' ],
        ['name'=>'collec'      ,'label'=>'Buddler' ],
        ['name'=>'guardian'    ,'label'=>'Wächter' ],
        ['name'=>'hunter'      ,'label'=>'Aufklärer' ],
        ['name'=>'tamer'       ,'label'=>'Dompteur' ],
        ['name'=>'tech'        ,'label'=>'Techniker' ],
        ['name'=>'shaman'      ,'label'=>'Schamane' ],
        ['name'=>'survivalist' ,'label'=>'Einsiedler' ],
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

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    protected function insert_professions(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Citizen professions: ' . count(static::$profession_data) . ' fixture entries available.</comment>' );

        // Set up console
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$profession_data) );

        // Iterate over all entries
        foreach (static::$profession_data as $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(CitizenProfession::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new CitizenProfession();

            // Set property
            $entity->setName( $entry['name'] );
            $entity->setLabel( $entry['label'] );

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

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();
        $output->writeln( '<info>Installing fixtures: Citizen Database</info>' );
        $output->writeln("");

        $this->insert_professions( $manager, $output );
        $output->writeln("");
        $this->insert_status_types( $manager, $output );
        $output->writeln("");
    }
}
