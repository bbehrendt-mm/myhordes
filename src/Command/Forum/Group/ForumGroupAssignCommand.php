<?php


namespace App\Command\Forum\Group;


use App\Console\AssociativeChoiceQuestion;
use App\Entity\Forum;
use Doctrine\Common\Collections\Criteria;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

#[AsCommand(
    name: 'app:forum-group:assign',
    description: 'Allows assigning/disassigning forums to forum groups.'
)]
class ForumGroupAssignCommand extends ForumGroupEditCommand
{
    protected function configure(): void
    {
        parent::configure();
        $this
            ->setHelp('This command allows assign or disassign a specific forum from a forum group.')
            ->addArgument('Forum', InputArgument::REQUIRED, 'Forum ID.')

            ->addOption('disassign', null, InputOption::VALUE_NONE, 'Disables the group.')
            ->addOption('localize', null, InputOption::VALUE_NONE, 'Add localized titles for the forum within this group interactively.')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Add overwritten title for the forum within this group.')
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if (empty($input->getArgument('Forum'))) {
            $assign = !$input->getOption('disassign');
            $forums = $this->entityManager->getRepository(Forum::class)->matching(
                Criteria::create()->where( $assign
                    ? Criteria::expr()->isNull( 'forumGroup' )
                    : Criteria::expr()->eq( 'forumGroup', $this->getForumGroup($input, $output) )
                )->andWhere( Criteria::expr()->isNull( 'town') )
            )->toArray();

            if (empty($forums))
                throw new \Exception('No forum available for this operation.');

            $input->setArgument('Forum', $helper->ask(
                $input, $output, new AssociativeChoiceQuestion('Select a forum group:',
                    array_combine(
                        array_map( fn(Forum $forum) => $forum->getId(), $forums ),
                        array_map( fn(Forum $forum) => $forum->getTitle(), $forums ),
                    ))
            ));
        }
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $assign = !$input->getOption('disassign');
        $forumGroup = $this->getForumGroup($input, $output);
        $forum = $this->getForum($input, $output);

        if ($forum->getTown())
            throw new \Exception('Cannot group town forums.');

        if ($forum->getForumGroup() && $forum->getForumGroup()->getId() !== $forumGroup->getId())
            throw new \Exception("Forum already assigned to group {$forum->getForumGroup()->getId()}.");

        if ($assign) {
            $forumGroup->addForum( $forum );
            if (!empty($input->getOption('name')))
                $forumGroup->setTitleOverride( $forum, $input->getOption('name') );

            if ($input->getOption('localize')) {
                $helper = $this->getHelper('question');
                foreach (['de', 'en', 'fr', 'es'] as $lang) {

                    $title = trim($helper->ask($input, $output, new Question("Please enter the title for <info>{$forum->getTitle()}</info> in <info>{$lang}</info> when appearing within grouo <info>{$forumGroup->getTitle()}</info> or leave blank to skip.\n > ")) ?? '');
                    if (empty($title)) $title = null;

                    $forumGroup->setTitleOverride( $forum, $title, $lang );
                }
            }
        } else {
            $forumGroup->clearTitleOverride( $forum, null );
            $forumGroup->removeForum( $forum );
        }

        $this->entityManager->persist($forumGroup);
        $this->entityManager->persist($forum);
        $this->entityManager->flush();

        $state = $assign ? 'Assigned' : 'Disassigned';
        $attr = $assign ? 'to' : 'from';
        $output->writeln("$state the forum <info>[{$forum->getId()}] {$forum->getTitle()}</info> $attr the group <info>[{$forumGroup->getId()}] {$forumGroup->getTitle()}</info>.");

        return 0;

    }
}
