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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class TownInspectorCommand extends Command
{
    protected static $defaultName = 'app:town';

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Manipulates and lists information about a single town.')
            ->setHelp('This command allows you work on single towns.')
            ->addArgument('TownID', InputArgument::REQUIRED, 'The town ID');
    }

    protected function info(Town $town, OutputInterface $output) {
        $output->writeln('<comment>Common town data</comment>');
        $table = new Table( $output );
        $table->setHeaders( ['ID', 'Open?', 'Name', 'Population', 'Type', 'Day'] );
        $table->addRow([
            $town->getId(),
            $town->isOpen(),
            $town->getName(),
            $town->getCitizenCount() . '/' . $town->getPopulation(),
            $town->getType()->getLabel(),
            $town->getDay()
        ]);
        $table->render();
        $output->writeln("\n\n");

        $output->writeln('<comment>Citizen list</comment>');
        $table = new Table( $output );
        $table->setHeaders( ['UID', 'CID', 'Name', 'Job', 'Alive?','HomeID','InvIDs'] );
        foreach ($town->getCitizens() as $citizen) {
            $table->addRow([
                $citizen->getUser()->getId(),
                $citizen->getId(),
                $citizen->getActive() ? "<comment>{$citizen->getUser()->getUsername()}</comment>" : $citizen->getUser()->getUsername(),
                $citizen->getProfession()->getLabel(),
                (int)$citizen->getAlive(),
                $citizen->getHome()->getId(),
                "<comment>H</comment>:{$citizen->getHome()->getChest()->getId()} <comment>R</comment>:{$citizen->getInventory()->getId()}"
            ]);
        }
        $table->render();

        return 0;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $town = $this->entityManager->getRepository(Town::class)->find( (int)$input->getArgument('TownID') );

        return $this->info($town, $output);
    }
}