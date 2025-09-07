<?php


namespace App\Command\Forum\Group;


use App\Entity\Citizen;
use App\Entity\CitizenRole;
use App\Entity\CitizenStatus;
use App\Entity\Forum;
use App\Entity\ForumGroup;
use App\Entity\ForumUsagePermissions;
use App\Entity\UserGroup;
use App\Kernel;
use App\Service\CitizenHandler;
use App\Service\CommandHelper;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\StatusFactory;
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
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\HttpKernel\KernelInterface;

#[AsCommand(
    name: 'app:forum-group:toggle',
    description: 'Allows enabling/disabling forum groups.'
)]
class ForumGroupToggleCommand extends ForumGroupEditCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setHelp('This command allows enabling or disabling a specific forum group.')

            ->addOption('enable', null, InputOption::VALUE_NONE, 'Enables the group.')
            ->addOption('disable', null, InputOption::VALUE_NONE, 'Disables the group.')

        ;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('enable') && $input->getOption('disable'))
            throw new \Exception('--enable and --disable are mutually exclusive.');

        $forumGroup = $this->getForumGroup($input, $output);

        if ($input->getOption('enable'))
            $forumGroup->setEnabled(true);
        elseif ($input->getOption('disable'))
            $forumGroup->setEnabled(false);
        else {
            $state = $forumGroup->isEnabled() ? 'enabled' : 'disabled';
            $verb = $forumGroup->isEnabled() ? 'disable' : 'enable';

            $helper = $this->getHelper('question');
            $forumGroup->setEnabled(
                !$forumGroup->isEnabled() === $helper->ask($input, $output, new ConfirmationQuestion("The group <info>[{$forumGroup->getId()}] {$forumGroup->getTitle()}</info> is currently <info>{$state}</info>. <info>{$verb}</info> now (y/n)?\n > "))
            );
        }

        $this->entityManager->persist( $forumGroup );
        $this->entityManager->flush();

        $state = $forumGroup->isEnabled() ? 'enabled' : 'disabled';
        $output->writeln("The group <info>[{$forumGroup->getId()}] {$forumGroup->getTitle()}</info> is now <info>{$state}</info>.");

        return 0;

    }
}
