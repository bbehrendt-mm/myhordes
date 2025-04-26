<?php

namespace App\Controller\REST\Hook;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\AccountRestriction;
use App\Enum\Configuration\MyHordesSetting;
use App\Messages\Gitlab\GitlabCreateIssueMessage;
use App\Messages\Gitlab\SupportChannelPostMessage;
use App\Service\ConfMaster;
use App\Service\JSONRequestParser;
use App\Service\RateLimitingFactoryProvider;
use App\Service\UserHandler;
use ArrayHelpers\Arr;
use Gitlab\Client;
use Shivas\VersioningBundle\Service\VersionManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\UuidV4;

#[Route(path: '/rest/v1/hooks/gitlab', name: 'gitlab_hooks', methods: ['POST'], condition: "request.headers.get('Content-Type') === 'application/json' and request.headers.get('X-Gitlab-Token') === env('GITLAB_HOOK_TOKEN')")]
#[GateKeeperProfile('skip')]
class GitlabHookController extends CustomAbstractCoreController
{

    #[Route(condition: "request.headers.get('X-Gitlab-Event') === 'Issue Hook' or request.headers.get('X-Gitlab-Event') === 'Confidential Issue Hook'")]
    public function issue_hook(JSONRequestParser $parser, ConfMaster $conf, MessageBusInterface $bus): JsonResponse {

        $project = $parser->get('project.id', '?');
        $action = $parser->get('object_attributes.action', '?');

        if ($project !== $conf->getGlobalConf()->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'project-id' ))
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        $issue_id = $parser->get_int('object_attributes.iid');

        if (!in_array($action, ['reopen', 'close']) || $issue_id <= 0) return new JsonResponse([], Response::HTTP_ACCEPTED);

        $bus->dispatch( new SupportChannelPostMessage(
            user: null,
            issue_id: $issue_id,
            title:    mb_substr("[#$issue_id]", 0, 64),
            body:     null,
            template: "gitlab_{$action}_issue",
        ) );

        return new JsonResponse([], Response::HTTP_CREATED);
    }

    #[Route(condition: "request.headers.get('X-Gitlab-Event') === 'Note Hook' or request.headers.get('X-Gitlab-Event') === 'Confidential Note Hook'")]
    public function note_hook(JSONRequestParser $parser, ConfMaster $conf, MessageBusInterface $bus): JsonResponse {

        $project = $parser->get('project.id', '?');
        $action = $parser->get('object_attributes.action', '?');

        if ($project !== $conf->getGlobalConf()->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'project-id' ))
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        if ($parser->get( 'event_type') === 'confidential_note') return new JsonResponse([], Response::HTTP_ACCEPTED);

        $issue_id = $parser->get_int('issue.iid');
        $text = $parser->get('object_attributes.description');

        if (!in_array($action, ['create']) || $issue_id <= 0 || empty($text)) return new JsonResponse([], Response::HTTP_ACCEPTED);

        $bus->dispatch( new SupportChannelPostMessage(
            user: null,
            issue_id: $issue_id,
            title:    mb_substr("[#$issue_id]", 0, 64),
            body: $text,
            template: "gitlab_comment_issue",
        ) );

        return new JsonResponse([], Response::HTTP_CREATED);
    }

}
