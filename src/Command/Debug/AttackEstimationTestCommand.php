<?php


namespace App\Command\Debug;

use App\Entity\Town;
use App\Service\Actions\Game\EstimateZombieAttackAction;
use App\Service\Actions\Game\PrepareZombieAttackEstimationAction;
use App\Service\CommandHelper;
use App\Service\ConfMaster;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:debug:attack-estimation',
    description: 'Debug command to test the attack estimation.'
)]
class AttackEstimationTestCommand extends Command
{
    public function __construct(
        private readonly CommandHelper $helper,
        private readonly ConfMaster $conf,
        private readonly PrepareZombieAttackEstimationAction $prepareZombieAttackEstimationAction,
        private readonly EstimateZombieAttackAction $estimateZombieAttackAction,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Debug attack estimations.')

            ->addArgument('TownID', InputArgument::REQUIRED, 'The town ID')
            ->addArgument('day', InputArgument::REQUIRED, 'The day')
            ->addArgument('to_day', InputArgument::OPTIONAL, 'Calculate until day')

            ->addOption('estimate', null, InputOption::VALUE_NONE, 'Perform estimations')
            ->addOption('tomorrow', null, InputOption::VALUE_NONE, 'Use with --estimate to run in planner mode')
            //->addOption('data', null, InputOption::VALUE_REQUIRED, 'Data to transmit', '{}')
        ;
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var Town $town */
        $town = $this->helper->resolve_string($input->getArgument('TownID'), Town::class, 'Town', $this->getHelper('question'), $input, $output);
        $day = (int)$input->getArgument('day');
        $to_day = (int)($input->getArgument('to_day') ?? $input->getArgument('day'));

        if ($day <= 0 || $to_day < $day) throw new \Exception('Invalid day');

        $bounding_violations = 0;
        $predictor_violations = 0;
        $predictor_visible_violations = 0;

        $io = new SymfonyStyle($input, $output);
        $head = ['Property'];
        $base = [
            [ 'Zombies (total)' ],
            [ 'Range Min' ],
            [ 'Range Max' ],
            [ 'Offset Min' ],
            [ 'Offset Max' ],
            [ 'Best Estimation Min' ],
            [ 'Best Estimation Max' ],
        ];

        $days = [];

        $conf = $this->conf->getTownConfiguration( $town );

        for ($d = $day; $d <= $to_day; ++$d) {
            $days[$d] = [
                $estimation = ($this->prepareZombieAttackEstimationAction)($conf, $d),
                $min_estimation = ($this->prepareZombieAttackEstimationAction)($conf, $d, 0.0),
                $max_estimation = ($this->prepareZombieAttackEstimationAction)($conf, $d, 1.0),
            ];

            $head[] = "Day $d";
            $base[0][] = $estimation->getZombies();
            $base[1][] = $min_estimation->getZombies();
            $base[2][] = $max_estimation->getZombies();
            $base[3][] = $estimation->getOffsetMin();
            $base[4][] = $estimation->getOffsetMax();
            $base[5][] = $estimation->getTargetMin();
            $base[6][] = $estimation->getTargetMax();
        }

        $io->table($head, $base);

        if (!$input->getOption('estimate')) return 0;

        $planner = $input->getOption('tomorrow');

        foreach ($days as $day => [$actual, $min, $max]) {
            $c = 0;

            $lines = [];

            do {
                $est = ($this->estimateZombieAttackAction)(
                    $conf,
                    $actual,
                    $c,
                    blocks: $planner ? true : 1,
                    fallback_seed: $town->getId() + $day
                );

                if ($est->getMin() < $min->getZombies() || $est->getMax() > $max->getZombies()) {
                    $predictor_violations++;
                    if ($est->getVisible()) $predictor_visible_violations++;
                }

                $default = $est->getVisible() ? 'white' : 'gray';
                $min_col = ($est->getMin() < $min->getZombies()) ? ($est->getVisible() ? 'bright-red' : 'red') : $default;
                $max_col = ($est->getMax() > $max->getZombies()) ? ($est->getVisible() ? 'bright-red' : 'red') : $default;
                $lines[] = [
                    "<fg=$default>$c</>", "<fg=$default>" . round($est->getEstimation() * 100) . '%</>',
                    "<fg=$min_col>{$est->getMin()}</>",
                    "<fg=$max_col>{$est->getMax()}</>",
                    "<fg=$default>" . ($est->getMax() - $est->getMin()) . '</>'
                ];

                $c++;

            } while ($c < $town->getPopulation() && $est->getEstimation() < 1);

            $bound_range = round(100 * (($max->getZombies() - $min->getZombies()) / $max->getZombies()));
            $io->section("Estimations for day $day | {$actual->getZombies()} | {$actual->getTargetMin()} - {$actual->getTargetMax()} | Bounds at {$min->getZombies()} - {$max->getZombies()} / {$bound_range}");

            if ($actual->getTargetMin() < $min->getZombies() || $actual->getTargetMax() > $max->getZombies()) {
                $io->writeln('<bg=red>Target range bounding violation!</>');
                $bounding_violations++;
            }

            $io->table( ['Citizens', 'Quality', 'Min', 'Max', 'Span'], $lines );
        }

        if ($bounding_violations > 0) $io->writeln("<bg=red>Total bounding violations: </><bg=red;options=bold>{$bounding_violations}</>");
        if ($predictor_violations > 0) $io->writeln("<bg=red>Total predictor violations: </><bg=red;options=bold>{$predictor_violations}</><bg=red>, visible: </><bg=red;options=bold>{$predictor_visible_violations}</>");

        return 0;
    }
}
