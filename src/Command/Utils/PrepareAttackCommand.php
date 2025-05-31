<?php


namespace App\Command\Utils;


use App\Entity\AttackSchedule;
use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\HeaderStat;
use App\Entity\Picto;
use App\Entity\PictoPrototype;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\Actions\Cache\InvalidateTagsInAllPoolsAction;
use App\Service\Actions\Mercure\BroadcastViaMercureAction;
use App\Service\ConfMaster;
use DateTime;
use DateTimeImmutable;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:utils:prepare-attack',
    description: 'Prepares a given attack.'
)]
class PrepareAttackCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BroadcastViaMercureAction $broadcast,
    )
    {

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command prepares a scheduled attack.')
            ->addArgument('schedule', InputArgument::REQUIRED, 'Schedule ID.')
        ;
        parent::configure();
    }


    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $schedule_id = (int)$input->getArgument('schedule');

        $s = $this->entityManager->getRepository(AttackSchedule::class)->find($schedule_id);
        if (!$s) throw new \Exception("Schedule ID $schedule_id not found.");

        if ($s->getTimestamp() > new DateTime('now')) throw new \Exception("Schedule ID $schedule_id is not due yet.");
        if ($s->getCompleted() || $s->getCompletedAt() !== null) throw new \Exception("Schedule ID $schedule_id has already completed.");

        ($this->broadcast)('attack-commence', ['schedule' => $s->getId()], public: true);

        return 0;
    }
}
