<?php


namespace App\Command\Info;


use App\Command\LanguageCommand;
use App\Interfaces\NamedEntity;
use App\Structures\IdentifierSemantic;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:info:resolve',
    description: 'Resolves a given identifier.'
)]
class ResolveCommand extends LanguageCommand
{
    protected function configure(): void
    {
        $this
            ->setHelp('This command resolves a given identifier.')
            ->addArgument('Identifier', InputArgument::IS_ARRAY, 'The identifier')

            ->addOption('as',   null, InputOption::VALUE_REQUIRED, 'Expected class')
            ->addOption('hint', null, InputOption::VALUE_REQUIRED, 'Hint')
            ->addOption('short', null, InputOption::VALUE_NONE, 'Only short ouput')
            ->addOption('name-only', null, InputOption::VALUE_NONE, 'Print name only')
        ;

        parent::configure();
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $short = $input->getOption('short');
        $nameOnly = $input->getOption('name-only');

        $ids = $input->getArgument('Identifier');

        $as = $input->getOption('as') ?? null;
        if ($as !== null && strpos($as, 'App\Entity\\') === false) $as = 'App\Entity\\' . $as;

        $print = function (?IdentifierSemantic $result, array $matches, string $label) use ($output,$nameOnly,$short) {
            if (empty($matches)) return;

            if (!$nameOnly) $output->writeln("<info>$label</info>");

            $print_matches = $short ? [$matches[0]] : $matches;
            foreach ($print_matches as $match)
                if ($nameOnly) {
                    $m = $result->getMatchedObject($match);
                    if (is_a($m, NamedEntity::class))
                        $output->writeln("<info>{$m->getName()}</info>");
                    else $output->writeln("<info>{$m->getId()}</info>");
                } else {
                    $output->writeln("\t" . implode(', ', [
                                         $this->helper->printObject($result->getMatchedObject($match)),
                                         "[$match]",
                                         "<comment>matched by '{$result->getMatchedProperty($match)}'</comment>"
                                     ]));
                }


            if (!$short) $output->writeln("");
        };

        foreach ($ids as $id) {
            $result = $as ? $this->helper->resolve_as($id, $as, $input->getOption('hint') ?? null) : $this->helper->resolve($id);

            if ($short) {
                $list = [
                    ...$result->getMatches( IdentifierSemantic::LikelyMatch, true ),
                    ...$result->getMatches( IdentifierSemantic::PerfectMatch, true ),
                ];
                if (empty($list)) $output->writeln("<fg=red>-- NO RESULT --</>");
                else $print($result, $list, '');
            } else {
                $print($result, $result->getMatches( IdentifierSemantic::LikelyMatch, true ), 'Likely Exact Matches');
                $print($result, $result->getMatches( IdentifierSemantic::PerfectMatch, true ), 'Exact Matches');
                $print($result, $result->getMatches( IdentifierSemantic::StrongMatch, true ), 'Strong Matches');
                $print($result, $result->getMatches( IdentifierSemantic::WeakMatch, true ), 'Weak Matches');
                $print($result, $result->getMatches( IdentifierSemantic::GuessMatch, true ), 'Guessed Matches');
            }
        }


        return 0;
    }
}
