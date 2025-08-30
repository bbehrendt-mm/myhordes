<?php


namespace App\Command\User;


use App\Entity\Citizen;
use App\Entity\User;
use App\Service\UserFactory;
use App\Service\UserHandler;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'app:user:test_name',
    description: 'Tests a user name'
)]
class UserTestNameCommand extends Command
{
    public function __construct(
        private readonly UserHandler $userHandler,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to test if a user name is valid.')
            ->addArgument('name',     InputArgument::REQUIRED, 'The user\'s name.')
            ->addOption('length', null,    InputOption::VALUE_REQUIRED, 'The max length', 16)
            ->addOption('no-regex', null,    InputOption::VALUE_NONE, 'Disable regex checks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = $input->getArgument('name');
        $valid = $this->userHandler->isNameValid( $username, $too_long, $input->getOption('length'), $input->getOption('no-regex'), $debug );

        if ($valid) $output->writeln("Username <info>$username</info> is <bg=green>valid</>.");
        else $output->writeln("Username <info>$username</info> is <bg=red>invalid</>.");

        foreach ($debug as $rule => $value)
            $output->writeln("<info>$rule</info>: '" . json_encode($value, JSON_PRETTY_PRINT) . "'");

        return 0;
    }
}
