<?php


namespace App\Command\Token;

use App\Entity\ExternalAccessTokens;
use App\Enum\Configuration\ExternalTokenType;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\Actions\External\GetGitlabClientAction;
use App\Service\ConfMaster;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Gitlab\HttpClient\Message\ResponseMediator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Zenstruck\ScheduleBundle\Attribute\AsScheduledTask;

#[AsCommand(
    name: 'app:token:refresh',
    description: 'Refreshes all API tokens for the given environment.'
)]
#[AsScheduledTask('19 0 * * *', description: 'Auto-refresh external API tokens')]
class RefreshTokenCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GetGitlabClientAction $gitlab,
        private readonly ParameterBagInterface $params,
        private readonly ConfMaster $confMaster,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_REQUIRED, 'Number of days before expiration', 14)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force refresh')
        ;
    }

    /**
     * @throws Exception|\Http\Client\Exception
     */
    protected function execute_gitlab(string $token, string $env, OutputInterface $output): array {
        $output->write("Creating a new token... ");
        $client = ($this->gitlab)( token: $token );

        $project_id = $this->confMaster->getGlobalConf()->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'project-id' );
        if (!$project_id) throw new Exception("Unable to get project ID.");

        //dd((new \DateTime('+320 days'))->format('Y-m-d'));

        $data = json_decode( $client->getHttpClient()->post( "api/v4/projects/{$project_id}/access_tokens/self/rotate", headers: [
            ResponseMediator::CONTENT_TYPE_HEADER => ResponseMediator::JSON_CONTENT_TYPE
        ], body: json_encode([
            'expires_at' => (new \DateTime('+1 year'))->format('Y-m-d'),
        ]) )->getBody()->getContents(), true );

        $id = Arr::get($data, 'id');
        $new_token = Arr::get($data, 'token');
        $revoked = Arr::get($data, 'revoked');
        $active = Arr::get($data, 'active');
        $scopes = Arr::get($data, 'scopes');
        $expires_ts = \DateTime::createFromFormat( 'Y-m-d H:i:s', Arr::get($data, 'expires_at') . ' 00:00:00', new \DateTimeZone('UTC') )->getTimestamp();
        $expires = new \DateTime();
        $expires->setTimestamp( $expires_ts );

        if (!$id || !$new_token) throw new Exception("Unable to get token.");
        if ($revoked || !$active) throw new Exception("Token is revoked or not active.");
        if (!in_array('api', $scopes)) throw new Exception("Token does not have the required scope 'api'.");

        $output->writeln("<fg=green>OK</>");

        $output->writeln( "A new token Token (<fg=green>$id</>) has been created to expire at <fg=yellow>{$expires->format('c')}</>." );
        return [$expires, $new_token];
    }

    /**
     * @throws \Http\Client\Exception
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $force = $input->getOption('force');
        $days = (int)$input->getOption('days');
        $env = $this->params->get('kernel.environment');

        /** @var Collection<ExternalAccessTokens> $tokens */
        $tokens = $this->entityManager->getRepository( ExternalAccessTokens::class )
            ->matching( Criteria::create()
                ->where( Criteria::expr()->eq( 'active', true ) )
                ->andWhere( Criteria::expr()->eq( 'env', $env ) )
            );

        $refreshed = [];
        foreach ($tokens as $token) {
            if (in_array( $token->getType()->value, $refreshed )) continue;

            $expires_days = (int)$token->getExpires()->diff( new \DateTime() )->format('%a');
            $output->writeln( "Token for <fg=yellow>{$token->getType()->value}</> expires in <fg=yellow>{$expires_days}</> days." );
            if ($expires_days > $days && !$force) continue;

            try {
                $output->writeln( "Refreshing <fg=red>{$token->getType()->value}</> token." );
                [$expires, $new_token] = match ($token->getType()) {
                    ExternalTokenType::GitlabApiToken => $this->execute_gitlab( $token->getToken(), $env, $output ),
                    default => throw new Exception("No handler for token type {$token->getType()->value}")
                };

                if (!$expires || $expires < new \DateTime()) throw new Exception("Invalid expiration date.");
            } catch (Exception $e) {
                $output->writeln( "Unable to refresh <fg=red>{$token->getType()->value}</> token: {$e->getMessage()}" );
                continue;
            }

            $output->writeln( "The generated token is valid for <fg=yellow>{$expires->diff( new \DateTime() )->format('%a')}</> days." );
            $output->write( "Adding <fg=yellow>{$token->getType()->value}</> token for environment <fg=yellow>{$env}</>... " );
            $this->entityManager->persist( (new ExternalAccessTokens())
                                               ->setToken( $new_token )
                                               ->setType( $token->getType() )
                                               ->setEnv( $env )
                                               ->setExpires( $expires )
                                               ->setActive( true )
            );
            $this->entityManager->getRepository( ExternalAccessTokens::class )
                ->matching( Criteria::create()
                    ->where( Criteria::expr()->eq( 'env', $env ) )
                    ->andWhere( Criteria::expr()->eq( 'type', $token->getType() ) )
                    ->andWhere( Criteria::expr()->eq( 'active', true ) )
                )->forAll(function (int $key, ExternalAccessTokens $token) {
                    $this->entityManager->persist( $token->setActive( false ) );
                });
            $this->entityManager->flush();
            $refreshed = $token->getType()->value;
            $output->writeln( "<fg=green>OK</>" );
        }

        return 0;
    }
}
