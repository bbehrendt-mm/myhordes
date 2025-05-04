<?php

namespace App\Messages\Gitlab;

use App\Entity\Avatar;
use App\Entity\GlobalPrivateMessage;
use App\Entity\LogEntryTemplate;
use App\Entity\NotificationSubscription;
use App\Entity\OfficialGroup;
use App\Entity\OfficialGroupMessageLink;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserGroupAssociation;
use App\Enum\Configuration\MyHordesSetting;
use App\Enum\NotificationSubscriptionType;
use App\Enum\OfficialGroupSemantic;
use App\Messages\WebPush\WebPushMessage;
use App\Service\ConfMaster;
use App\Service\EventProxyService;
use App\Service\PermissionHandler;
use ArrayHelpers\Arr;
use Doctrine\ORM\EntityManagerInterface;
use Gitlab\Client;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\UuidV4;
use WebPush\Message;
use WebPush\Notification;
use WebPush\WebPush;

readonly class SupportChannelMessageHandler
{
    public function __construct(
        private ConfMaster $confMaster,
        private EntityManagerInterface $entityManager,
        private PermissionHandler $permissionHandler,
        private EventProxyService $proxy
    ) {}

    #[AsMessageHandler]
    public function postMessage( SupportChannelPostMessage $message ): void {
        /** @var ?User $user */
        $user = $message->user ? $this->entityManager->find( User::class, $message->user ) : null;

        $template = $this->entityManager->getRepository(LogEntryTemplate::class)->findOneBy(['name' => $message->template]);
        if (!$template) throw new UnrecoverableMessageHandlingException( 'Template not found');

        /** @var UserGroup $existing_group */
        $existing_group = $message->issue_id !== null ? $this->entityManager->getRepository(UserGroup::class)
            ->createQueryBuilder('s')
            ->where("JSON_EXTRACT(s.properties, '$.gitlab.issue_id') = :issue")->setParameter('issue', $message->issue_id)
            ->andWhere('s.type = :type')->setParameter('type', UserGroup::GroupMessageGroup)
            ->getQuery()->getOneOrNullResult() : null;

        $new = false;
        if (!$existing_group) {
            if (!$user) return;

            $new = true;
            $og = $this->entityManager->getRepository(OfficialGroup::class)
                ->findOneBy(['lang' => $user->getLanguage() ?? 'en', 'semantic' => OfficialGroupSemantic::Support]);
            if (!$og) throw new UnrecoverableMessageHandlingException( 'No official support group found');

            $existing_group = (new UserGroup())
                ->setType(UserGroup::GroupMessageGroup)
                ->setName($message->title)
                ->setRef1(1)
                ->setRef2(time())
                ->setRef3(time());

            if ($message->issue_id !== null) $existing_group->setProperty('gitlab.issue_id', $message->issue_id);

            $this->entityManager->persist( (new OfficialGroupMessageLink())->setMessageGroup( $existing_group )->setOfficialGroup( $og ) );
            foreach ($this->permissionHandler->usersInGroup( $og->getUsergroup()) as $group_member)
                if ($group_member->getId() !== $user->getId())
                    $this->permissionHandler->associate( $group_member, $existing_group, UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember )
                        ->setRef1( 0  )->setRef2( 0 );

            $this->permissionHandler->associate( $user, $existing_group, UserGroupAssociation::GroupAssociationTypePrivateMessageMember )
                ->setRef1( 0  )->setRef2( 0 );

        } else {
            $og = $this->entityManager->getRepository(OfficialGroupMessageLink::class)->findOneBy(['messageGroup' => $existing_group])?->getOfficialGroup();
            if (!$og) throw new UnrecoverableMessageHandlingException( 'Invalid OG link for existing group');

            $existing_group->setRef1($existing_group->getRef1() + 1)->setRef2(time());
            if ($user && !$this->permissionHandler->userInGroup( $user, $existing_group ))
                $this->permissionHandler->associate( $user, $existing_group, UserGroupAssociation::GroupAssociationTypePrivateMessageMember )
                    ->setRef1( 0  )->setRef2( 0 );
        }

        $base = $this->confMaster->getGlobalConf()->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'base' );
        $slug = $this->confMaster->getGlobalConf()->getSubKey( MyHordesSetting::IssueReportingGitlabToken, 'project-slug' );

        $issue_link = ($base && $slug) ? "$base/$slug/-/issues/{$message->issue_id}" : '#';

        $text =
            implode( '', array_map(fn(string $s) => "<div><img alt=\"\" src=\"$s\"/></div>",$message->images) ) .
            implode( '<hr />', array_map(fn(string $s) => "<div><a href=\"$s\">$s</a></div>",$message->attachments) );

        if (!empty($text)) $text = "<hr>$text";

        $post = (new GlobalPrivateMessage())
            ->setSenderGroup($og)->setTimestamp( new \DateTime() )->setReceiverGroup($existing_group)
            ->setText( $text )
            ->setTemplate( $template )->setData( [
                'body'  => strip_tags($message->body),
                'issue' => $message->issue_id,
                'link'  => $issue_link,
            ] );

        $this->entityManager->persist( $post );
        $this->entityManager->persist( $existing_group );
        $this->entityManager->flush();

        $this->proxy->globalPrivateMessageNewPostEvent( $post, null, $new );
    }
}