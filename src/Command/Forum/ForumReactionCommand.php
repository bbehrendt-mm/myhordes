<?php


namespace App\Command\Forum;


use App\Entity\Forum;
use App\Entity\ForumUsagePermissions;
use App\Entity\Post;
use App\Entity\ReactionSet;
use App\Entity\Thread;
use App\Entity\UserGroup;
use App\Enum\ForumType;
use App\Service\CommandHelper;
use App\Service\ConfMaster;
use App\Service\Media\MediaService;
use App\Service\Morph\MorphService;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:forum:create-reactions',
    description: 'Allows creating reaction instances in forums.'
)]
class ForumReactionCommand extends Command
{
    use ForumIconCollectorTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommandHelper $helper,
        private readonly MorphService $morphService,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command prepares forum reactions in forums.')
            ->addArgument('ForumID', InputArgument::OPTIONAL, 'The Forum ID')
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
        $forum = $input->getArgument('ForumID');
        $forums = $forum
            ? $this->entityManager->getRepository(Forum::class)
                ->matching( Criteria::create(true)->where( Criteria::expr()->in('id', [(int)$forum])) )
                ->map( fn(Forum $f) => $f->getId() )
                ->toArray()
            : $this->entityManager->getRepository(Forum::class)
                ->matching( Criteria::create(true) )
                ->filter( fn(Forum $f) => $f->isUsingEmoteReactions() )
                ->map( fn(Forum $f) => $f->getId() )
                ->toArray();

        if (count($forums) === 0) {
            $output->writeln( '<fg=red>No forums found.</>' );
            return 0;
        }

        $output->writeln( 'Selected <info>' . count($forums) . '</info> forums.' );
        $io = new SymfonyStyle($input, $output);
        $io->table(
            ['ID','Name'],
            $this->entityManager->getRepository(Forum::class)
                ->matching( Criteria::create(true)->where( Criteria::expr()->in('id', $forums) ) )
                ->map( fn(Forum $f) => [$f->getId(), $f->getTitle()] )
                ->toArray()
        );

        if ($this->getHelper('question')->ask($input, $output, new Question('Continue? (y/n) ', 'n')) !== 'y')
            return -1;

        $threads = $this->entityManager->getRepository(Thread::class)
            ->createQueryBuilder('t')
            ->select('t.id')
            ->where('t.forum IN (:forums)')->setParameter('forums', $forums)
            ->getQuery()->getSingleColumnResult();

        $this->helper->leChunk(
            $output,
            Post::class,
            100,
            Criteria::create(true)->where( Criteria::expr()->in('thread', $threads) ),
            true,
            false,
            function(Post $post) {
                $this->entityManager->persist(
                    $this->morphService->firstOrCreateMorph( ReactionSet::class, $post)
                );
            },
            true
        );

        return 0;

    }
}
