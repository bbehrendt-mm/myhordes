<?php

namespace App\Service\Actions\External;

use App\Entity\ExternalAccessTokens;
use App\Enum\Configuration\ExternalTokenType;
use App\Enum\Configuration\MyHordesSetting;
use App\Service\ConfMaster;
use Doctrine\ORM\EntityManagerInterface;
use Gitlab\Client;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

readonly class GetGitlabClientAction
{
    public function __construct(private ConfMaster $conf, private EntityManagerInterface $em, private ParameterBagInterface $params) { }

    public function __invoke(?string &$project = null, ?string $token = null): Client
    {
        $conf = $this->conf->getGlobalConf();

        $token ??=
            $this->em->getRepository(ExternalAccessTokens::class)->findOneBy([
                'type' => ExternalTokenType::GitlabApiToken,
                'active' => true,
                'env' => $this->params->get('kernel.environment')
            ])->getToken() ??
            $conf->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'token' );

        $base = $conf->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'base' );
        $project = $conf->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'project-id' );

        // If Gitlab access is not configured, fail
        if (!$token || !$project || !$base)
            throw new UnrecoverableMessageHandlingException( 'Gitlab token, project id or base URL not set');

        $client = new Client();
        $client->setUrl( $base );
        $client->authenticate($token, Client::AUTH_HTTP_TOKEN);

        return $client;
    }
}