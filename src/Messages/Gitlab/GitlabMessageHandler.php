<?php

namespace App\Messages\Gitlab;

use App\Entity\Avatar;
use App\Entity\NotificationSubscription;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Enum\NotificationSubscriptionType;
use App\Messages\WebPush\WebPushMessage;
use App\Service\Actions\External\GetGitlabClientAction;
use App\Service\ConfMaster;
use ArrayHelpers\Arr;
use Doctrine\ORM\EntityManagerInterface;
use Gitlab\Client;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\UuidV4;
use WebPush\Message;
use WebPush\Notification;
use WebPush\WebPush;

readonly class GitlabMessageHandler
{
    public function __construct(
        private GetGitlabClientAction $gitlab,
        private ParameterBagInterface $params,
        private MessageBusInterface $bus
    ) {}

    private function makeTable(array $data): string {
        return "| Key | Value |\n|---|---|\n" .
            implode("\n", array_map( fn(string $key, string $value) => '| ' . str_replace( '|', ' ', $key ) . ' | ' . str_replace( '|', ' ', $value ) . ' |', array_keys( $data ), array_values( $data ) )) .
            "\n";
    }

    #[AsMessageHandler]
    public function createIssue( GitlabCreateIssueMessage $message ): void {
        $project = null;
        $client = ($this->gitlab)( $project );

        $filesystem = new Filesystem();
        $tempDir = "{$this->params->get('kernel.project_dir')}/var/tmp/issue_attachments/" . UuidV4::v4()->toRfc4122();
        $filesystem->mkdir( $tempDir );

        $paths = [];
        $accum = 0;
        foreach ( $message->attachments as $content ) {
            $filename = Arr::get( $content, 'file', null );
            $extension = Arr::get( $content, 'ext', '' );
            $content = Arr::get( $content, 'content', '' );

            if (!$filename || !$extension || !$content) continue;
            $decoded = base64_decode( $content );
            if (!$decoded || ($accum += strlen( $decoded )) > 3145728) continue;

            $storage_name = "$tempDir/" . UuidV4::v4()->toRfc4122() . $extension;
            $filesystem->dumpFile($storage_name, $decoded);
            $paths[$storage_name] = $filename;
        }

        try {
            $md = array_map( function( $file, $name ) use ($client, $project) {
                ['url' => $url] = $client->projects()->uploadFile($project, $file);
                return "![$name]($url)";
            }, array_keys( $paths ), array_values( $paths ) );

            $info_table = empty($message->passed_info) ? '' : ("\n## Context information:\n\n" . $this->makeTable( $message->trusted_info ) );
            $proxy_table = empty($message->passed_info) ? '' : ("\n## Information passed by client:\n\n" . $this->makeTable( $message->passed_info ) );
            $attachments = (empty($md) ? '' : ("\n### Attachments:\n" .  implode( "\n", $md )));

            ['iid' => $issue_id] = $client->issues()->create( $project, [
                'description'   => $message->description . $info_table . $proxy_table . $attachments,
                'issue_type'    => $message->issue_type,
                'confidential'  => $message->confidential,
                'title'         => $message->title,
            ] );
        } finally {
            $filesystem->remove( $tempDir );
        }

        $this->bus->dispatch( new SupportChannelPostMessage(
            user: $message->owner,
            issue_id: $issue_id,
            title:    mb_substr("[#$issue_id] $message->title", 0, 64),
            body:     $message->description,
            template: 'gitlab_new_issue',
        ) );
    }

    #[AsMessageHandler]
    public function createComment( GitlabCreateIssueCommentMessage $message ): void {
        $project = null;
        $client = ($this->gitlab)( $project );

        $client->issues()->addNote(
            $project,
            $message->issue_id,
            $message->description, [
                'internal' => $message->confidential,
            ] );
    }
}