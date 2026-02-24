<?php


namespace App\Command\Town;

use App\Entity\Town;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\CommandHelper;
use App\Service\ConfMaster;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\TownHandler;
use App\Structures\EventConf;
use App\Structures\TownSetup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zenstruck\ScheduleBundle\Schedule\SelfSchedulingCommand;
use Zenstruck\ScheduleBundle\Schedule\Task\CommandTask;

#[AsCommand(
    name: 'app:town:watchdog',
    description: 'Ensures enough towns are open according to configuration'
)]
class WatchdogCommand extends Command implements SelfSchedulingCommand
{

    public function __construct(
        protected EntityManagerInterface $em,
        protected ConfMaster $confMaster,
        protected GameFactory $gameFactory,
        protected GameProfilerService $gps,
        protected TownHandler $townHandler,
        protected CommandHelper $helper
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Let's check if there is enough opened town
        $openTowns = $this->em->getRepository(Town::class)->findOpenTown();

        $openTowns = array_filter( $openTowns, function(Town $t): bool {
            if ($t->getPassword() || $t->getCreator()) return false;
            return true;
        } );

        $conf = $this->confMaster->getGlobalConf();

        $count = [];
        $langs = $conf->get( MyHordesSetting::TownGeneratorLanguages );
        foreach ($langs as $lang) $count[$lang] = [];

        foreach ($openTowns as $openTown) {
            if (!isset($count[$openTown->getLanguage()])) continue;
            if (!isset($count[$openTown->getLanguage()][$openTown->getType()->getName()])) $count[$openTown->getLanguage()][$openTown->getType()->getName()] = 0;
            $count[$openTown->getLanguage()][$openTown->getType()->getName()]++;
        }

        $minOpenTown = [
            'small'  => $conf->get( MyHordesSetting::TownGeneratorMinSmall ),
            'remote' => $conf->get( MyHordesSetting::TownGeneratorMinRemote ),
            'panda'  => $conf->get( MyHordesSetting::TownGeneratorMinPanda ),
            'custom' => $conf->get( MyHordesSetting::TownGeneratorMinCustom ),
        ];

        foreach ($langs as $lang)
            foreach ($minOpenTown as $type => $min) {
                $current = $count[$lang][$type] ?? 0;
                while ($current < $min) {
                    $this->helper->capsule("app:town:create $type 40 $lang --by cron", $output, "Creating town of type $type ($lang)... ");
                    $current++;
                }
            }

        return 0;
    }

    public function schedule(CommandTask $task): void
    {
        $task
            ->everyMinute()
            ->withoutOverlapping(600)
            ->description('Automated town creator')
        ;
    }
}
