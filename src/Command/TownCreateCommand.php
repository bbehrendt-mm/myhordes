<?php


namespace App\Command;


use App\Entity\Inventory;
use App\Entity\Town;
use App\Entity\TownClass;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class TownCreateCommand extends Command
{
    protected static $defaultName = 'app:create-town';

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
        parent::__construct();
    }

    protected function getValidTownTypes(): array {
        return array_map(function(TownClass $entry) {
            return $entry->getName();
        }, $this->entityManager->getRepository(TownClass::class)->findAll());
    }

    protected function configure()
    {
        $this
            ->setDescription('Creates a new town.')
            ->setHelp('This command allows you to create a new, empty town.')

            ->addArgument('townClass', InputArgument::REQUIRED, 'Town type [' . implode(', ', $this->getValidTownTypes()) . ']')
            ->addArgument('citizens', InputArgument::REQUIRED, 'Number of citizens [1 - 40]')
            ->addArgument('name', InputArgument::OPTIONAL, 'Town name');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln(['Town Creator','============','']);

        $town_type     = $input->getArgument('townClass');
        $town_citizens = (int)$input->getArgument('citizens');
        $town_name = $input->getArgument('name') ?: 'Unnamed Town';

        if (!in_array($town_type, $this->getValidTownTypes())) {
            $output->writeln('<error>The given town type is invalid. Check the help for a list of valid types.</error>');
            return -1;
        }

        if ($town_citizens < 1 || $town_citizens > 40 ) {
            $output->writeln('<error>A town must have between 1 and 40 citizens.</error>');
            return -2;
        }

        $output->writeln("<info>Creating a new '$town_type' town called '$town_name' with $town_citizens unlucky inhabitants.</info>");


        $town = new Town();
        $output->writeln('🡒 <comment>Town</comment> container object created.');

        $town->setType( $this->entityManager->getRepository(TownClass::class)->findOneByName($town_type) );
        $output->writeln('🡒 <comment>TownClass</comment> reference established.');

        $town->setPopulation( $town_citizens );
        $output->writeln('🡒 Property <comment>population</comment> set.');

        $town->setName( $town_name );
        $output->writeln('🡒 Property <comment>name</comment> set.');

        $town->setDay( 0 );
        $output->writeln('🡒 Property <comment>day</comment> set.');

        $town->setBank( new Inventory() );
        $output->writeln('🡒 <comment>Inventory</comment> instance created and linked to property <comment>bank</comment>.');

        $output->write('Persisting ... ');
        try {
            $this->entityManager->persist( $town );
            $this->entityManager->flush();
        } catch (Exception $e) {
            $output->writeln('<error>Failed!</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return -3;
        }
        $output->writeln('<info>OK!</info>');

        $table = new Table($output);
        $table->setHeaders(['Class','Property','ID']);
        $table->addRow( ['Town',      'town',      $town->getId()] );
        $table->addRow( ['Inventory', 'town.bank', $town->getBank()->getId()] );
        $table->render();

        return 0;
    }
}