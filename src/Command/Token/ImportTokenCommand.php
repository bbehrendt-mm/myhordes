<?php


namespace App\Command\Token;

use App\Entity\ExternalAccessTokens;
use App\Enum\Configuration\ExternalTokenType;
use App\Service\Actions\External\GetGitlabClientAction;
use ArrayHelpers\Arr;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:token:import',
    description: 'Imports an external API token'
)]
class ImportTokenCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly GetGitlabClientAction $gitlab,
        private readonly ParameterBagInterface $params
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'The token type')
            ->addArgument('token', InputArgument::REQUIRED, 'The token')
        ;
    }

    /**
     * @throws Exception|\Http\Client\Exception
     */
    protected function execute_gitlab(string $token, OutputInterface $output): ?\DateTime {
        $output->write("Validating given token... ");
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

        $output->writeln("<fg=green>OK</>");

        $output->writeln( "Token <fg=green>$id</> is named <fg=yellow>$name</> and expires at <fg=yellow>{$expires->format('c')}</>." );
        return $expires;
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
        $token = $input->getArgument('token');

        $env = $this->params->get('kernel.environment');

        if ($this->entityManager->getRepository( ExternalAccessTokens::class )->findOneBy(
            ['env' => $env, 'type' => $type, 'token' => $token]
        )) throw new Exception("Token already exists.");

        $expires = match ($type) {
            ExternalTokenType::GitlabApiToken => $this->execute_gitlab( $token, $output ),
            default => throw new Exception("No handler for token type {$type->value}")
        };

        if (!$expires || $expires < new \DateTime()) throw new Exception("Unable to import token.");
        $output->writeln( "The given token is valid for <fg=yellow>{$expires->diff( new \DateTime() )->format('%a')}</> days." );

        $output->write( "Importing <fg=yellow>{$type->value}</> token for environment <fg=yellow>{$env}</>... " );
        $this->entityManager->persist( (new ExternalAccessTokens())
            ->setToken( $token )
            ->setType( $type )
            ->setEnv( $env )
            ->setExpires( $expires )
            ->setActive( true )
        );
        $this->entityManager->getRepository( ExternalAccessTokens::class )
            ->matching( Criteria::create()
                ->where( Criteria::expr()->eq( 'env', $env ) )
                ->andWhere( Criteria::expr()->eq( 'type', $type ) )
                ->andWhere( Criteria::expr()->eq( 'active', true ) )
            )->forAll(function (int $key, ExternalAccessTokens $token) {
                $this->entityManager->persist( $token->setActive( false ) );
            });
        $this->entityManager->flush();
        $output->writeln( "<fg=green>OK</>" );
        return 0;
    }
}
