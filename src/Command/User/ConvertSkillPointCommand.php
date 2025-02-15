<?php


namespace App\Command\User;


use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\CitizenRole;
use App\Entity\CitizenStatus;
use App\Entity\Forum;
use App\Entity\ForumUsagePermissions;
use App\Entity\HeroExperienceEntry;
use App\Entity\HeroSkillPoint;
use App\Entity\Season;
use App\Entity\Thread;
use App\Entity\TownRankingProxy;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserSponsorship;
use App\Enum\Configuration\TownSetting;
use App\Enum\HeroXPType;
use App\Kernel;
use App\Service\CitizenHandler;
use App\Service\CommandHelper;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\StatusFactory;
use App\Service\User\UserUnlockableService;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:user:convert-skill-points',
    description: 'Converts legacy skill points to new skill points.',
)]
class ConvertSkillPointCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommandHelper          $helper,
        private readonly UserUnlockableService  $unlockableService,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('UserId', InputArgument::OPTIONAL, 'The user ID')
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Score value', 50)
        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $for = (int)$input->getOption('days') ?: 50;
        $season = $this->entityManager->getRepository(Season::class)->findOneBy(['current' => true]);

        $this->helper->leChunk(
            $output,
            User::class,
            100,
            $input->getArgument('UserId') ? ['id' => (int)$input->getArgument('UserId')] : [],
            true,
            false,
            function(User $u) use (&$season, $for) {
                $legacyPoints = min(2, $this->entityManager->createQueryBuilder()
                    ->from(HeroExperienceEntry::class, 'x')
                    // Join ranking proxies so we can observe their DISABLED state
                    ->leftJoin(TownRankingProxy::class, 't', 'WITH', 'x.town = t.id')
                    ->leftJoin(CitizenRankingProxy::class, 'c', 'WITH', 'x.citizen = c.id')
                    // Scope to given user
                    ->where('x.user = :user')->setParameter('user', $u)
                    // Disregard disabled entries
                    ->andWhere('x.disabled = 0')
                    ->andWhere('(t.disabled = 0 OR t.disabled IS NULL)')
                    ->andWhere('(c.disabled = 0 OR c.disabled IS NULL)')

                    ->select('MAX(x.reset)')
                    ->andWhere('x.type != :legacy')->setParameter('legacy', HeroXPType::Legacy->value)
                    ->andWhere('x.season = :season')->setParameter('season', $season)
                    ->getQuery()->getSingleScalarResult() ?? 0) - $this->unlockableService->getResetPackPoints( $u );

                if ($legacyPoints > 0)
                    for ($i = 0; $i < $legacyPoints; $i++)
                        $this->entityManager->persist( (new HeroSkillPoint())
                            ->setUser( $u )
                            ->setSeason( $season )
                            ->setReceivedAt( new \DateTimeImmutable() )
                            ->setOriginalDays( $for )
                            ->setDays( $for )
                        );

                return false;
            },
            true,
            function() use (&$season) { $season = $this->entityManager->getRepository(Season::class)->findOneBy(['current' => true]); }
        );

        return 0;
    }
}
