<?php


namespace App\Command\Forum\Group;


use App\Console\AssociativeChoiceQuestion;
use App\Entity\ForumGroup;
use App\Service\CommandHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

abstract class ForumGroupEditCommand extends Command
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly CommandHelper $helper,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('Group', InputArgument::REQUIRED, 'The forum group')
        ;
    }

    protected function getForumGroup(InputInterface $input, OutputInterface $output): ForumGroup {
        $group = $input->getArgument('Group');

        return is_numeric($group)
            ? $this->entityManager->getRepository(ForumGroup::class)->find((int)$group)
            : $this->helper->resolve_string($group, ForumGroup::class, 'Forum Group', $this->getHelper('question'), $input, $output);
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
		/** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if (empty($input->getArgument('Group'))) {
            $groups = $this->entityManager->getRepository(ForumGroup::class)->findAll();
            $input->setArgument('Group', $helper->ask(
                $input, $output, new AssociativeChoiceQuestion('Select an existing forum group:',
                array_combine(
                    array_map( fn(ForumGroup $group) => $group->getId(), $groups ),
                    array_map( fn(ForumGroup $group) => $group->getTitle(), $groups ),
                ))
            ));
        }
    }
}
