<?php

namespace App\Command\Debug;

use App\Service\RandomGenerator;
use App\Service\Translation\TranslationService;
use App\Translation\ICUTranslator;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[AsCommand(
    name: 'app:debug:translation-icu',
    description: 'Validates the ICU syntax for all translations in a given translation file and language.',
    aliases: ['app:debug:icu', 'app:debug:translation']
)]
class TranslationIcuTestCommand extends Command
{
    private const array ALLOWED_LANGUAGES = ['de', 'en', 'fr', 'es'];

    public function __construct(
        private readonly ParameterBagInterface $param,
        private readonly TranslatorInterface $translator,
        private readonly RandomGenerator $randomGenerator,
        private readonly TranslationService $translationService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command iterates over the translations in a given translation file and language,
and attempts to translate each item to verify that the ICU syntax is valid.

Examples:
  <info>php %command.full_name% en items</info>
  <info>php %command.full_name% de game</info>
  <info>php %command.full_name% fr buildings+intl-icu.fr.yml</info>
EOF
            )
            ->addArgument('language', InputArgument::REQUIRED, 'Language code (' . implode(', ', self::ALLOWED_LANGUAGES) . ')')
            ->addArgument('file', InputArgument::REQUIRED, 'Translation file name or domain, e.g. "items"')
            ->addOption('fail-fast', null, InputOption::VALUE_NONE, 'Stop execution on the first encountered error')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $lang = strtolower(trim((string)$input->getArgument('language')));
        if (!in_array($lang, self::ALLOWED_LANGUAGES, true)) {
            $io->error(sprintf(
                'Invalid language "%s". Allowed languages are: %s.',
                $input->getArgument('language'),
                implode(', ', self::ALLOWED_LANGUAGES)
            ));
            return Command::INVALID;
        }

        $fileInput = trim((string)$input->getArgument('file'));
        [$resolvedFilePath, $domain] = $this->resolveTranslationFile($fileInput, $lang);

        if ($resolvedFilePath === null || !is_file($resolvedFilePath)) {
            $io->error(sprintf(
                'Translation file could not be found for "%s" in language "%s".',
                $fileInput,
                $lang
            ));
            return Command::FAILURE;
        }

        $io->title('ICU Translation Syntax Checker');
        $io->text([
            sprintf('File:     <info>%s</info>', $resolvedFilePath),
            sprintf('Domain:   <info>%s</info>', $domain),
            sprintf('Language: <info>%s</info>', $lang),
        ]);

        try {
            $rawMessages = Yaml::parseFile($resolvedFilePath);
        } catch (ParseException $e) {
            $io->error(sprintf('Failed to parse YAML file "%s": %s', $resolvedFilePath, $e->getMessage()));
            return Command::FAILURE;
        }

        if (!is_array($rawMessages) || empty($rawMessages)) {
            $io->warning(sprintf('The translation file "%s" is empty or contains no valid translations.', $resolvedFilePath));
            return Command::SUCCESS;
        }

        $messages = $this->flattenMessages($rawMessages);
        $total = count($messages);

        $io->section(sprintf('Testing %d translations for valid ICU syntax...', $total));

        $undecoratedTranslator = $this->getUndecoratedTranslator($this->translator);
        $intlFormatter = new IntlFormatter();

        $errors = [];
        $failFast = (bool)$input->getOption('fail-fast');

        $progressBar = new ProgressBar($output, $total);
        $progressBar->start();

        $unknown_params = [];

        foreach ($messages as $id => $message) {
            $messageStr = (string)$message;
            $itemErrors = [];

            $proto = $params = [];
            preg_match_all('/\{\s*([-_\w]+)\s*,/', $messageStr, $detected_params);
            foreach ($detected_params[1] as $param) {
                $proto[$param] = match (true) {
                    $param === 'ref__icu' => 'on',
                    str_contains($param, 'count') ||
                    str_contains($param, 'defense') ||
                    str_starts_with($param, 'raw_') ||
                    in_array($param, [
                        'ap', 'sp', 'bp', 'cp', 'mp', 'pm','num', 'x', 'y', 'z', 'num', 'ration', 'points', 'deads',
                        'kills', 'water', 'def', 'rank', 'watchpoint', 'day', 'days', 'size'
                    ]) ||
                    str_ends_with( $param, '_num' ) ||
                    str_ends_with( $param, '_storage' )
                        => mt_rand(0, 100),
                    str_ends_with($param, '__gender') => $this->randomGenerator->pick(['male', 'female', 'none']),
                    ctype_upper( substr($param, 0, 1) ) => false,
                    default => null,
                };
            }

            foreach ($proto as $param => $value) {
                if ($value === null) {
                    $unknown_params[] = $param;
                    $params[$param] = $params["{$param}__copy"] = 'UNKNOWN';
                } elseif ($value !== false)
                    $params[$param] = $params["{$param}__copy"] = $value;
            }

            // 1. Direct ICU formatting validation using IntlFormatter / \MessageFormatter
            if ($messageStr !== '') {
                try {
                    $intlFormatter->formatIntl($messageStr, $lang, $params);
                } catch (Throwable $e) {
                    $itemErrors[] = $e->getMessage();
                }
            }

            // 2. Translation attempt through Symfony Translator
            try {
                $undecoratedTranslator->trans($id, $params, $domain, $lang);
            } catch (Throwable $e) {
                $itemErrors[] = $e->getMessage();
            }

            if (!empty($itemErrors)) {
                $errors[$id] = [
                    'key' => $id,
                    'message' => $messageStr,
                    'errors' => array_unique($itemErrors),
                    'params' => $params,
                ];

                if ($failFast) {
                    $progressBar->finish();
                    $io->newLine(2);
                    break;
                }
            }

            $progressBar->advance();
        }

        if (!$failFast || empty($errors)) {
            $progressBar->finish();
            $io->newLine(2);
        }

        $fileBasename = basename($resolvedFilePath);

        if (!empty($errors)) {
            $io->error(sprintf('Found %d invalid ICU translation(s) out of %d in "%s".', count($errors), $total, $fileBasename));

            $table = new Table($output);
            $table->setHeaders(['#', 'Key', 'Translation', 'Error', 'Params']);

            $index = 1;
            foreach ($errors as $errorInfo) {
                $shortKey = mb_strimwidth($errorInfo['key'], 0, 40, '...');
                $shortMessage = mb_strimwidth($errorInfo['message'], 0, 50, '...');
                $errorMsg = implode("\n", $errorInfo['errors']);

                $table->addRow([$index++, $shortKey, $shortMessage, "<error>$errorMsg</error>", json_encode($errorInfo['params'])]);
            }

            $table->render();

            foreach (array_unique($unknown_params) as $param)
                $io->warning(sprintf('Unknown ICU parameter "%s" found in "%s".', $param, $fileBasename));

            return Command::FAILURE;
        }

        $io->success(sprintf('All %d translations in "%s" have valid ICU syntax.', $total, $fileBasename));

        foreach (array_unique($unknown_params) as $param)
            $io->warning(sprintf('Unknown ICU parameter "%s" found in "%s".', $param, $fileBasename));

        return Command::SUCCESS;
    }

    /**
     * @return array{?string, string} [?filePath, domain]
     */
    private function resolveTranslationFile(string $inputName, string $lang): array
    {
        $cleanName = basename($inputName);
        $domain = preg_replace('/\.(ya?ml|xlf)$/i', '', $cleanName) ?? $cleanName;
        $domain = preg_replace('/\.([a-z]{2,3}(_[a-z]{2,3})?)$/i', '', $domain) ?? $domain;
        $domain = preg_replace('/\+intl-icu$/i', '', $domain) ?? $domain;

        $projectDir = (string)$this->param->get('kernel.project_dir');
        $translationsDir = $projectDir . '/translations';

        // Direct path check if user provided a valid existing path
        if (is_file($inputName)) {
            return [$inputName, $domain];
        }
        if (is_file($translationsDir . '/' . $inputName)) {
            return [$translationsDir . '/' . $inputName, $domain];
        }

        // Standard filename patterns in translations/
        $candidates = [
            "$translationsDir/$domain+intl-icu.$lang.yml",
            "$translationsDir/$domain+intl-icu.$lang.yaml",
            "$translationsDir/$domain.$lang.yml",
            "$translationsDir/$domain.$lang.yaml",
        ];

        // Check bundle paths if translation service is available
        if ($this->translationService !== null) {
            foreach ($this->translationService->getExistingDomains(null, $lang) as $existingDomain) {
                if ($existingDomain === $domain) {
                    $bundleSearch = [
                        "$translationsDir/$domain+intl-icu.$lang.yml",
                        "$translationsDir/$domain.$lang.yml",
                    ];
                    foreach ($bundleSearch as $p) {
                        if (is_file($p)) {
                            return [$p, $domain];
                        }
                    }
                }
            }
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return [$candidate, $domain];
            }
        }

        return [null, $domain];
    }

    /**
     * Flattens nested translation messages.
     *
     * @param array<string, mixed> $messages
     * @return array<string, mixed>
     */
    private function flattenMessages(array $messages, string $prefix = ''): array
    {
        $result = [];
        foreach ($messages as $key => $value) {
            $fullKey = $prefix === '' ? "$key" : "$prefix.$key";
            if (is_array($value)) {
                $result = array_merge($result, $this->flattenMessages($value, $fullKey));
            } else {
                $result[$fullKey] = $value;
            }
        }
        return $result;
    }

    /**
     * Extracts the undecorated Translator if wrapped by ICUTranslator.
     */
    private function getUndecoratedTranslator(TranslatorInterface $translator): TranslatorInterface
    {
        if ($translator instanceof ICUTranslator) {
            try {
                $property = new ReflectionProperty(ICUTranslator::class, '_decorated');
                $inner = $property->getValue($translator);
                if ($inner instanceof TranslatorInterface) {
                    return $this->getUndecoratedTranslator($inner);
                }
            } catch (ReflectionException) {
                // Fallback to original translator
            }
        }

        return $translator;
    }
}
