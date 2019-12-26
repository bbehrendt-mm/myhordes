<?php


namespace App\Command;


use App\Entity\Citizen;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class DebugCommand extends Command
{
    protected static $defaultName = 'app:debug';

    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->setDescription('Debug options.')
            ->setHelp('Debug options.')

            ->addOption('add-debug-users', null, InputOption::VALUE_NONE, 'Creates 80 validated users.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('add-debug-users')) {

            $command = $this->getApplication()->find('app:create-user');
            for ($i = 1; $i <= 80; $i++) {
                $user_name = 'user_' . str_pad($i, 3, '0', STR_PAD_LEFT);
                $nested_input = new ArrayInput([
                    'name' => $user_name,
                    'email' => $user_name . '@localhost',
                    'password' => $user_name,
                    '--validated' => true,
                ]);
                $command->run($nested_input, $output);
            }
            return 0;

        }
    }
}