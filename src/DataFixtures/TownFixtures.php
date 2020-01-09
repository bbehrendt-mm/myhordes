<?php

namespace App\DataFixtures;

use App\Entity\TownClass;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;

class TownFixtures extends Fixture
{
    public static $town_class_data = [
        ['name'=>'small'  ,'label'=>'Kleine Stadt',       'well' => [90,180], 'map' => [12,14], 'ruins' => [ 7,10] ],
        ['name'=>'remote' ,'label'=>'Entfernte Regionen', 'well' => [90,180], 'map' => [25,27], 'ruins' => [17,20] ],
        ['name'=>'panda'  ,'label'=>'Pandämonium',        'well' => [60, 90], 'map' => [25,27], 'ruins' => [15,20] ],
        ['name'=>'custom' ,'label'=>'Private Stadt',      'well' => [90,180], 'map' => [25,27], 'ruins' => [17,20] ],
    ];

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
    }

    protected function insert_town_classes(ObjectManager $manager, ConsoleOutputInterface $out) {
        $out->writeln( '<comment>Town classes: ' . count(static::$town_class_data) . ' fixture entries available.</comment>' );

        // Set up console
        $table = new Table( $out );
        $table->setHeaders( ['ID','Name','Label'] );
        $progress = new ProgressBar( $out->section() );
        $progress->start( count(static::$town_class_data) );

        // Iterate over all entries
        foreach (static::$town_class_data as $entry) {
            // Get existing entry, or create new one
            $entity = $this->entityManager->getRepository(TownClass::class)->findOneByName( $entry['name'] );
            if ($entity === null) $entity = new TownClass();

            // Set property
            $entity
                ->setName( $entry['name'] )
                ->setLabel( $entry['label'] )
                ->setMapMin( $entry['map'][0] )
                ->setMapMax( $entry['map'][1] )
                ->setWellMin( $entry['well'][0] )
                ->setWellMax( $entry['well'][1] )
                ->setRuinsMin( $entry['ruins'][0] )
                ->setRuinsMax( $entry['ruins'][1] )
            ;

            $manager->persist( $entity );

            // Set table entry
            $table->addRow( [$entity->getId(),$entity->getName(),$entity->getLabel()] );
            $progress->advance();
        }

        $manager->flush();
        $progress->finish();
        $table->render();
    }

    public function load(ObjectManager $manager) {
        $output = new ConsoleOutput();
        $output->writeln( '<info>Installing fixtures: Town Content Database</info>' );
        $output->writeln("");

        $this->insert_town_classes( $manager, $output );
        $output->writeln("");
    }
}
