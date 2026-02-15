<?php

namespace App\Command\User;


use App\Entity\User;
use App\Service\CommandHelper;
use App\Service\User\UserUnlockableService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user:migrate:herodays',
    description: 'Transfers old hero days to the new hero exp system'
)]
class UserTransferHeroDaysToXPCommand extends Command
{

    public function __construct(
        private readonly CommandHelper $helper,
        private readonly UserUnlockableService $unlockableService
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command allows you to update a users hero exp.')

            ->addOption('user', null,InputOption::VALUE_REQUIRED, 'Restrict to a specific user ID');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userID = $input->getOption('user');

            $this->helper->leChunk($output, User::class, 10, $userID === null ? [] : ['id' => $userID], true, false, function(User $u) {

                $changed = false;
                if ($u->getHeroDaysSpent() > 0) {
                    $this->unlockableService->setLegacyHeroDaysSpent($u, false, $u->getHeroDaysSpent(), true);
                    $u->setHeroDaysSpent(0);
                    $changed = true;
                }

                if ($u->getImportedHeroDaysSpent() > 0) {
                    $this->unlockableService->setLegacyHeroDaysSpent($u, true, $u->getImportedHeroDaysSpent(), true);
                    $u->setImportedHeroDaysSpent(0);
                    $changed = true;
                }

                return $changed;
            }, true);

        return 0;
    }
}
