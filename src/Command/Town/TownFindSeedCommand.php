<?php


namespace App\Command\Town;

use App\Entity\TownClass;
use App\Entity\Zone;
use App\Service\ConfMaster;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\GameValidator;
use App\Service\Locksmith;
use App\Service\TownHandler;
use App\Structures\EventConf;
use App\Structures\TownSetup;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use App\Entity\Town;

#[AsCommand(
    name: 'app:town:findseed',
    description: 'Try to find a seed that will output a town with the given parameters.'
)]
class TownFindSeedCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private GameFactory $gameFactory;
    private GameValidator $gameValidator;
    private ConfMaster $conf;
    private TownHandler $townHandler;
    private Translator $trans;
    private GameProfilerService $gps;


    public function __construct(EntityManagerInterface $em, GameFactory $f, GameValidator $v, ConfMaster $conf,
                                TownHandler $th,  Translator $translator, GameProfilerService $gps)
    {
        $this->entityManager = $em;
        $this->gameFactory = $f;
        $this->gameValidator = $v;
        $this->conf = $conf;
        $this->townHandler = $th;
        $this->trans = $translator;
        $this->gps = $gps;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command helps you to create the town you want.')

            ->addArgument('townClass', InputArgument::REQUIRED, 'Town type [' . implode(', ', $this->gameValidator->getValidTownTypes()) . ']')
            ->addArgument('citizens', InputArgument::REQUIRED, 'Number of citizens [1 - 40]')
            ->addArgument('lang', InputArgument::REQUIRED, 'Town language')
            ->addArgument('desire', InputArgument::REQUIRED, 'File containing JSON of desired properties for this town')
            ->addArgument('name', InputArgument::OPTIONAL, 'Town name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(['Town Finder (also known as Tinder)','============','']);

        $town_type     = $input->getArgument('townClass');
        $town_citizens = (int)$input->getArgument('citizens');
        $town_lang = $input->getArgument('lang');
        $desire = $input->getArgument('desire');
        $town_name = $input->getArgument('name');

		if(!file_exists($desire)) {
            $output->writeln('<error>The specified desire file for this town is missing.</error>');
            return -1;
		}

		$json = file_get_contents($desire);
		$desire_data = json_decode($json, true);

		if(!$desire_data || empty($desire_data)) {
            $output->writeln('<error>'.$json.'.</error>');
            $output->writeln('<error>Something went wrong while reading the desire file. Make sure it contains valid JSON.</error>');
            return -1;
		}

        $this->trans->setLocale($town_lang !== 'multi' ? $town_lang : 'en');
		
        $current_events = $this->conf->getCurrentEvents();
        $name_changers = array_values(
            array_map( fn(EventConf $e) => $e->get( EventConf::EVENT_MUTATE_NAME ), array_filter($current_events,fn(EventConf $e) => $e->active() && $e->get( EventConf::EVENT_MUTATE_NAME )))
        );

		$seed = 1;
		$match = false;
        
		$start = hrtime(true);
		while(!$match) {
			$setup = hrtime(true);
			$town = $this->gameFactory->createTown(
				new TownSetup($town_type, name: $town_name, language: $town_lang, population: $town_citizens, seed: $seed, nameMutator: $name_changers[0] ?? null)
				, $output
			);
			$setupTime = hrtime(true)-$setup;
	
			if ($town === null) {
				$output->writeln('<error>Town creation service terminated with an error. Please check if the town parameters are valid.</error>');
				return -1;
			}
	
			$check = hrtime(true);
			$match = $this->checkTownDesire($output, $desire_data, $town);
			$checkTime = hrtime(true)-$check;
	
			if($match) {
				$output->writeln('Found in '.(floor((hrtime(true)-$start)/100000000)*100).'ms');
				$output->writeln('Town with seed '.$seed.' matched desire !');
			} else {
				$output->writeln($seed.'... ('.number_format($setupTime/1000000, 2).'ms + '.number_format($checkTime/1000000, 2).'ms)');
			}
			$seed++;
		}

        return 0;
    }

	protected function checkTownDesire(OutputInterface $output, $desire_data, Town $town) {
		if($desire_data['well']['min'] && $desire_data['well']['min'] > $town->getWell()) {
			return false;
		}
		if($desire_data['well']['max'] && $desire_data['well']['max'] < $town->getWell()) {
			return false;
		}
		$mapsize = $town->getMapSize();
		if($desire_data['map']['size']['min'] && $desire_data['map']['size']['min'] > $mapsize) {
			return false;
		}
		if($desire_data['map']['size']['max'] && $desire_data['map']['size']['max'] < $mapsize) {
			return false;
		}
		$ruins_conditions = [
			"whitelist" => [],
			"blacklist" => [],
			"distances" => [],
			"x" => [],
			"y" => []
		];
		foreach($desire_data['ruins'] as $id => $building_reqs) {
			if($building_reqs['present'] === true) $ruins_conditions['whitelist'][$id] = true;
			if($building_reqs['present'] === false) $ruins_conditions['blacklist'][$id] = true;

			if($building_reqs['distance']['min'] || $building_reqs['distance']['max']) {
				$ruins_conditions['distances'][$id] = [$building_reqs['distance']['min'], $building_reqs['distance']['max']];
			}
			if($building_reqs['position']['x']['min'] || $building_reqs['position']['x']['max']) {
				$ruins_conditions['x'][$id] = [$building_reqs['position']['x']['min'], $building_reqs['position']['x']['max']];
			}
			if($building_reqs['position']['y']['min'] || $building_reqs['position']['y']['max']) {
				$ruins_conditions['y'][$id] = [$building_reqs['position']['y']['min'], $building_reqs['position']['y']['max']];
			}
		}
		if(
			!empty($ruins_conditions['whitelist']) ||
			!empty($ruins_conditions['blacklist']) ||
			!empty($ruins_conditions['distances']) ||
			!empty($ruins_conditions['x']) ||
			!empty($ruins_conditions['y'])
		) {
			foreach($town->getZones() as $zone) {
				if($zone->getPrototype() === null) continue;

				if(!$this->checkZoneDesire($output, $ruins_conditions, $zone)) {
					return false;
				}
			}
		}

		if(!empty($ruins_conditions['whitelist'])) {
			return false;
		}

		return true;
	}

	protected function checkZoneDesire($output, & $ruins_conditions, $zone) {
		$ruin = $zone->getPrototype()->getIcon();
		if(isset($ruins_conditions['whitelist'][$ruin])) {
			unset($ruins_conditions['whitelist'][$ruin]);
		}
		if(isset($ruins_conditions['blacklist'][$ruin])) {
			return false;
		}
		if(isset($ruins_conditions['distance'][$ruin])) {
			if($ruins_conditions['distance'][$ruin][0] && $ruins_conditions['distance'][$ruin][0] > $zone->getDistance()) {
				return false;
			}
			if($ruins_conditions['distance'][$ruin][1] && $ruins_conditions['distance'][$ruin][1] < $zone->getDistance()) {
				return false;
			}
		}
		if(isset($ruins_conditions['x'][$ruin])) {
			if($ruins_conditions['x'][$ruin][0] && $ruins_conditions['x'][$ruin][0] > $zone->getX()) {
				return false;
			}
			if($ruins_conditions['x'][$ruin][1] && $ruins_conditions['x'][$ruin][1] < $zone->getX()) {
				return false;
			}
		}
		if(isset($ruins_conditions['y'][$ruin])) {
			if($ruins_conditions['y'][$ruin][0] && $ruins_conditions['y'][$ruin][0] > $zone->getY()) {
				return false;
			}
			if($ruins_conditions['y'][$ruin][1] && $ruins_conditions['y'][$ruin][1] < $zone->getY()) {
				return false;
			}
		}
		return true;
	}
}