<?php

namespace App\Controller\Messages;

use App\Annotations\GateKeeperProfile;
use App\Entity\AccountRestriction;
use App\Entity\AdminReport;
use App\Entity\Announcement;
use App\Entity\ForumModerationSnippet;
use App\Entity\ForumThreadSubscription;
use App\Entity\ForumUsagePermissions;
use App\Entity\GlobalPrivateMessage;
use App\Entity\OfficialGroup;
use App\Entity\OfficialGroupMessageLink;
use App\Entity\SocialRelation;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserGroupAssociation;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\LogTemplateHandler;
use App\Service\PermissionHandler;
use App\Service\UserHandler;
use App\Translation\T;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @GateKeeperProfile(allow_during_attack=true)
 * @method User getUser
 */
class MessageGlobalPMController extends MessageController
{
    /**
     * @Route("api/pm/ping", name="api_pm_ping")
     * @GateKeeperProfile("skip")
     * @param EntityManagerInterface $em
     * @param SessionInterface $s
     * @return Response
     */
    public function ping_check_new_message(EntityManagerInterface $em, SessionInterface $s): Response {
        $cache = $s->get('cache_ping');

        if ($cache && isset($cache['ts']) && isset($cache['r']) && (new DateTime('-1min')) < $cache['ts'] )
            return new AjaxResponse($cache['r']);

        $user = $this->getUser();
        if (!$user) return new AjaxResponse(['new' => 0, 'connected' => false, 'success' => true]);

        /** @var Collection|ForumThreadSubscription[] $subscriptions */
        $subscriptions = $em->getRepository(ForumThreadSubscription::class)->matching(
            (new Criteria())
                ->andWhere( Criteria::expr()->eq('user', $user) )
                ->andWhere( Criteria::expr()->gt('num', 0))
        );

        if (!empty($subscriptions)) {
            $forums = $this->perm->getForumsWithPermission($user);
            $subscriptions =  $subscriptions->filter(fn(ForumThreadSubscription $s) => in_array($s->getThread()->getForum(), $forums));
        }

        $response = ['new' =>
            count($subscriptions) +
            $em->getRepository(UserGroupAssociation::class)->countUnreadPMsByUser($user) +
            $em->getRepository(UserGroupAssociation::class)->countUnreadInactivePMsByUser($user) +
            $em->getRepository(GlobalPrivateMessage::class)->countUnreadDirectPMsByUser($user) +
            $em->getRepository(Announcement::class)->countUnreadByUser($user, $this->getUserLanguage()),
            'connected' => 60000, 'success' => true];

        $s->set('cache_ping', ['ts' => new DateTime(), 'r' => $response]);

        return new AjaxResponse($response);
    }

    /**
     * @Route("api/pm/spring/{domain}/{id<\d+>}", name="api_pm_spring")
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @param string $domain
     * @param int $id
     * @return Response
     */
    public function ping_fetch_new_messages(EntityManagerInterface $em, JSONRequestParser $parser, string $domain = '', int $id = 0): Response {

        $user = $this->getUser();
        if (!$user) return new AjaxResponse(['connected' => false, 'success' => true]);
        $rk = $parser->get_int('rk',0);

        if ($rk <= 0 || $rk >= time()) return new AjaxResponse(['connected' => 15000, 'success' => true]);

        if ($id <= 0 || !in_array($domain, ['d','g'])) $domain = '';

        $cutoff = new DateTime();
        $cutoff->setTimestamp( $rk );

        /** @var GlobalPrivateMessage[] $dm_cache */
        $dm_cache = $em->getRepository(GlobalPrivateMessage::class)->getUnreadDirectPMsByUser($user, $cutoff);

        $entries = [];
        $this->render_group_associations( $em->getRepository(UserGroupAssociation::class)->getUnreadPMsByUser($user, $cutoff), $entries );
        $this->render_announcements( $em->getRepository(Announcement::class)->getUnreadByUser($user, $this->getUserLanguage(), $cutoff), $entries );
        $this->render_directNotifications( $dm_cache );

        usort($entries, fn($a,$b) => $b['date'] <=> $a['date']);

        $index = $this->render( 'ajax/pm/bubbles.html.twig', ['raw_id' => $entries] )->getContent();
        $focus = '';

        switch ($domain) {
            case 'd':
                foreach ($dm_cache as $entry) $this->entity_manager->persist( $entry->setSeen(true) );
                try {
                    $this->entity_manager->flush();
                } catch (Exception $e) {}

                foreach ($dm_cache as $entry) $entry->setText( $this->html->prepareEmotes( $entry->getText() ) );
                $focus = $this->render( 'ajax/pm/bubbles.html.twig', ['raw_dm' => $dm_cache] )->getContent();

                break;
            case 'g':

                $group = $em->getRepository( UserGroup::class )->find($id);
                if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) break;

                /** @var UserGroupAssociation $group_association */
                $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' =>
                    [UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember],
                'association' => $group]);
                if (!$group_association) break;

                $messages = $em->getRepository(GlobalPrivateMessage::class)->findByGroup($group, 0, 0, 0, $cutoff);
                if (!$messages) break;

                try {
                    $this->entity_manager->persist( $group_association->setRef1( $group->getRef1() )->setRef2( $messages[0]->getId() ) );
                    $this->entity_manager->flush();
                } catch (\Exception $e) {}

                foreach ($messages as $message) $message->setText( $this->html->prepareEmotes( $message->getText() ) );
                $focus = $this->render( 'ajax/pm/bubbles.html.twig', ['raw_gp' => $messages, 'raw_gp_owner' => $group_association->getAssociationLevel() == UserGroupAssociation::GroupAssociationLevelFounder] )->getContent();

                break;
        }

        return new AjaxResponse(['success' => true, 'response_key' => (new DateTime('now'))->getTimestamp(), 'payload' => [
            'connected' => 60000,
            'index' => $index,
            'focus' => $focus,
        ]]);
    }

    /**
     * @Route("jx/pm", name="pm_proxy_view")
     * @return Response
     */
    public function pm_proxy_view(): Response {
        return $this->render( 'ajax/pm/proxy.html.twig', $this->addDefaultTwigArgs());
    }


    /**
     * @Route("jx/pm/short/{s}", name="pm_proxy_view_short")
     * @param string|null $s
     * @return Response
     */
    public function pm_proxy_view_short(?string $s = null): Response {
        return $this->render( 'ajax/pm/proxy.html.twig', $this->addDefaultTwigArgs(null,['command' => $s]));
    }

    /**
     * @Route("jx/pm/view", name="pm_view")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function pm_view(JSONRequestParser $parser): Response {
        $target = Request::createFromGlobals()->headers->get('X-Render-Target', '');

        if ($target === 'post-office-content') {
            return $this->render( 'ajax/pm/view.html.twig', ['rk' => (new DateTime('now'))->getTimestamp(), 'command' => $parser->get('command')]);
        }

        return $this->pm_proxy_view();
    }

    /**
     * @param UserGroupAssociation[] $group_associations
     * @param array|null $entries
     */
    private function render_group_associations(array $group_associations, ?array &$entries = null): void {
        if ($entries === null) $entries = [];

        foreach ($group_associations as $association) {

            $last_post_date = new DateTime();
            $last_post_date->setTimestamp($association->getAssociation()->getRef2());

            $official_meta = $this->entity_manager->getRepository(OfficialGroupMessageLink::class)->findOneBy(['messageGroup' => $association->getAssociation()]);

            $owner_assoc = $this->entity_manager->getRepository(UserGroupAssociation::class)->findOneBy([
                'association' => $association->getAssociation(),
                'associationLevel' => $official_meta ? UserGroupAssociation::GroupAssociationLevelDefault : UserGroupAssociation::GroupAssociationLevelFounder,
                'associationType' => $official_meta ? [UserGroupAssociation::GroupAssociationTypePrivateMessageMember,UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive] : UserGroupAssociation::GroupAssociationTypePrivateMessageMember,
            ]);

            $read_only = $association->getAssociationType() === UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive;

            $entries[] = [
                'obj'    => $association,
                'date'   => $last_post_date,
                'system' => false,
                'official' => $official_meta ? $official_meta->getOfficialGroup() : null,
                'title'  => $association->getAssociation()->getName(),
                'closed' => $association->getAssociationType() === UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive,
                'count'  => $read_only ? $association->getRef3() : $association->getAssociation()->getRef1(),
                'unread' => $read_only ? ($association->getRef3() - $association->getRef1()) : ($association->getAssociation()->getRef1() - $association->getRef1()),
                'owner'  => ($owner_assoc && $owner_assoc->getUser()) ? $owner_assoc->getUser() : null,
                'users'  => array_map(fn(UserGroupAssociation $a) => $a->getUser(), $this->entity_manager->getRepository(UserGroupAssociation::class)->findBy( [
                    'association' => $association->getAssociation(),
                    'associationType' =>  UserGroupAssociation::GroupAssociationTypePrivateMessageMember]
                ))
            ];
        }
    }

    /**
     * @param Announcement[] $announcements
     * @param array|null $entries
     */
    private function render_announcements( array $announcements, ?array &$entries = null ): void {
        if ($entries === null) $entries = [];

        foreach ($announcements as $announcement) {

            $entries[] = [
                'obj'    => $announcement,
                'date'   => $announcement->getTimestamp(),
                'system' => false,
                'official' => null,
                'title'  => $announcement->getTitle(),
                'closed' => false,
                'count'  => 1,
                'unread' => ($announcement->getTimestamp() < new DateTime('-60days') || $announcement->getReadBy()->contains( $this->getUser() )) ? 0 : 1,
                'owner'  => $announcement->getSender(),
                'users'  => [$this->getUser(), $announcement->getSender()]
            ];

        }
    }

    /**
     * @param array|null $entries
     * @param int[] $skip
     */
    private function render_forumNotifications( ?array &$entries = null, array $skip = [] ): void {
        if ($entries === null) $entries = [];

        /** @var Collection|ForumThreadSubscription[] $subscriptions */
        $subscriptions = $this->entity_manager->getRepository(ForumThreadSubscription::class)->matching(
            (new Criteria())
                ->andWhere( Criteria::expr()->eq('user', $this->getUser()) )
                ->andWhere( Criteria::expr()->gt('num', 0))
        );

        if (!empty($subscriptions)) {
            $forums = $this->perm->getForumsWithPermission($this->getUser());
            $subscriptions =  $subscriptions->filter(fn(ForumThreadSubscription $s) => !in_array($s->getThread()->getId(), $skip) && in_array($s->getThread()->getForum(), $forums));
        }

        foreach ($subscriptions as $subscription) {
            $entries[] = [
                'obj'    => $subscription->getThread(),
                'date'   => new DateTime(),
                'system' => false,
                'official' => null,
                'title'  => $subscription->getThread()->getTranslatable()
                        ? $this->translator->trans($subscription->getThread()->getTitle(), [], 'game') : $subscription->getThread()->getTitle()
                ,
                'closed' => false,
                'count'  => $subscription->getNum(),
                'unread' => $subscription->getNum(),
                'owner'  => $this->getUser(),
                'users'  => [$this->getUser()]
            ];
        }
    }

    /**
     * @param GlobalPrivateMessage[] $pms
     * @param array|null $entries
     */
    private function render_directNotifications( array $pms, ?array &$entries = null ): void {
        if ($entries === null) $entries = [];

        $latest_pm = empty($pms) ? null : $pms[0];

        if ($latest_pm) {
            $crow = $this->entity_manager->getRepository(User::class)->find(66);
            $entries[] = [
                'obj'    => $latest_pm,
                'date'   => $latest_pm->getTimestamp(),
                'system' => true,
                'official' => null,
                'title'  => $this->translator->trans('Nachrichten des Raben', [], 'global'),
                'closed' => false,
                'count'  => $this->entity_manager->getRepository(GlobalPrivateMessage::class)->count(['receiverUser' => $this->getUser(), 'receiverGroup' => null]),
                'unread' => $this->entity_manager->getRepository(GlobalPrivateMessage::class)->countUnreadDirectPMsByUser($this->getUser()),
                'owner'  => $crow,
                'users'  => [$this->getUser(),$crow]
            ];

        }
    }

    /**
     * @Route("jx/pm/list", name="pm_list")
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $p
     * @return Response
     */
    public function pm_load_list(EntityManagerInterface $em, JSONRequestParser $p): Response {
        $entries = [];

        $skip = $p->get_array('skip');
        $num = max(5,min(30,$p->get_int('num', 30)));

        $this->render_group_associations( $em->getRepository(UserGroupAssociation::class)->findByUserAssociation($this->getUser(), [
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive, UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember
        ], $skip['g'] ?? [], $num+1), $entries );

        $this->render_announcements( $em->getRepository(Announcement::class)->findByLang($this->getUserLanguage(),
        $skip['a'] ?? [], $num+1), $entries );

        if (empty($skip['d'])) $this->render_directNotifications($this->entity_manager->getRepository(GlobalPrivateMessage::class)->getDirectPMsByUser($this->getUser(), 0, 1), $entries);
        $this->render_forumNotifications($entries, $skip['f'] ?? [] );

        usort($entries, fn($a,$b) => $b['date'] <=> $a['date']);

        return $this->render( 'ajax/pm/list.html.twig', $this->addDefaultTwigArgs(null, [
            'more' => count($entries) > $num,
            'entries' => array_slice($entries,0,$num)
        ] ));
    }

    /**
     * @Route("api/pm/conversation/group/{gid<\d+>}/user/{uid<\d+>}/kick", name="pm_conv_group_user_kick")
     * @param int $gid
     * @param int $uid
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_conversation_group_user_kick(int $gid, int $uid, EntityManagerInterface $em, PermissionHandler $perm): Response {

        if ($uid === $this->getUser()->getId()) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $group = $em->getRepository( UserGroup::class )->find($gid);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' =>
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, 'associationLevel' => UserGroupAssociation::GroupAssociationLevelFounder
        , 'association' => $group]);
        if (!$group_association) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $other_user = $em->getRepository(User::class)->find($uid);
        if (!$other_user) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $other_association */
        $other_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $other_user, 'associationType' =>
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, 'association' => $group]);
        if (!$other_association) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($other_association->getRef1() === null)
            $perm->disassociate( $other_user, $group );
        else {
            $messages = $em->getRepository(GlobalPrivateMessage::class)->findByGroup($group, 0, 1);

            $em->persist($other_association
                             ->setAssociationType(UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive)
                             ->setRef3( $group->getRef1() )->setRef4( empty($messages) ? 1 : $messages[0]->getId() )
            );
        }

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/conversation/group/{gid<\d+>}/user/{uid<\d+>}/restore", name="pm_conv_group_user_restore")
     * @param int $gid
     * @param int $uid
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_conversation_group_user_restore(int $gid, int $uid, EntityManagerInterface $em): Response {

        $group = $em->getRepository( UserGroup::class )->find($gid);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' =>
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, 'associationLevel' => UserGroupAssociation::GroupAssociationLevelFounder
                                                                                            , 'association' => $group]);
        if (!$group_association) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $other_user = $em->getRepository(User::class)->find($uid);
        if (!$other_user) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $other_association */
        $other_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $other_user, 'associationType' =>
            UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive, 'association' => $group]);
        if (!$other_association) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $em->persist($other_association
             ->setAssociationType(UserGroupAssociation::GroupAssociationTypePrivateMessageMember)
             ->setRef3( null )->setRef4( null )
        );

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/conversation/group/{gid<\d+>}/user/{uid<\d+>}/add", name="pm_conv_group_user_add")
     * @param int $gid
     * @param int $uid
     * @param EntityManagerInterface $em
     * @param UserHandler $userHandler
     * @param PermissionHandler $perm
     * @return Response
     */
    public function pm_conversation_group_user_add(int $gid, int $uid, EntityManagerInterface $em, UserHandler $userHandler, PermissionHandler $perm): Response {

        if ($userHandler->isRestricted($this->getUser(), AccountRestriction::RestrictionGlobalCommunication))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $group = $em->getRepository( UserGroup::class )->find($gid);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' =>
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, 'associationLevel' => UserGroupAssociation::GroupAssociationLevelFounder
                                                                                            , 'association' => $group]);
        if (!$group_association) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $member_count = $em->getRepository(UserGroupAssociation::class)->count(['association' => $group]);
        if ($member_count >= 100) return AjaxResponse::error( self::ErrorGPMMemberLimitHit);

        $other_user = $em->getRepository(User::class)->find($uid);
        if (!$other_user) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        if ($userHandler->hasRole($other_user, 'ROLE_DUMMY')) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($userHandler->checkRelation($other_user,$this->getUser(),SocialRelation::SocialRelationTypeBlock))
            return AjaxResponse::error(ErrorHelper::ErrorBlockedByUser);

        /** @var UserGroupAssociation $other_association */
        $other_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $other_user,'association' => $group]);
        if ($other_association) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $perm->associate($other_user, $group, UserGroupAssociation::GroupAssociationTypePrivateMessageMember);

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }


    /**
     * @Route("jx/pm/conversation/group/{id<\d+>}/users", name="pm_conv_group_users")
     * @param int $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_user_list(int $id, EntityManagerInterface $em): Response {
        $group = $em->getRepository( UserGroup::class )->find($id);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return new Response('not found');

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(),
            'associationType' => [UserGroupAssociation::GroupAssociationTypePrivateMessageMember,UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember], 'association' => $group]);
        if (!$group_association) return new Response('not found');

        $all_associations = $em->getRepository(UserGroupAssociation::class)->findBy(['associationType' => [
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive
        ], 'association' => $group]);

        if ($og_link = $this->entity_manager->getRepository(OfficialGroupMessageLink::class)->findOneBy(['messageGroup' => $group]))
            $og_link = $og_link->getOfficialGroup();

        $oa = array_filter( array_map( fn(UserGroupAssociation $a): ?User => $a->getAssociationLevel() === UserGroupAssociation::GroupAssociationLevelFounder ? $a->getUser() : null, $all_associations ) );
        $oa = count($oa) === 1 ? $oa[0] : null;

        return $this->render( 'ajax/pm/user_list.html.twig', $this->addDefaultTwigArgs(null, [
            'gid' => $id,
            'owner' => $group_association->getAssociationLevel() === UserGroupAssociation::GroupAssociationLevelFounder,
            'owning_user' => $oa,
            'can_add' => count($all_associations) < 100,
            'active'   => array_filter( array_map( fn(UserGroupAssociation $a): ?User => $a->getAssociationType() === UserGroupAssociation::GroupAssociationTypePrivateMessageMember ? $a->getUser() : null, $all_associations ) ),
            'inactive' => array_filter( array_map( fn(UserGroupAssociation $a): ?User => $a->getAssociationType() !== UserGroupAssociation::GroupAssociationTypePrivateMessageMember ? $a->getUser() : null, $all_associations ) ),
            'og' => $og_link
        ] ));
    }


    /**
     * @Route("jx/pm/conversation/group/{id<\d+>}", name="pm_conv_group")
     * @param int $id
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $p
     * @param SessionInterface $s
     * @return Response
     */
    public function pm_conversation_group(int $id, EntityManagerInterface $em, JSONRequestParser $p, SessionInterface $s): Response {

        $group = $em->getRepository( UserGroup::class )->find($id);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return new Response('not found');

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' => [
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive, UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember
        ], 'association' => $group]);
        if (!$group_association) return new Response('not found');

        $read_only = $group_association->getAssociationType() === UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive;

        $num = max(5,min($p->get('num', 5),30));
        $last_id = $p->get('last', 0);
        $nb_id = $read_only ? $group_association->getRef4() : 0;

        $messages = $em->getRepository(GlobalPrivateMessage::class)->findByGroup($group, $last_id, $num + 1, $nb_id);
        if (!$messages) return new Response('no messages');

        $last = $group_association->getRef2();

        try {
            $s->remove('cache_ping');
            $this->entity_manager->persist( $group_association->setRef1( $read_only ? $group_association->getRef3() : $group->getRef1() )->setRef2( $messages[0]->getId() ) );
            $this->entity_manager->flush();
        } catch (\Exception $e) {}

        foreach ($messages as $message) $message->setText( $this->html->prepareEmotes( $message->getText() ) );

        /** @var GlobalPrivateMessage[] $sliced */
        $sliced = array_slice($messages, 0, $num);

        $pinned =  $em->getRepository(GlobalPrivateMessage::class)->findOneBy(['receiverGroup' => $group, 'pinned' => true]);

        return $this->render( 'ajax/pm/conversation_group.html.twig', $this->addDefaultTwigArgs(null, [
            'gid' => $id,
            'owner' => $group_association->getAssociationLevel() === UserGroupAssociation::GroupAssociationLevelFounder,
            'last' => $last,
            'more' => count($messages) > $num,
            'pinned' => $pinned,
            'messages' => $sliced,
            'last_message' => $sliced[array_key_last($sliced)]->getId()
        ] ));
    }

    /**
     * @Route("jx/pm/conversation/dm", name="pm_dm")
     * @param EntityManagerInterface $em
     * @param LogTemplateHandler $th
     * @param JSONRequestParser $p
     * @param SessionInterface $s
     * @return Response
     */
    public function pm_direct_messages(EntityManagerInterface $em, LogTemplateHandler $th, JSONRequestParser $p, SessionInterface $s): Response {

        $num = max(5,min($p->get('num', 5),30));
        $last_id = $p->get('last', 0);

        /** @var GlobalPrivateMessage[] $messages */
        $messages = $em->getRepository(GlobalPrivateMessage::class)->getDirectPMsByUser($this->getUser(), $last_id, $num + 1);
        if (!$messages) return new Response('no messages');

        $sliced = array_slice($messages, 0, $num);

        $update = false;
        $seen_map = [];
        foreach ($sliced as $message)
            if (!$message->getSeen()) {
                $seen_map[] = $message->getId();
                $message->setSeen($update = true);
                $em->persist($message);
            }

        if ($update) try {
            $s->remove('cache_ping');
            $this->entity_manager->flush();
        } catch (\Exception $e) {}

        foreach ($sliced as $message) {
            $tx = '';
            if ($message->getTemplate() === null && $message->getText()) $tx .= $this->html->prepareEmotes($message->getText());

            if ($message->getTemplate())
                try {
                    $tx .= $this->translator->trans(
                        $message->getTemplate()->getText(), $th->parseTransParams($message->getTemplate()->getVariableTypes(), $message->getData()), 'game'
                    );
                }
                catch (\Exception $e) { $tx .= '_TEMPLATE_ERROR_'; }

            $message->setText($tx);
            if (in_array($message->getId(),$seen_map)) $message->setSeen(false);
        }

        return $this->render( 'ajax/pm/dm.html.twig', $this->addDefaultTwigArgs(null, [
            'last' => $sliced[array_key_last($sliced)]->getId(),
            'more' => count($messages) > $num,
            'messages' => $sliced,
            'last_message' => $sliced[array_key_last($sliced)]->getId()
        ] ));
    }


    /**
     * @Route("jx/pm/conversation/announce/{id<\d+>}", name="pm_announce")
     * @param int $id
     * @param EntityManagerInterface $em
     * @param SessionInterface $s
     * @return Response
     */
    public function pm_announcement(int $id, EntityManagerInterface $em, SessionInterface $s): Response {
        $announce = $em->getRepository( Announcement::class )->find($id);
        if (!$announce || $announce->getLang() != $this->getUserLanguage()) return new Response('not found');

        $new = !$announce->getReadBy()->contains($this->getUser());
        if ($new)
            try {
                $s->remove('cache_ping');
                $announce->getReadBy()->add($this->getUser());
                $this->entity_manager->persist( $announce );
                $this->entity_manager->flush();
            } catch (\Exception $e) {}

        $announce->setText( $this->html->prepareEmotes( $announce->getText() ) );

        return $this->render( 'ajax/pm/announcement.html.twig', $this->addDefaultTwigArgs(null, [
            'announcements' => [$announce],
            'new' => $new,
            'more' => false
        ] ));
    }

    /**
     * @Route("jx/pm/conversation/announce/all", name="pm_announce_all")
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @param SessionInterface $s
     * @return Response
     */
    public function pm_announcement_all(EntityManagerInterface $em, JSONRequestParser $parser, SessionInterface $s): Response {
        $skip = $parser->get_array('skip');
        $num = max(1,min(10,$parser->get_int('num', 5)));

        $announces = $em->getRepository( Announcement::class )->findByLang($this->getUserLanguage(), $skip, $num + 1 );

        $sliced = array_slice($announces, 0, $num);

        $new = false;
        foreach ($sliced as $announce)
            if (!$announce->getReadBy()->contains($this->getUser())) {
                $new = true;
                $announce->getReadBy()->add($this->getUser());
                $this->entity_manager->persist($announce);
            }

        if ($new)
            try {
                $s->remove('cache_ping');
                $this->entity_manager->flush();
            } catch (\Exception $e) {}

        foreach ($sliced as $announce)
            $announce->setText( $this->html->prepareEmotes( $announce->getText() ) );

        return $this->render( 'ajax/pm/announcement.html.twig', $this->addDefaultTwigArgs(null, [
            'announcements' => $announces,
            'new' => $new,
            'more' => count($announces) > $num
        ] ));
    }

    /**
     * @Route("api/pm/conversation/group/{id<\d+>}/delete", name="pm_delete_conv_group")
     * @param int $id
     * @param EntityManagerInterface $em
     * @param PermissionHandler $perm
     * @return Response
     */
    public function pm_delete_conversation_group(int $id, EntityManagerInterface $em, PermissionHandler $perm): Response {

        $group = $em->getRepository( UserGroup::class )->find($id);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($em->getRepository(OfficialGroupMessageLink::class)->findOneBy(['messageGroup' => $group]))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' => [
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive
        ], 'association' => $group]);
        if (!$group_association) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $num_of_assocs = $em->getRepository(UserGroupAssociation::class)->count(['association' => $group]);

        $perm->disassociate( $this->getUser(), $group );
        if ($num_of_assocs < 2) $this->entity_manager->remove( $group );

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/conversation/group/unread/{id<\d+>}", name="pm_unread_conv_group")
     * @param int $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_unread_conversation_group(int $id, EntityManagerInterface $em): Response {

        $group = $em->getRepository( UserGroup::class )->find($id);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' => [
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive, UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember
        ], 'association' => $group]);
        if (!$group_association) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $this->entity_manager->persist( $group_association->setRef1(0)->setRef2(null) );

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/conversation/group/block/{id<\d+>}", name="pm_block_conv_group")
     * @param int $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_block_conversation_group(int $id, EntityManagerInterface $em): Response {

        $group = $em->getRepository( UserGroup::class )->find($id);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(), 'associationType' => [
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive
        ], 'association' => $group]);
        if (!$group_association || $group_association->getAssociationLevel() === UserGroupAssociation::GroupAssociationLevelFounder)
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $owner_assoc = $em->getRepository(UserGroupAssociation::class)->findOneBy(['associationType' => [
            UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive
        ], 'association' => $group, 'associationLevel' => UserGroupAssociation::GroupAssociationLevelFounder]);

        if (!$owner_assoc || $owner_assoc->getUser() === $this->getUser() || $this->userHandler->hasRole( $owner_assoc->getUser(), 'ROLE_CROW' ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($this->userHandler->checkRelation($this->getUser(), $owner_assoc->getUser(), SocialRelation::SocialRelationTypeBlock))
            return AjaxResponse::success();

        $this->entity_manager->persist( (new SocialRelation())->setOwner($this->getUser())->setRelated($owner_assoc->getUser())->setType( SocialRelation::SocialRelationTypeBlock ) );

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/conversation/dm/delete", name="pm_delete_dm")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_delete_dm(EntityManagerInterface $em): Response {

        foreach ($em->getRepository(GlobalPrivateMessage::class)->getDirectPMsByUser( $this->getUser() ) as $dm)
            $em->remove($dm);

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/conversation/dm/unread", name="pm_unread_dm")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_unread_dm(EntityManagerInterface $em): Response {
        $dm = $em->getRepository(GlobalPrivateMessage::class)->getDirectPMsByUser( $this->getUser(), 0, 1 );

        if ($dm) {
            $em->persist( $dm[0]->setSeen(false) );
            try {
                $em->flush();
            } catch (\Exception $e) {
                return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
            }
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/conversation/announce/unread/{id<\d+>}", name="pm_unread_announce")
     * @param int $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function pm_unread_announcement(int $id, EntityManagerInterface $em): Response {

        $announce = $em->getRepository( Announcement::class )->find($id);
        if (!$announce || $announce->getLang() !== $this->getUserLanguage())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if ($announce->getReadBy()->contains($this->getUser())) {
            $announce->getReadBy()->removeElement($this->getUser());
            $this->entity_manager->persist( $announce );

            try {
                $em->flush();
            } catch (\Exception $e) {
                return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
            }
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/pm/og_resolve", name="pm_og_resolve")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function editor_og_group_preview(JSONRequestParser $parser) {
        if (!$parser->has('og')) return new Response("");
        $group = $this->entity_manager->getRepository(OfficialGroup::class)->find($parser->get_int('og'));
        if (!$group) return new Response("");

        return $this->render( 'ajax/pm/og.html.twig', ['group' => $group]);
    }

    /**
     * @Route("jx/pm/create-editor", name="pm_thread_editor_controller")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function editor_pm_thread_api(EntityManagerInterface $em): Response {
        if ($this->userHandler->isRestricted($this->getUser(), AccountRestriction::RestrictionGlobalCommunication))
            return new Response("");

        if ($em->getRepository(UserGroupAssociation::class)->countRecentRecipients($this->getUser()) > 100)
            return $this->render( 'ajax/pm/non-editor.html.twig');

        return $this->render( 'ajax/forum/editor.html.twig', [
            'fid' => null,
            'tid' => null,
            'pid' => null,

            'permission' => $this->getPermissionObject( ForumUsagePermissions::PermissionCreateThread ),
            'snippets' => $this->isGranted('ROLE_CROW') ? $this->entity_manager->getRepository(ForumModerationSnippet::class)->findAll() : [],

            'emotes' => $this->getEmotesByUser($this->getUser(),true),
            'username' => $this->getUser()->getName(),
            'forum' => false,
            'town_controls' => null,

            'type' => 'global-pm',
            'target_url' => 'pm_new_thread_controller',
        ] );
    }

    /**
     * @Route("jx/pm/create-og-editor", name="pm_og_thread_editor_controller")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function editor_pm_og_thread_api(EntityManagerInterface $em): Response {

        return $this->render( 'ajax/forum/editor.html.twig', [
            'fid' => null,
            'tid' => null,
            'pid' => null,

            'permission' => $this->getPermissionObject( ForumUsagePermissions::PermissionCreateThread ),
            'snippets' => $this->isGranted('ROLE_CROW') ? $this->entity_manager->getRepository(ForumModerationSnippet::class)->findAll() : [],

            'emotes' => $this->getEmotesByUser($this->getUser(),true),
            'username' => $this->getUser()->getName(),
            'forum' => false,
            'town_controls' => null,

            'type' => 'global-og-pm',
            'target_url' => 'pm_new_og_thread_controller',
        ] );
    }

    /**
     * @Route("jx/pm/answer-editor/{id<\d+>}", name="pm_post_editor_controller")
     * @param int $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function editor_pm_post_api(int $id, EntityManagerInterface $em): Response {
        if ($this->userHandler->isRestricted($this->getUser(), AccountRestriction::RestrictionGlobalCommunication))
            return new Response("");

        return $this->render( 'ajax/forum/editor.html.twig', [
            'fid' => null,
            'tid' => null,
            'pid' => null,

            'permission' => $this->getPermissionObject( ForumUsagePermissions::PermissionCreatePost ),
            'snippets' => $this->isGranted('ROLE_CROW') ? $this->entity_manager->getRepository(ForumModerationSnippet::class)->findAll() : [],

            'emotes' => $this->getEmotesByUser($this->getUser(),true),
            'username' => $this->getUser()->getName(),
            'forum' => false,
            'town_controls' => null,

            'type' => 'global-pm',
            'target_url'  => 'pm_new_post_controller',
            'target_data' => ['id' => $id],
        ] );
    }

    /**
     * @Route("api/pm/post", name="pm_new_thread_controller")
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param UserHandler $userHandler
     * @param PermissionHandler $perm
     * @return Response
     */
    public function new_thread_api(JSONRequestParser $parser, EntityManagerInterface $em, UserHandler $userHandler, PermissionHandler $perm): Response {

        $user = $this->getUser();
        if ($this->userHandler->isRestricted( $user, AccountRestriction::RestrictionGlobalCommunication ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (!$parser->has_all(['title','content','users'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $title = $parser->trimmed('title');
        $text  = $parser->trimmed('content');
        $user_ids = $parser->get('users');
        array_map( fn($u) => (int)$u, is_array($parser->get('users')) ? $parser->get('users') : [] );

        $users = $this->entity_manager->getRepository(User::class)->findBy(['id' => $user_ids]);
        if (count($user_ids) !== count($users)) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (count($users) > 100) return AjaxResponse::error( self::ErrorGPMMemberLimitHit);

        foreach ($users as $chk_user)
            if ($userHandler->hasRole($chk_user, 'ROLE_DUMMY')) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var User[] $blocked_users */
        $blocked_users = [];
        $valid_non_blocked = 0;
        $users = array_filter($users, function(User $chk_user) use ($user,&$valid_non_blocked,&$blocked_users) {
            if ($chk_user === $user) return true;
            if ($this->userHandler->checkRelation($chk_user,$user,SocialRelation::SocialRelationTypeBlock)) {
                $blocked_users[] = $chk_user;
                return false;
            }

            $valid_non_blocked++;
            return true;
        });

        if ($valid_non_blocked === 0) return AjaxResponse::error(ErrorHelper::ErrorBlockedByUser);

        if ($em->getRepository(UserGroupAssociation::class)->countRecentRecipients($user) > 100)
            return AjaxResponse::error( self::ErrorGPMThreadLimitHit);

        if (mb_strlen($title) < 3 || mb_strlen($title) > 64)  return AjaxResponse::error( self::ErrorPostTitleLength );
        if (mb_strlen($text) < 2 || mb_strlen($text) > 16384) return AjaxResponse::error( self::ErrorPostTextLength );

        $ts = new DateTime();

        $pg = (new UserGroup())->setType(UserGroup::GroupMessageGroup)->setName( $title )->setRef1(1)->setRef2( $ts->getTimestamp() )->setRef3( $ts->getTimestamp() );
        $this->entity_manager->persist($pg);

        $perm->associate( $user, $pg, UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationLevelFounder);
        foreach ($users as $chk_user)
            if ($user !== $chk_user) $perm->associate( $chk_user, $pg, UserGroupAssociation::GroupAssociationTypePrivateMessageMember);

        $post = (new GlobalPrivateMessage())
            ->setSender($user)->setTimestamp($ts)->setReceiverGroup($pg)->setText($text);

        $tx_len = 0;
        if (!$this->preparePost($user,null,$post,$tx_len, null, $edit))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest, ['a' => 10] );
        if ($tx_len < 2) return AjaxResponse::error( self::ErrorPostTextLength );

        $this->entity_manager->persist( $post );

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        if (!empty($blocked_users)) {
            if (count($blocked_users) === 1)
                $this->addFlash('error', $this->translator->trans('{user} hat dich geblockt und wurde daher aus der Liste der Empfänger für diese Nachricht gestrichen.',['{user}' => $blocked_users[0]->getName()],'global'));
            else {
                $users_text = $this->translator->trans('{users} und {last_user}', ['{users}' => implode( ', ', array_map(fn(User $u) => $u->getName(), array_slice($blocked_users, 0, -1) )), '{last_user}' => $blocked_users[array_key_last($blocked_users)]->getName()], 'global');
                $this->addFlash('error', $this->translator->trans('{users} haben dich geblockt und wurden daher aus der Liste der Empfänger für diese Nachricht gestrichen.',['{users}' => $users_text],'global'));
            }
        }

        return AjaxResponse::success( true , ['url' => $this->generateUrl('pm_view')] );
    }

    /**
     * @Route("api/pm/og_post", name="pm_new_og_thread_controller")
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param UserHandler $userHandler
     * @param PermissionHandler $perm
     * @return Response
     */
    public function new_og_thread_api(JSONRequestParser $parser, EntityManagerInterface $em, UserHandler $userHandler, PermissionHandler $perm): Response {

        $user = $this->getUser();

        if (!$parser->has_all(['title','content','og'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $title = $parser->trimmed('title');
        $text  = $parser->trimmed('content');
        $og = $parser->get_int('og');

        $official_group = $this->entity_manager->getRepository(OfficialGroup::class)->find($og);
        if (!$official_group) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (mb_strlen($title) < 3 || mb_strlen($title) > 64)  return AjaxResponse::error( self::ErrorPostTitleLength );
        if (mb_strlen($text) < 2 || mb_strlen($text) > 16384) return AjaxResponse::error( self::ErrorPostTextLength );

        $ts = new DateTime();

        $pg = (new UserGroup())->setType(UserGroup::GroupMessageGroup)->setName( $title )->setRef1(1)->setRef2( $ts->getTimestamp() )->setRef3( $ts->getTimestamp() );
        $this->entity_manager->persist($pg);

        $perm->associate( $user, $pg, UserGroupAssociation::GroupAssociationTypePrivateMessageMember );

        $post = (new GlobalPrivateMessage())
            ->setSender($user)->setTimestamp($ts)->setReceiverGroup($pg)->setText($text);

        $tx_len = 0;
        if (!$this->preparePost($user,null,$post,$tx_len, null, $edit))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest, ['a' => 10] );
        if ($tx_len < 2) return AjaxResponse::error( self::ErrorPostTextLength );

        $this->entity_manager->persist( (new OfficialGroupMessageLink())->setMessageGroup( $pg )->setOfficialGroup( $official_group ) );

        foreach ($perm->usersInGroup( $official_group->getUsergroup()) as $group_member)
            if ($group_member !== $user)
                $perm->associate( $group_member, $pg, UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember )
                    ->setRef1( 0  )->setRef2( 0 );

        $this->entity_manager->persist( $post );

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success( true , ['url' => $this->generateUrl('pm_view')] );
    }


    /**
     * @Route("api/pm/{id<\d+>}/answer", name="pm_new_post_controller")
     * @param int $id
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param UserHandler $userHandler
     * @return Response
     */
    public function new_post_api(int $id, JSONRequestParser $parser, EntityManagerInterface $em, UserHandler $userHandler): Response {

        $user = $this->getUser();

        $group = $em->getRepository( UserGroup::class )->find($id);
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $official = $this->entity_manager->getRepository(OfficialGroupMessageLink::class)->findOneBy(['messageGroup' => $group]);

        if (!$official && $this->userHandler->isRestricted( $user, AccountRestriction::RestrictionGlobalCommunication ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (!$parser->has('content', true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(),
            'associationType' => [UserGroupAssociation::GroupAssociationTypePrivateMessageMember,UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember], 'association' => $group]);

        if (!$group_association) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        // Check the last 4 posts; if they were all made by the same user, they must wait 5min before they can post again
        /** @var GlobalPrivateMessage[] $last_posts */
        $last_posts = $this->entity_manager->getRepository(GlobalPrivateMessage::class)->findBy(['receiverGroup' => $group], ['timestamp' => 'DESC'], 4);
        if (count($last_posts) === 4) {
            $all_by_user = true;
            foreach ($last_posts as $last_post) $all_by_user = $all_by_user && ($last_post->getSender() === $user);
            if ($all_by_user && $last_posts[0]->getTimestamp()->getTimestamp() > (time() - 300) )
                return AjaxResponse::error( self::ErrorForumLimitHit );
        }

        $text  = $parser->trimmed('content');

        if (mb_strlen($text) < 2 || mb_strlen($text) > 16384) return AjaxResponse::error( self::ErrorPostTextLength );
        $ts = new DateTime();

        $this->entity_manager->persist( $group->setRef1( $group->getRef1() + 1 )->setRef2( $ts->getTimestamp() ) );
        $this->entity_manager->persist( $group_association->setRef1($group_association->getRef1() + 1 ));

        $post = (new GlobalPrivateMessage())->setSender($user)->setTimestamp($ts)->setReceiverGroup($group)->setText($text);

        $tx_len = 0;
        if (!$this->preparePost($user,null,$post,$tx_len, null, $edit))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest, ['a' => 10] );
        if ($tx_len < 2) return AjaxResponse::error( self::ErrorPostTextLength );

        if ($group_association->getAssociationType() === UserGroupAssociation::GroupAssociationTypeOfficialGroupMessageMember && $official)
            $post->setSenderGroup($official->getOfficialGroup());

        $this->entity_manager->persist( $post );

        try {
            $em->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success( true , ['url' => $this->generateUrl('pm_view')] );
    }

    /**
     * @Route("api/pm/{pid<\d+>}/report", name="pm_report_post_controller")
     * @param int $pid
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $ti
     * @return Response
     */
    public function report_post_api(int $pid, EntityManagerInterface $em, TranslatorInterface $ti, JSONRequestParser $parser): Response {
        $user = $this->getUser();

        $message = $em->getRepository( GlobalPrivateMessage::class )->find( $pid );
        if (!$message) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $group = $message->getReceiverGroup();
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(),
            'associationType' => [UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive], 'association' => $group]);
        if (!$group_association) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );


        $targetUser = $message->getSender();
        if ($targetUser->getName() === "Der Rabe" )
            return AjaxResponse::success(true, ['msg' => $ti->trans('Das ist keine gute Idee, das ist dir doch wohl klar!', [], 'game')]);

        $reports = $message->getAdminReports();
        foreach ($reports as $report)
            if ($report->getSourceUser()->getId() == $user->getId())
                return AjaxResponse::success();

        $newReport = (new AdminReport())
            ->setSourceUser($user)
            ->setTs(new DateTime('now'))
            ->setReason( $parser->get_int('reason', 0, 0, 10) )
            ->setGpm($message);

        try {
            $em->persist($newReport);
            $em->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success( true, ['msg' => $ti->trans('Du hast die Nachricht von {username} dem Raben gemeldet. Wer weiß, vielleicht wird {username} heute Nacht stääärben...', ['{username}' => '<span>' . $message->getSender()->getName() . '</span>'], 'game')]);
    }

    /**
     * @Route("api/pm/{pid<\d+>}/pin/{action<\d+>}", name="pm_pin_post_controller")
     * @param int $pid
     * @param int $action
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function report_pin_api(int $pid, int $action, EntityManagerInterface $em): Response {
        $user = $this->getUser();

        if ($action !== 0 && $action !== 1) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $message = $em->getRepository( GlobalPrivateMessage::class )->find( $pid );
        if (!$message || $message->getHidden()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $group = $message->getReceiverGroup();
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(),
                                                                                            'associationType' => [UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive], 'association' => $group]);
        if (!$group_association || $group_association->getAssociationLevel() !== UserGroupAssociation::GroupAssociationLevelFounder)
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($action === 1) {
            $message->setCollapsed( false );
            foreach ($em->getRepository( GlobalPrivateMessage::class )->findBy(['receiverGroup' => $group, 'pinned' => true]) as $pinned)
                $this->entity_manager->persist($pinned->setPinned(false));
        }

        $message->setPinned( $action === 1 );

        $this->entity_manager->persist($message);

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/pm/{pid<\d+>}/collapse/{action<\d+>}", name="pm_collapse_post_controller")
     * @param int $pid
     * @param int $action
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function report_collapse_api(int $pid, int $action, EntityManagerInterface $em): Response {
        $user = $this->getUser();

        if ($action !== 0 && $action !== 1) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $message = $em->getRepository( GlobalPrivateMessage::class )->find( $pid );
        if (!$message || $message->getHidden()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $group = $message->getReceiverGroup();
        if (!$group || $group->getType() !== UserGroup::GroupMessageGroup)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var UserGroupAssociation $group_association */
        $group_association = $em->getRepository(UserGroupAssociation::class)->findOneBy(['user' => $this->getUser(),
                                                                                            'associationType' => [UserGroupAssociation::GroupAssociationTypePrivateMessageMember, UserGroupAssociation::GroupAssociationTypePrivateMessageMemberInactive], 'association' => $group]);
        if (!$group_association || $group_association->getAssociationLevel() !== UserGroupAssociation::GroupAssociationLevelFounder)
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $message->setCollapsed( $action === 1 );
        if ($action === 1) $message->setPinned(false);

        $this->entity_manager->persist($message);

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }
}