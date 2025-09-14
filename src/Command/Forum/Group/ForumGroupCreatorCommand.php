<?php


namespace App\Command\Forum\Group;


use App\Entity\ForumGroup;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'app:forum-group:create',
    description: 'Allows creation of new forum groups.'
)]
class ForumGroupCreatorCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows creating a new forum group.')
            ->addArgument('Name', InputArgument::REQUIRED, 'The Forum Name')

            ->addOption('lang', 'l', InputOption::VALUE_REQUIRED, 'The forum group language', 'mu')

            ->addOption('localize', null, InputOption::VALUE_NONE, 'Add localized titles for this group interactively.')
            ->addOption('enable', null, InputOption::VALUE_NONE, 'Enables the newly created group right away.')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
		/** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        while (empty($input->getArgument('Name')))
            $input->setArgument('Name', $helper->ask($input, $output, new Question("Please enter the forum group name:\n > ")));
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $helper = $this->getHelper('question');

        $this->entityManager->persist($newGroup = (new ForumGroup())
            ->setTitle( $input->getArgument('Name') )
            ->setLang( $input->getOption('lang') )
            ->setEnabled( $input->getOption('enable') )
        );

        if ($input->getOption('localize')) {
            foreach (['de', 'en', 'fr', 'es'] as $lang) {

                $title = trim($helper->ask($input, $output, new Question("Please enter the title for <info>{$newGroup->getTitle()}</info> in <info>{$lang}</info> or leave blank to skip.\n > ")) ?? '');
                if (empty($title)) continue;

                $newGroup->setLocalizedTitle($lang, $title);
            }

            $this->entityManager->persist($newGroup);
        }

        $this->entityManager->flush();
        $output->writeln("New forum group <info>[{$newGroup->getId()}] {$newGroup->getTitle()}</info> created.");
        return 0;

    }
}
