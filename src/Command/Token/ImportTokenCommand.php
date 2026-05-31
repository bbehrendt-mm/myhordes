<?php


namespace App\Command\Token;

use App\Entity\ExternalAccessTokens;
use App\Enum\Configuration\ExternalTokenPurpose;
use App\Enum\Configuration\ExternalTokenType;
use App\Service\Actions\External\GetGitlabClientAction;
use ArrayHelpers\Arr;
use DiscordWebhooks\Client;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption as InputOptionAlias;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:token:import',
    description: 'Imports an external API token'
)]
class ImportTokenCommand extends Command
{
    use TokenManagement;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GetGitlabClientAction $gitlab,
        private readonly ParameterBagInterface $params,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Packages $assets,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('token', InputArgument::REQUIRED, 'The token')
            ->addArgument('type', InputArgument::REQUIRED, 'The token type')

            ->addOption('name', null, InputOptionAlias::VALUE_REQUIRED, 'Give a name to the token.' )
            ->addOption('purpose', null, InputOptionAlias::VALUE_REQUIRED, 'Specify the token purpose.' )
        ;
    }

    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        $helper = new QuestionHelper();

        if (!$input->getArgument('token')) {
            $input->setArgument('token', $helper->ask( $input, $output, new Question(
                'Please enter the token: ',
            ) ) );
        }

        if (!$input->getArgument('type')) {
            $types = array_map(
                fn(ExternalTokenType $t) => $t->value,
                array_values( array_filter( ExternalTokenType::cases(), fn(ExternalTokenType $t) => $t->canImport() ) )
            );
            $input->setArgument('type', $helper->ask( $input, $output, new ChoiceQuestion(
                'Please select the token type:',
                $types
            ) ) );
        }

        $type = ExternalTokenType::tryFrom( $input->getArgument('type') );
        if ($type === null) throw new Exception("Invalid token type {$input->getArgument('type')}");

        $possible_purposes = array_map(
            fn(ExternalTokenPurpose $p) => $p->value,
            array_values( array_filter( ExternalTokenPurpose::cases(), fn(ExternalTokenPurpose $p) => $p->isValidForType( $type ) ) )
        );

        if (!$input->getOption('purpose') && !empty( $possible_purposes )) {
            if (count($possible_purposes) === 1) $input->setOption('purpose', $possible_purposes[0]);
            else $input->setOption('purpose', $helper->ask( $input, $output, new ChoiceQuestion(
                    'Please select the token purpose:',
                    $possible_purposes
                ) ) );
        }

        $purpose = ExternalTokenPurpose::tryFrom( $input->getOption('purpose') ?? 'no-purpose' );
        if ($purpose && !$purpose->isUnique() && !$input->getOption('name'))
            $input->setOption('name', $helper->ask( $input, $output, new Question(
                'Please enter a name for the token: ',
            ) ) );
    }

    /**
     * @throws Exception|\Http\Client\Exception
     */
    protected function execute_gitlab(string $token, OutputInterface $output): ?\DateTime {
        $client = ($this->gitlab)( token: $token );
        $data = json_decode( $client->getHttpClient()->get( 'api/v4/personal_access_tokens/self' )->getBody()->getContents(), true );

        $id = Arr::get($data, 'id');
        $name = Arr::get($data, 'name');
        $revoked = Arr::get($data, 'revoked');
        $active = Arr::get($data, 'active');
        $scopes = Arr::get($data, 'scopes');
        $expires_ts = \DateTime::createFromFormat( 'Y-m-d H:i:s', Arr::get($data, 'expires_at') . ' 00:00:00', new \DateTimeZone('UTC') )->getTimestamp();
        $expires = new \DateTime();
        $expires->setTimestamp( $expires_ts );

        if (!$id) throw new Exception("Unable to get token ID.");
        if ($revoked || !$active) throw new Exception("Token is revoked or not active.");
        if (!in_array('api', $scopes)) throw new Exception("Token does not have the required scope 'api'.");

        $output->writeln( "Token <fg=green>$id</> is named <fg=yellow>$name</> and expires at <fg=yellow>{$expires->format('c')}</>." );
        return $expires;
    }

    /**
     * @throws Exception
     */
    protected function execute_discord(string $token, OutputInterface $output, ExternalTokenPurpose $purpose, string $name): null {
        $base_url = $this->urlGenerator->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $avatar_url = $this->assets->getUrl('build/images/default/user-myhordes-cli.png');

        $discord = new Client($token);
        $discord
            ->username('MyHordes CLI')
            ->avatar( $base_url . $avatar_url)
            ->message(
                "Hello! This webhook has just been imported into a MyHordes instance. It is configured with the purpose `{$purpose->value}` and is named **{$name}**."
            )->send();

        $output->writeln( "Test message has been sent." );
        return null;
    }

    /**
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $type = ExternalTokenType::tryFrom( $input->getArgument('type') );
        if ($type === null) throw new Exception("Invalid token type {$input->getArgument('type')}");

        if (!$type->canImport()) throw new Exception("Token type {$type->value} cannot be imported.");

        $purpose = ExternalTokenPurpose::tryFrom( $input->getOption('purpose') ?? 'no-purpose' );
        if (!$type->isUnique() && !$purpose) throw new Exception("A purpose must be specified for a token of type {$input->getArgument('type')}.");
        if ($purpose && !$purpose->isValidForType( $type )) throw new Exception("The given purpose is not valid for the given token type {$type->value}.");

        $token = $input->getArgument('token');
        $name = $input->getOption('name') ?? 'default';

        $env = $this->params->get('kernel.environment');

        if (!$this->entityManager->getRepository( ExternalAccessTokens::class )->matching(
            self::generateOverlappingCriteria( $env, $type, $purpose, $name )
        )->isEmpty()) throw new Exception("Token already exists.");

        $output->write("Validating given token... ");
        $expires = match ($type) {
            ExternalTokenType::GitlabApiToken => $this->execute_gitlab( $token, $output ),
            ExternalTokenType::DiscordWebhook => $this->execute_discord( $token, $output, $purpose, $name ),
            default => throw new Exception("No handler for token type {$type->value}")
        };
        $output->writeln("<fg=green>OK</>");

        if ($type->canExpire()) {
            if (!$expires || $expires < new \DateTime()) throw new Exception("Unable to import token.");
            $output->writeln( "The given token is valid for <fg=yellow>{$expires->diff( new \DateTime() )->format('%a')}</> days." );
        }

        $output->write( "Importing <fg=yellow>{$type->value}</> token for environment <fg=yellow>{$env}</>... " );
        $this->entityManager->persist( new ExternalAccessTokens()
            ->setToken( $token )
            ->setType( $type )
            ->setPurpose( $purpose )
            ->setName( $name )
            ->setEnv( $env )
            ->setExpires( $expires )
            ->setActive( true )
        );

        $this->entityManager->flush();
        $output->writeln( "<fg=green>OK</>" );
        return 0;
    }
}
