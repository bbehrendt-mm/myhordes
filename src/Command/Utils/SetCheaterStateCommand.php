<?php


namespace App\Command\Utils;


use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\CommandHelper;
use App\Service\ConfMaster;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:utils:update-cheaters',
    description: 'Adds or removes the CHEATER flag for all users depending on the STAGING setting.'
)]
class SetCheaterStateCommand extends Command
{
    public function __construct(
        private readonly CommandHelper $commandHelper,
        private readonly ConfMaster $confMaster,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $enable = $this->confMaster->getGlobalConf()->get( MyHordesSetting::StagingSettingsEnabled );

        $this->commandHelper->leChunk($output, User::class, 200, [], true, false, function(User $user) use ($enable) {

            if ($user->hasRoleFlag( User::USER_ROLE_CHEATER ) !== $enable) {
                $user->toggleRoleFlag( User::USER_ROLE_CHEATER, $enable );
                return true;
            } else return false;
        }, true);

        return 0;
    }
}
