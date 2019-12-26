<?php


namespace App\Command;


use App\Entity\Citizen;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class UserInfoCommand extends Command
{
    protected static $defaultName = 'app:users';

    private $entityManager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entityManager = $em;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Lists information about users.')
            ->setHelp('This command allows you list users, or get information about a specific user.')

            ->addOption('validation-pending', 'v0', InputOption::VALUE_NONE, 'Only list users with pending validation.')
            ->addOption('validated', 'v1', InputOption::VALUE_NONE, 'Only list validated users.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var User[] $users */
        $users = array_filter( $this->entityManager->getRepository(User::class)->findAll(), function(User $user) use ($input) {

            if ($input->getOption( 'validation-pending' ) && $user->getValidated()) return false;
            if ($input->getOption( 'validated' ) && !$user->getValidated()) return false;

            return true;
        } );

        $table = new Table( $output );
        $table->setHeaders( ['ID', 'Name', 'Mail', 'Validated?', 'ActCitID.','ValTkn.'] );

        foreach ($users as $user) {
            $activeCitizen = $this->entityManager->getRepository(Citizen::class)->findActiveByUser( $user );
            $pendingValidation = $user->getPendingValidation();
            $table->addRow( [
                $user->getId(), $user->getUsername(), $user->getEmail(), $user->getValidated() ? '1' : '0',
                $activeCitizen ? $activeCitizen->getId() : '-',
                $pendingValidation ? $pendingValidation->getPkey() : '-'
            ] );
        }

        $table->render();
        $output->writeln('Found a total of <info>' . count($users) . '</info> users.');

        return 0;
    }
}