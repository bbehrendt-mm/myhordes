<?php


namespace App\Command\Forum\Group;


use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'app:forum-group:configure',
    description: 'Allows configuring forum groups.'
)]
class ForumGroupConfigureCommand extends ForumGroupEditCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setHelp('This command allows enabling or disabling a specific forum group.')

            ->addOption('show-if-single-entry', null, InputOption::VALUE_REQUIRED, 'Display the group if it only has one visible forum in it.')
            ->addOption('sort', null, InputOption::VALUE_REQUIRED, 'Sorting value.')

            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Sets the group name.')
            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'The forum group language')
            ->addOption('localize', null, InputOption::VALUE_NONE, 'Add localized titles for this group interactively.')
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
        $forumGroup = $this->getForumGroup($input, $output);

        if ($input->getOption('name') !== null)
            $forumGroup->setTitle( $input->getOption('name') );

        if ($input->getOption('lang') !== null)
            $forumGroup->setLang( $input->getOption('lang') );

        if ($input->getOption('show-if-single-entry') !== null)
            $forumGroup->setShowIfSingleEntry( (int)$input->getOption('show-if-single-entry') > 0 );

        if ($input->getOption('sort') !== null)
            $forumGroup->setSort( (int)$input->getOption('sort') );

        if ($input->getOption('localize')) {
            $helper = $this->getHelper('question');
            foreach (['de', 'en', 'fr', 'es'] as $lang) {

                $title = trim($helper->ask($input, $output,
                    new Question(
                        "Please enter the title for <info>{$forumGroup->getTitle()}</info> in <info>{$lang}</info> or leave blank to skip.\n > ")) ?? ''
                );

                if (empty($title)) $title = null;
                $forumGroup->setLocalizedTitle($lang, $title);
            }
        }

        $this->entityManager->persist( $forumGroup );
        $this->entityManager->flush();

        $output->writeln("The group <info>[{$forumGroup->getId()}] {$forumGroup->getTitle()}</info> has been updated.");

        return 0;

    }
}
