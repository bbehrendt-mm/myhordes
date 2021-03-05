<?php


namespace App\Command\Town;

use App\Entity\TownClass;
use App\Entity\Zone;
use App\Service\ConfMaster;
use App\Service\GameFactory;
use App\Service\GameValidator;
use App\Service\Locksmith;
use App\Service\TownHandler;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class TownCreateCommand extends Command
{
    protected static $defaultName = 'app:town:create';

    private EntityManagerInterface $entityManager;
    private GameFactory $gameFactory;
    private GameValidator $gameValidator;
    private ConfMaster $conf;
    private TownHandler $townHandler;
    private Translator $trans;


    public function __construct(EntityManagerInterface $em, GameFactory $f, GameValidator $v, ConfMaster $conf,
                                TownHandler $th,  Translator $translator)
    {
        $this->entityManager = $em;
        $this->gameFactory = $f;
        $this->gameValidator = $v;
        $this->conf = $conf;
        $this->townHandler = $th;
        $this->trans = $translator;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Creates a new town.')
            ->setHelp('This command allows you to create a new, empty town.')

            ->addArgument('townClass', InputArgument::REQUIRED, 'Town type [' . implode(', ', $this->gameValidator->getValidTownTypes()) . ']')
            ->addArgument('citizens', InputArgument::REQUIRED, 'Number of citizens [1 - 40]')
            ->addArgument('lang', InputArgument::REQUIRED, 'Town language')
            ->addArgument('name', InputArgument::OPTIONAL, 'Town name')

            ->addOption('simulate', null, InputOption::VALUE_NONE, 'Only simulates town creation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $simulate = $input->getOption('simulate');

        $output->writeln(['Town Creator','============','']);

        $town_type     = $input->getArgument('townClass');
        $town_citizens = (int)$input->getArgument('citizens');
        $town_name = $input->getArgument('name');
        $town_lang = $input->getArgument('lang');

        $this->trans->setLocale($town_lang !== 'multi' ? $town_lang : 'en');

        $output->writeln("<info>Creating a new '$town_type' town " . ($town_name === null ? '' : "called '$town_name' ") . " (" . $town_lang . ") with $town_citizens unlucky inhabitants.</info>");
        $town = $this->gameFactory->createTown($town_name, $town_lang, $town_citizens, $town_type);

        if ($town === null) {
            $output->writeln('<error>Town creation service terminated with an error. Please check if the town parameters are valid.</error>');
            return -1;
        }

        if (!$simulate) {
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

            $current_event = $this->conf->getCurrentEvent();
            if ($current_event->active()) {
                $output->write("Applying current event '{$current_event->name()}' ... ");
                if (!$this->townHandler->updateCurrentEvent($town, $current_event)) {
                    $this->entityManager->clear();
                    $output->writeln('<error>Failed!</error>');
                } else {
                    $this->entityManager->persist($town);
                    $this->entityManager->flush();
                    $output->writeln('<info>OK!</info>');
                }
            }

            $output->writeln("<comment>Empty town '" . $town->getName() . "' was created successfully!</comment>");
        }

        $table = new Table($output);
        $table->setHeaders(['Property','Value']);

        foreach ($this->conf->getTownConfiguration( $town )->raw() as $name => $value) {
            if (is_bool($value)) $value = $value ? 'true' : 'false';
            elseif (is_array($value)) $value = empty($value) ? '[]' : implode("\n", array_map(function ($entry) {
                return is_array($entry) ? implode(", ", $entry) : $entry;
            }, $value));
            $table->addRow([$name, "<info>{$value}</info>"]);
        }
        $table->render();

        $table = new Table($output);
        $table->setHeaders(['Class','Property','ID']);
        $table->addRow( ['Town',      'town',      $town->getId()] );
        $table->addRow( ['Inventory', 'town.bank', $town->getBank()->getId()] );

        $table->render();

        $table = new Table($output);
        $table->setHeaders(['Ruin', 'Location', 'Distance', 'Theorical Min', 'Theorical Max']);
        /** @var Zone $zone */
        foreach ($town->getZones() as $zone) {
            if($zone->getPrototype() === null) continue;
            $table->addRow( [$this->trans->trans($zone->getPrototype()->getLabel(), [], 'game'), "[{$zone->getX()}, {$zone->getY()}]", $zone->getDistance(), $zone->getPrototype()->getMinDistance(), $zone->getPrototype()->getMaxDistance()] );
        }

        $table->render();

        return 0;
    }
}