<?php

namespace App\Controller\Messages;

use App\Annotations\GateKeeperProfile;
use App\Command\Info\ResolveCommand;
use App\Entity\AccountRestriction;
use App\Entity\AdminDeletion;
use App\Entity\AdminReport;
use App\Entity\Citizen;
use App\Entity\Forum;
use App\Entity\ForumModerationSnippet;
use App\Entity\ForumPoll;
use App\Entity\ForumPollAnswer;
use App\Entity\ForumThreadSubscription;
use App\Entity\ForumUsagePermissions;
use App\Entity\OfficialGroup;
use App\Entity\Post;
use App\Entity\SocialRelation;
use App\Entity\Thread;
use App\Entity\ThreadReadMarker;
use App\Entity\ThreadTag;
use App\Entity\User;
use App\Enum\UserSetting;
use App\Response\AjaxResponse;
use App\Service\CitizenHandler;
use App\Service\CrowService;
use App\Service\ErrorHelper;
use App\Service\HTMLService;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use App\Service\PermissionHandler;
use App\Service\PictoHandler;
use App\Structures\HTMLParserInsight;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @IsGranted("ROLE_USER")
 * @GateKeeperProfile(allow_during_attack=true)
 * @method User getUser
 */
class MessageForumController extends MessageController
{
    protected const ThreadsPerPage = 20;
    protected const PostsPerPage = 10;

    private function default_forum_renderer(int $fid, int $tid, int $pid, int $post_page, EntityManagerInterface $em, JSONRequestParser $parser, CitizenHandler $ch): Response {
        $user = $this->getUser();

        /** @var Forum $forum */
        $forum = $em->getRepository(Forum::class)->find($fid);
        $permissions = $this->perm->getEffectivePermissions( $user, $forum );

        if (!$forum || !$this->perm->isAnyPermitted($permissions, [ ForumUsagePermissions::PermissionModerate, ForumUsagePermissions::PermissionListThreads, ForumUsagePermissions::PermissionReadThreads ]) || ($forum->getTown() && $this->time_keeper->isDuringAttack() && !$this->isGranted("ROLE_CROW", $user) ) )
            return $this->redirect($this->generateUrl('forum_list'));

        $sel_post = $sel_thread = null;
        if ($pid > 0) $sel_post = $em->getRepository(Post::class)->find($pid);
        if ($tid > 0) $sel_thread = $em->getRepository(Thread::class)->find($tid);

        if (($pid > 0 && !$sel_post) || ($tid > 0 && !$sel_thread) || ($sel_thread && $sel_thread->getForum() !== $forum) || ($sel_post && $sel_thread && $sel_post->getThread() !== $sel_thread) )
            return $this->redirect($this->generateUrl('forum_list'));

        // Set the activity status
        if ($forum->getTown() && $user->getActiveCitizen() && $user->getActiveCitizen()->getTown() === $forum->getTown()) {
            $c = $user->getActiveCitizen();
            if ($c) $ch->inflictStatus($c, 'tg_chk_forum');
            $paranoid = $ch->hasStatusEffect($c,'tg_paranoid');
            $em->persist( $c );
            $em->flush();
        } else $paranoid = false;


        $show_hidden_threads = $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate );

        if ( $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionListThreads ) ) {
            $pages = floor(max(0,$em->getRepository(Thread::class)->countByForum($forum, $show_hidden_threads, false)-1) / self::ThreadsPerPage) + 1;

            if ($sel_thread && !$sel_thread->getPinned())
                $page = 1 + floor(($em->getRepository(Thread::class)->countByForum($forum, $show_hidden_threads, false, $sel_thread)) / self::ThreadsPerPage);
            elseif ($parser->has('page'))
                $page = min(max(1,$parser->get('page', 1)), $pages);
            else $page = 1;

            $threads = $em->getRepository(Thread::class)->findByForum($forum, self::ThreadsPerPage, ($page-1)*self::ThreadsPerPage, $show_hidden_threads);
        } elseif ( $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) ) {

            $tp = $ttp = 0;
            $threads = array_filter( $em->getRepository(Thread::class)->findByForum($forum, null, null, $show_hidden_threads), function(Thread $t) use ($tp,$ttp,$sel_thread): bool { $tp++; if ($t === $sel_thread) $ttp = $tp-1; return $t->hasReportedPosts(); } );
            $pages = floor(max(0,count($threads)-1) / self::ThreadsPerPage) + 1;
            if ($sel_thread && !$sel_thread->getPinned())
                $page = 1 + ($ttp / self::ThreadsPerPage);
            elseif ($parser->has('page'))
                $page = min(max(1,$parser->get('page', 1)), $pages);
            else $page = 1;

            $threads = array_slice($threads, ($page-1)*self::ThreadsPerPage, self::ThreadsPerPage);
        } else {
            $page = $pages = 1;
            $threads = [];
        }

        $global_marker = $em->getRepository(ThreadReadMarker::class)->findGlobalAndUser($user);

        foreach ($threads as $thread) {
            /** @var Thread $thread */
            /** @var ThreadReadMarker $marker */
            $marker = $em->getRepository(ThreadReadMarker::class)->findByThreadAndUser($user, $thread);
            if (($thread->lastPost( $show_hidden_threads )?->getId() ?? 0) > max( $global_marker?->getPost()->getId() ?? 0, $marker?->getPost()->getId() ?? 0))
                $thread->setNew();
        }

        usort( $threads, fn(Thread $a, Thread $b) => $b->lastPost( $show_hidden_threads )?->getDate() <=> $a->lastPost( $show_hidden_threads )?->getDate() );

        if ( $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionListThreads ) ) {
            $pinned_threads = $em->getRepository(Thread::class)->findPinnedByForum($forum, null, null, $show_hidden_threads);
        } elseif ( $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) ) {
            $pinned_threads = array_filter( $em->getRepository(Thread::class)->findPinnedByForum($forum, null, null, $show_hidden_threads), fn(Thread $t): bool => $t->hasReportedPosts() );
        } else $pinned_threads = [];

        foreach ($pinned_threads as $thread) {
            /** @var Thread $thread */
            /** @var ThreadReadMarker $marker */
            $marker = $em->getRepository(ThreadReadMarker::class)->findByThreadAndUser($user, $thread);
            if (($thread->lastPost( $show_hidden_threads )?->getId() ?? 0) > max( $global_marker?->getPost()->getId() ?? 0, $marker?->getPost()->getId() ?? 0))
                $thread->setNew();
        }
        
        return $this->render( 'ajax/forum/view.html.twig', $this->addDefaultTwigArgs(null, [
            'forum' => $forum,
            'threads' => $threads,
            'pinned_threads' => $pinned_threads,
            'permission' => $this->getPermissionObject( $permissions ),
            'select' => $tid,
            'jump' => $pid,
            'post_page' => $post_page,
            'town' => $forum->getTown() ?? false,
            'pages' => $pages,
            'current_page' => $page,
            'paranoid' => $paranoid
        ] ));
    }

    /**
     * @Route("jx/forum/town", name="forum_town_redirect")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function forum_redirector(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        /** @var Citizen $citizen */
        $citizen = $em->getRepository(Citizen::class)->findActiveByUser( $user );

        if ($citizen !== null && $citizen->getAlive() && $citizen->getTown()->getForum() && $this->perm->checkEffectivePermissions( $user, $citizen->getTown()->getForum(), ForumUsagePermissions::PermissionRead ) && !$this->time_keeper->isDuringAttack())
            return $this->redirect($this->generateUrl('forum_view', ['id' => $citizen->getTown()->getForum()->getId()]));
        else return $this->redirect( $this->generateUrl( 'forum_list' ) );
    }

    /**
     * @Route("jx/forum/{id<\d+>}", name="forum_view")
     * @param int $id
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $p
     * @param CitizenHandler $ch
     * @return Response
     */
    public function forum(int $id, EntityManagerInterface $em, JSONRequestParser $p, CitizenHandler $ch): Response
    {
        return $this->default_forum_renderer($id,-1,-1, -1, $em, $p, $ch);
    }

    /**
     * @Route("jx/forum/{fid<\d+>}/{tid<\d+>}/{page<\d+>}", name="forum_thread_view")
     * @param int $fid
     * @param int $tid
     * @param int $page
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $p
     * @param CitizenHandler $ch
     * @return Response
     */
    public function forum_thread(int $fid, int $tid, EntityManagerInterface $em, JSONRequestParser $p, CitizenHandler $ch, int $page = -1): Response
    {
        return $this->default_forum_renderer($fid,$tid,-1, $page, $em,$p,$ch);
    }

    /**
     * @Route("jx/forum/jump/{pid<\d+>}", name="forum_jump_view")
     * @param int $pid
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $p
     * @param CitizenHandler $ch
     * @return Response
     */
    public function forum_jump_post(int $pid, EntityManagerInterface $em, JSONRequestParser $p, CitizenHandler $ch): Response
    {
        /** @var Post $post */
        $post = $this->entity_manager->getRepository(Post::class)->find($pid);

        return $this->default_forum_renderer($post ? $post->getThread()->getForum()->getId() : -1,$post ? $post->getThread()->getId() : -1,$post ? $pid : -1, -1, $em,$p,$ch);
    }

    /**
     * @Route("jx/forum", name="forum_list")
     * @return Response
     */
    public function forums(): Response
    {
        /** @var Forum[] $forums */
        $forums = array_filter($this->perm->getForumsWithPermission($this->getUser()), function ($f) {
            /** @var Forum $f */
            return !$f->getTown() || (!$this->time_keeper->isDuringAttack()) || $this->isGranted("ROLE_CROW", $this->getUser());
        });
        $subscriptions = $this->getUser()->getForumThreadSubscriptions()->filter(fn(ForumThreadSubscription $s) => !$s->getThread()->getHidden() && in_array($s->getThread()->getForum(), $forums));

        $forums_new = [];
        foreach ($forums as $forum) {

            if ( (!$forum->getTown() && $this->getUser()->getMutedForums()->contains( $forum )) || !$this->perm->checkEffectivePermissions( $this->getUser(), $forum, ForumUsagePermissions::PermissionListThreads )) {
                $forums_new[$forum->getId()] = false;
                continue;
            }

            if ($forum->getTown()) {
                if (!$forum->getTown()->userInTown($this->getUser())) {
                    $forums_new[$forum->getId()] = false;
                    continue;
                }

                $forums_new[$forum->getId()] = $this->entity_manager->getRepository(Thread::class)->countThreadsWithUnreadPosts(
                    $this->getUser(), $forum
                ) > 0;
            } else $forums_new[$forum->getId()] = $this->entity_manager->getRepository(Thread::class)->countThreadsWithUnreadPosts(
                $this->getUser(), $this->entity_manager->getRepository(Thread::class)->firstPageThreadIDs( $forum, self::ThreadsPerPage )
            ) > 0;
        }

        $forum_sections = array_unique( array_filter( array_map( fn(Forum $f) => $f->getWorldForumLanguage(), $forums ) ) );
        usort( $forum_sections, function(string $a, string $b) {
            if ($a === $b) return 0;
            if ($a === $this->getUserLanguage()) return -1;
            if ($b === $this->getUserLanguage()) return 1;
            return $a <=> $b;
        } );

        return $this->render( 'ajax/forum/list.html.twig', $this->addDefaultTwigArgs(null, [
            'user' => $this->getUser(),
            'forums' => $forums,
            'forums_new' => $forums_new,
            'subscriptions' => $subscriptions,
            'forumSections' => $forum_sections,
            'official_groups' => $this->entity_manager->getRepository(OfficialGroup::class)->findBy(['lang' => [$this->getUserLanguage(),'multi']])
        ] ));
    }

    protected function should_notify( User $from, User $to ): bool {
        if ($from === $to) return false;
        if (!$to->getSetting( UserSetting::NotifyMeWhenMentioned )) return false;
        if ($this->userHandler->checkRelation($to,$from,SocialRelation::SocialRelationTypeBlock)) return false;
        return true;
    }

    /**
     * @Route("api/forum/{id<\d+>}/post", name="forum_new_thread_controller")
     * @param int $id
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function new_thread_api(int $id, JSONRequestParser $parser, EntityManagerInterface $em, RateLimiterFactory $forumThreadCreationLimiter, CrowService $crow): Response {

        /** @var Forum $forum */
        $forum = $em->getRepository(Forum::class)->find($id);
        if (!$forum) return AjaxResponse::error( self::ErrorForumNotFound );

        $user = $this->getUser();
        $permission = $this->perm->getEffectivePermissions($user,$forum);
        if ($this->userHandler->isRestricted( $user, AccountRestriction::RestrictionForum ) || !$this->perm->isPermitted( $permission, ForumUsagePermissions::PermissionCreateThread ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (!$parser->has_all(['title','text'], true))
            return AjaxResponse::error(self::ErrorPostTitleTextMissing );

        $town_citizen = $forum->getTown() ? $user->getCitizenFor( $forum->getTown() ) : null;
        $is_heroic = !$forum->getTown() || ( $town_citizen && $town_citizen->getAlive() && $town_citizen->getProfession()->getHeroic() );

        $title = $parser->trimmed('title');
        $tag   = ($this->userHandler->hasSkill($user, 'writer') && $is_heroic) ? $parser->trimmed('tag') : null;
        $text  = $parser->trimmed('text');

        if (empty($tag) || $tag === '-none-')
            $tag = null;
        else $tag = $this->entity_manager->getRepository(ThreadTag::class)->findOneBy(['name' => $tag]);

        if ($tag !== null) {
            if ($tag->getPermissionMap() !== null && !$this->perm->isPermitted( $permission, $tag->getPermissionMap() ))
                return AjaxResponse::error( ErrorHelper::ErrorPermissionError );
            if (!$forum->getAllowedTags()->contains($tag))
                return AjaxResponse::error( ErrorHelper::ErrorPermissionError );
        }

        $type = $parser->get('type') ?? 'USER';
        $valid = ['USER'];
        if ($this->perm->isPermitted( $permission, ForumUsagePermissions::PermissionPostAsAnim )) $valid[] = 'ANIM';
        if ($this->perm->isPermitted( $permission, ForumUsagePermissions::PermissionPostAsCrow )) $valid[] = 'CROW';
        if ($this->perm->isPermitted( $permission, ForumUsagePermissions::PermissionPostAsDev )) $valid[] = 'DEV';
        if (!in_array($type, $valid)) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (mb_strlen($title) < 3 || mb_strlen($title) > 64)  return AjaxResponse::error( self::ErrorPostTitleLength );
        if (mb_strlen($text) < 2 || mb_strlen($text) > 16384) return AjaxResponse::error( self::ErrorPostTextLength );

        if ($town_citizen)
            $title = $this->html->htmlDistort( $title,
                                            ($this->citizen_handler->hasStatusEffect($town_citizen, 'drunk') ? HTMLService::ModulationDrunk : HTMLService::ModulationNone) |
                                            ($this->citizen_handler->hasStatusEffect($town_citizen, 'terror') ? HTMLService::ModulationTerror : HTMLService::ModulationNone) |
                                            ($this->citizen_handler->hasStatusEffect($town_citizen, 'wound1') ? HTMLService::ModulationHead : HTMLService::ModulationNone)
                , $town_citizen->getTown()->getRealLanguage($this->generatedLangsCodes) ?? $this->getUserLanguage(), $d );

        if ( !$this->isGranted('ROLE_ELEVATED') && !$forumThreadCreationLimiter->create( $user->getId() )->consume( $forum->getTown() ? 1 : 2 )->isAccepted())
            return AjaxResponse::error( ErrorHelper::ErrorRateLimited );

        $thread = (new Thread())->setTitle( $title )->setTag($tag)->setOwner($user);

        $map_type = [
            'CROW' => 66,
            'ANIM' => 67,
        ];

        $post = (new Post())
            ->setOwner( isset($map_type[$type]) ? $this->entity_manager->getRepository(User::class)->find($map_type[$type]) : $user )
            ->setText( $text )
            ->setDate( new DateTime('now') )
            ->setType($type)
            ->setEditingMode( Post::EditorPerpetual )
            ->setLastAdminActionBy($type === "CROW" ? $user : null);

        /** @var HTMLParserInsight $insight */
        if (!$this->preparePost($user,$forum,$post, null, $insight))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        if ($insight->text_length < 2) return AjaxResponse::error( self::ErrorPostTextLength );

        if (!$insight->editable) $post->setEditingMode( Post::EditorLocked );

        $forum->addThread($thread);
        $thread->addPost($post)->setLastPost( $post->getDate() );

        try {
            if (!$user->getNoAutoFollowThreads()) $em->persist((new ForumThreadSubscription())->setThread($thread)->setUser($user));
            $em->persist($thread);
            $em->persist($forum);

            $this->commit_post_with_polls($em,$post,$insight->polls ?? []);
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        $has_notif = false;
        foreach ( $insight->taggedUsers as $tagged_user )
            if ( $this->should_notify( $user, $tagged_user ) && $this->perm->checkEffectivePermissions( $tagged_user, $forum, ForumUsagePermissions::PermissionReadThreads ) ) {
                $this->entity_manager->persist( $crow->createPM_mentionNotification( $tagged_user, $post ) );
                $has_notif = true;
            }
        if ($has_notif) try { $this->entity_manager->flush(); } catch (\Throwable) {}

        return AjaxResponse::success( true, ['url' => $this->generateUrl('forum_thread_view', ['fid' => $id, 'tid' => $thread->getId()])] );
    }

    /**
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/cast_vote", name="forum_poll_cast_api")
     * @param int $fid
     * @param int $tid
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param Locksmith $locksmith
     * @return Response
     */
    public function poll_cast_api( int $fid, int $tid, JSONRequestParser $parser, EntityManagerInterface $em, Locksmith $locksmith): Response {
        $user = $this->getUser();

        $thread = $em->getRepository(Thread::class)->find( $tid );
        if (!$thread || $thread->getForum()->getId() !== $fid) return AjaxResponse::error( self::ErrorForumNotFound );
        /** @var Forum $forum */
        $forum = $thread->getForum();

        $poll = $this->entity_manager->getRepository(ForumPoll::class)->find($parser->get_int('poll'));
        if (!$poll || !$poll->getPost() || $poll->getPost()->getThread() !== $thread)
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $read_only = !$this->perm->isPermitted( $this->perm->getEffectivePermissions($user, $forum), ForumUsagePermissions::PermissionCreatePost ) ||
            $this->userHandler->isRestricted( $user, AccountRestriction::RestrictionForum );

        $answer = $parser->get_int('cast');
        if ($read_only || $poll->getClosed() || ($poll->getParticipants()->contains($user) && $answer !== -666))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($answer === -42 || $answer === -666) $answer = null;
        else {
            $answer = $this->entity_manager->getRepository(ForumPollAnswer::class)->find($answer);
            if (!$answer || $answer->getPoll() !== $poll) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        }

        $lock = $locksmith->waitForLock("poll-{$poll->getId()}");
        if ($answer)
            $this->entity_manager->persist($answer->setNum( $answer->getNum() + 1 ));

        if ($parser->get_int('cast') === -666) $poll->setClosed(true);
        $this->entity_manager->persist($poll->addParticipant($user));

        try {
            $em->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/polls", name="forum_poll_query_api")
     * @param int $fid
     * @param int $tid
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function poll_query_api( int $fid, int $tid, JSONRequestParser $parser, EntityManagerInterface $em): Response {
        $user = $this->getUser();

        $thread = $em->getRepository(Thread::class)->find( $tid );
        if (!$thread || $thread->getForum()->getId() !== $fid) return AjaxResponse::error( self::ErrorForumNotFound );

        /** @var Forum $forum */
        $forum = $thread->getForum();

        $permissions = $this->perm->getEffectivePermissions($user, $forum);
        if (!$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionReadThreads ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $read_only = !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionCreatePost ) ||
            $this->userHandler->isRestricted( $user, AccountRestriction::RestrictionForum );

        $data = [];
        foreach ($parser->get_array('polls') as $poll_id) {
            if (!is_numeric($poll_id) || ($id = (int)$poll_id) <= 0) continue;

            $poll = $em->getRepository(ForumPoll::class)->find($id);
            if (!$poll || !$poll->getPost() || $read_only || $poll->getPost()->getThread() !== $thread)
                $data[$poll_id] = false;
            else if (!$poll->getClosed() && !$poll->getParticipants()->contains( $user ))
                $data[$poll_id] = true;
            else {
                $data[$poll_id] = [];
                foreach ($poll->getAnswers() as $answer) $data[$poll_id][] = [$answer->getId(),$answer->getNum()];
                $data[$poll_id][] = [-666,$poll->getOwner() === $user && !$poll->getClosed()];
            }
        }

        return AjaxResponse::success(true, ['response' => $data]);
    }

    /**
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/post", name="forum_new_post_controller")
     * @param int $fid
     * @param int $tid
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param PictoHandler $ph
     * @return Response
     */
    public function new_post_api(int $fid, int $tid, JSONRequestParser $parser, EntityManagerInterface $em, PictoHandler $ph, CrowService $crow): Response {
        $user = $this->getUser();

        $thread = $em->getRepository(Thread::class)->find( $tid );
        if (!$thread || $thread->getForum()->getId() !== $fid) return AjaxResponse::error( self::ErrorForumNotFound );

        /** @var Forum $forum */
        $forum = $thread->getForum();

        $permissions = $this->perm->getEffectivePermissions($user, $forum);

        if ($this->userHandler->isRestricted( $user, AccountRestriction::RestrictionForum ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $mod_post = false;
        if (!$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionCreatePost )) {
            if ($thread->hasReportedPosts(false) && $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) )
                $mod_post = true;
            else return AjaxResponse::error( ErrorHelper::ErrorPermissionError );
        }

        if (($thread->getLocked() || $thread->getHidden()) && !$this->perm->isPermitted($permissions, ForumUsagePermissions::PermissionModerate))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        // Check the last 4 posts; if they were all made by the same user, they must wait 4h before they can post again
        if (!$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate )) {
            $last_posts = $this->entity_manager->getRepository(Post::class)->findBy(['thread' => $thread], ['date' => 'DESC'], 4);
            if (count($last_posts) === 4) {
                $all_by_user = true;
                foreach ($last_posts as $last_post) $all_by_user = $all_by_user && ($last_post->getOwner() === $user);
                if ($all_by_user && $last_posts[0]->getDate()->getTimestamp() > (time() - 14400) )
                    return AjaxResponse::error( self::ErrorForumLimitHit );
            }
        }


        if (!$parser->has_all(['text'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $text = $parser->get('text');

        $type = $parser->get('type') ?? 'USER';
        $valid = ['USER'];
        if ($this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionPostAsAnim )) $valid[] = 'ANIM';
        if ($this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionPostAsCrow )) $valid[] = 'CROW';
        if ($this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionPostAsDev )) $valid[] = 'DEV';
        if (!in_array($type, $valid)) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $map_type = [
            'CROW' => 66,
            'ANIM' => 67
        ];

        $post = (new Post())
            ->setOwner( isset($map_type[$type]) ? $this->entity_manager->getRepository(User::class)->find($map_type[$type]) : $user )
            ->setText( $text )
            ->setDate( new DateTime('now') )
            ->setType($type)
            ->setEditingMode( $type !== "USER" ? Post::EditorPerpetual : Post::EditorTimed )
            ->setLastAdminActionBy($type === "CROW" ? $user : null);

        /** @var HTMLParserInsight $insight */
        if (!$this->preparePost($user,$forum,$post, null, $insight))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($insight->text_length < 2) return AjaxResponse::error( self::ErrorPostTextLength );

        if (!$insight->editable) $post->setEditingMode(Post::EditorLocked);

        $thread->addPost($post)->setLastPost( $post->getDate() );
        if ($forum->getTown()) {
            /** @var Citizen $current_citizen */
            $current_citizen = $this->entity_manager->getRepository(Citizen::class)->findOneBy(['user' => $user, 'town' => $forum->getTown(), 'alive' => true]);
            if ($current_citizen) {
                // Give picto if the post is in the town forum
                $ph->give_picto($current_citizen, 'r_forum_#00');
            }
        }

        /** @var ForumThreadSubscription[] $subscriptions */
        $subscriptions = $em->getRepository(ForumThreadSubscription::class)->matching(
            (new Criteria())
                ->andWhere( Criteria::expr()->neq('user', $user) )
                ->andWhere( Criteria::expr()->eq('thread', $thread) )
                ->andWhere( Criteria::expr()->lt('num', 10) )
        );

        foreach ($subscriptions as $s) $em->persist($s->setNum($s->getNum() + 1));

        try {
            $em->persist($thread);
            $em->persist($forum);

            $this->commit_post_with_polls($em,$post,$insight->polls ?? []);

        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        $has_notif = false;
        foreach ( $insight->taggedUsers as $tagged_user )
            if ( $this->should_notify( $user, $tagged_user ) && $this->perm->checkEffectivePermissions( $tagged_user, $forum, ForumUsagePermissions::PermissionReadThreads ) ) {
                $this->entity_manager->persist( $crow->createPM_mentionNotification( $tagged_user, $post ) );
                $has_notif = true;
            }
        if ($has_notif) try { $this->entity_manager->flush(); } catch (\Throwable) {}

        return AjaxResponse::success( true, ['url' =>
            $this->generateUrl('forum_thread_view', ['fid' => $fid, 'tid' => $tid])
        ] );
    }

    protected function commit_post_with_polls(EntityManagerInterface $em, Post $post, array $polls = []) {
        $em->persist($post);

        $question_assoc = [];
        $answer_assoc = [];

        if (!empty($polls)) {
            foreach ($polls as $question => $answers) {
                $em->persist($question_assoc[$question] = (new ForumPoll())
                    ->setOwner($post->getOwner())
                    ->setPost($post)
                    ->setClosed(false)
                );
                foreach ($answers as $answer)
                    $question_assoc[$question]->addAnswer(
                        $answer_assoc[$answer] = (new ForumPollAnswer())->setNum(0)
                    );
            }
        }

        $em->flush();

        if (!empty($polls)) {
            $tx = $post->getText();
            $tx = str_replace( array_keys( $question_assoc ), array_map( fn(ForumPoll $o) => $o->getId(), array_values( $question_assoc ) ), $tx );
            $tx = str_replace( array_keys( $answer_assoc ), array_map( fn(ForumPollAnswer $o) => $o->getId(), array_values( $answer_assoc ) ), $tx );
            $post->setText($tx);

            $em->persist($post);
            $em->flush();
        }
    }

    /**
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/{pid<\d+>}/edit", name="forum_edit_post_controller")
     * @param int $fid
     * @param int $tid
     * @param int $pid
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param CrowService $crow
     * @return Response
     */
    public function edit_post_api(int $fid, int $tid, int $pid, JSONRequestParser $parser, EntityManagerInterface $em, CrowService $crow): Response {
        $user = $this->getUser();
        if ($this->userHandler->isRestricted( $user, AccountRestriction::RestrictionForum )) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $post = $em->getRepository(Post::class)->find($pid);
        if (!$post) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($post->getTranslate()) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        /** @var Thread $thread */
        $thread = $em->getRepository(Thread::class)->find( $tid );
        if (!$thread || $thread->getForum()->getId() !== $fid || $post->getThread() !== $thread)
            return AjaxResponse::error( self::ErrorForumNotFound );

        $permission = $this->perm->getEffectivePermissions($user, $thread->getForum());

        $mod_permissions = $thread->hasReportedPosts(false) && $this->perm->isPermitted($permission, ForumUsagePermissions::PermissionModerate);

        if ($post->getOwner()->getId() === 66 && !$this->perm->isPermitted($permission, ForumUsagePermissions::PermissionPostAsCrow | ForumUsagePermissions::PermissionEditPost))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($post->getOwner()->getId() === 67 && !$this->perm->isPermitted($permission, ForumUsagePermissions::PermissionPostAsAnim | ForumUsagePermissions::PermissionEditPost))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ((($post->getOwner() !== $user && $post->getOwner()->getId() !== 66) || !$post->isEditable()) && !$mod_permissions && !$this->perm->isPermitted($permission, ForumUsagePermissions::PermissionModerate | ForumUsagePermissions::PermissionEditPost) )
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (($thread->getLocked() || $thread->getHidden() || ($post !== $thread->lastPost(false) && $post !== $thread->firstPost(true))) && !$mod_permissions && !$this->perm->isPermitted($permission, ForumUsagePermissions::PermissionModerate))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        /** @var Forum $forum */
        $forum = $thread->getForum();

        if (!$parser->has_all(['text'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $text = $parser->get('text');

        $old_text = $post->getText();
        $post
            ->setText( $text )
            ->setEdited( new DateTime() );

        if ($post === $thread->firstPost(true) && $parser->has('title',true) && !$thread->getTranslatable()) {
            $title = $parser->get('title');

            $town_citizen = $forum->getTown() ? $user->getCitizenFor( $forum->getTown() ) : null;
            $is_heroic = !$forum->getTown() || ( $town_citizen && $town_citizen->getAlive() && $town_citizen->getProfession()->getHeroic() );

            $tag = ($this->userHandler->hasSkill($user, 'writer') && $is_heroic) ? $parser->trimmed('tag') : null;

            if (empty($tag) || $tag === '-none-')
                $tag = null;
            else $tag = $this->entity_manager->getRepository(ThreadTag::class)->findOneBy(['name' => $tag]);

            if ($tag !== null) {
                if ($tag->getPermissionMap() !== null && !$this->perm->isPermitted( $permission, $tag->getPermissionMap() ))
                    $tag = null;
                if (!$forum->getAllowedTags()->contains($tag))
                    $tag = null;
            }

            if (mb_strlen($title) >= 3 && mb_strlen($title) <= 64) {
                if ($town_citizen)
                    $title = $this->html->htmlDistort( $title,
                                                       ($this->citizen_handler->hasStatusEffect($town_citizen, 'drunk') ? HTMLService::ModulationDrunk : HTMLService::ModulationNone) |
                                                       ($this->citizen_handler->hasStatusEffect($town_citizen, 'terror') ? HTMLService::ModulationTerror : HTMLService::ModulationNone) |
                                                       ($this->citizen_handler->hasStatusEffect($town_citizen, 'wound1') ? HTMLService::ModulationHead : HTMLService::ModulationNone)
                        , $town_citizen->getTown()->getRealLanguage($this->generatedLangsCodes) ?? $this->getUserLanguage(), $d );

                $thread->setTitle($title)->setTag($tag);
                $this->entity_manager->persist($thread);
            }
        }

        /** @var HTMLParserInsight $insight */
        if (!$this->preparePost($user,$forum,$post, null, $insight))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($insight->text_length < 2) return AjaxResponse::error( self::ErrorPostTextLength );

        if (!$insight->editable) $post->setEditingMode(Post::EditorLocked);

        if ($user !== $post->getOwner()) {
            $post
                ->setEditingMode(Post::EditorLocked)
                ->setLastAdminActionBy($user);
            if ($post->getOriginalText() === null && $post->getOwner()->getId() !== 66)
                $post->setOriginalText($old_text);

            $notification = $crow->createPM_moderation( $post->getOwner(),
                CrowService::ModerationActionDomainForum, CrowService::ModerationActionTargetPost, CrowService::ModerationActionEdit,
                $post
            );
            if ($notification) $em->persist($notification);
        }

        try {

            $this->commit_post_with_polls($em,$post,$insight->polls ?? []);

        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success( true, ['url' => $this->generateUrl('forum_jump_view', ['pid' => $pid])] );
    }


    /**
     * @Route("api/forum/{sem<\d+>}/{fid<\d+>}/preview", name="forum_previewer_controller")
     * @param int $fid
     * @param int $sem
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function small_viewer_api( int $fid, int $sem, EntityManagerInterface $em, CitizenHandler $ch) {
        $user = $this->getUser();

        if ($sem === 0) return new Response('');

        /** @var Forum $forum */
        $forum = $em->getRepository(Forum::class)->find($fid);
        if (!$forum || !$this->perm->checkEffectivePermissions( $user, $forum, ForumUsagePermissions::PermissionReadThreads ))
            return new Response('');

        /** @var Thread $thread */
        $thread = $em->getRepository(Thread::class)->findByForumSemantic( $forum, $sem );
        if (!$thread || $thread->getHidden() || $thread->getForum()->getId() !== $fid) return new Response(' ');

        $posts = $em->getRepository(Post::class)->findUnhiddenByThread($thread, 5, -5);

        // Check for paranoia
        if ($forum->getTown() && $user->getActiveCitizen() && $user->getActiveCitizen()->getTown() === $forum->getTown())
            $paranoid = $ch->hasStatusEffect($user->getActiveCitizen(),'tg_paranoid');
        else $paranoid = false;

        foreach ($posts as $post)
            /** @var Post $post */
            $post->setHydrated( $this->html->prepareEmotes( $post->getText(), $this->getUser(), $post->getThread()->getForum()->getTown() ) );

        return $this->render( 'ajax/forum/posts_small.html.twig', [
            'posts' => $posts,
            'town' => $forum->getTown() ? $forum->getTown() : false,
            'fid' => $fid,
            'tid' => $thread->getId(),
            'paranoid' => $paranoid
        ] );
    }

    /**
     * @Route("api/forum/{tid<\d+>}/{fid<\d+>}/view/{pid<\d+>}", name="forum_viewer_controller")
     * @param int $fid
     * @param int $tid
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @param SessionInterface $session
     * @param CitizenHandler $ch
     * @param int $pid
     * @return Response
     */
    public function viewer_api(int $fid, int $tid, EntityManagerInterface $em, JSONRequestParser $parser, SessionInterface $session, CitizenHandler $ch, int $pid = -1): Response {
        $user = $this->getUser();

        $hydrate_post = fn(Post $post, bool $include_original = false) => $post->getHydrated() ? $post : $post->setHydrated(
            $this->html->prepareEmotes( $post->getText(), $user, $post->getThread()->getForum()->getTown() ),
            $include_original && $post->getOriginalText() ? $this->html->prepareEmotes( $post->getOriginalText(), $user, $post->getThread()->getForum()->getTown() ) : null
        );

        /** @var Thread $thread */
        $thread = $em->getRepository(Thread::class)->find( $tid );
        if (!$thread || $thread->getForum()->getId() !== $fid) return new Response('');

        /** @var Forum $forum */
        $forum = $em->getRepository(Forum::class)->find($fid);
        $permissions = $this->perm->getEffectivePermissions( $user, $forum );

        if ($thread->getHidden() && !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ))
            return new Response('');

        if (!$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionReadThreads )) {
            if (!$thread->hasReportedPosts(false) || !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) )
                return new Response('', 200, ['X-AJAX-Control' => 'reload']);
        }

        $jump_post = ($pid > 0) ? $em->getRepository(Post::class)->find( $pid ) : null;
        if ($jump_post && $jump_post->getThread() !== $thread) return new Response('');

        $marker = $em->getRepository(ThreadReadMarker::class)->findByThreadAndUser( $user, $thread );
        if (!$marker) $marker = (new ThreadReadMarker())->setUser($user)->setThread($thread);

        if ($this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ))
            $pages = floor(max(0,$em->getRepository(Post::class)->countByThread($thread)-1) / self::PostsPerPage) + 1;
        else
            $pages = floor(max(0,$em->getRepository(Post::class)->countUnhiddenByThread($thread)-1) / self::PostsPerPage) + 1;

        if ($jump_post)
            $page = min($pages,1 + floor(($em->getRepository(Post::class)->getOffsetOfPostByThread( $thread, $jump_post, $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) )) / self::PostsPerPage));
        elseif ($parser->has('page'))
            $page = min(max(1,$parser->get('page', 1)), $pages);
        elseif (!$marker->getPost()) $page = 1;
        else $page = min($pages,1 + floor((1+$em->getRepository(Post::class)->getOffsetOfPostByThread( $thread, $marker->getPost(), $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) )) / self::PostsPerPage));

        if ($this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ))
            $posts = $em->getRepository(Post::class)->findByThread($thread, self::PostsPerPage, ($page-1)*self::PostsPerPage);
        else
            $posts = $em->getRepository(Post::class)->findUnhiddenByThread($thread, self::PostsPerPage, ($page-1)*self::PostsPerPage);


        $announces = [
            'reported' => $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) ? array_map($hydrate_post, $thread->getUnseenReportedPosts()->toArray()) : [],
            'admin' => array_map($hydrate_post, $em->getRepository(Post::class)->findAdminAnnounces($thread)),
            'oracle' => array_map($hydrate_post, $em->getRepository(Post::class)->findOracleAnnounces($thread))
        ];

        foreach ($posts as $post){
            /** @var $post Post */
            if ($marker->getPost() === null || $marker->getPost()->getDate() < $post->getDate())
                $post->setNew();
        }

        $flush = false;

        if (!empty($posts)) {

            /** @var Post $read_post */
            $read_post = $posts[array_key_last($posts)];
            /** @var Post $last_read */
            $last_read = $marker->getPost();
            if ($last_read === null || $read_post->getId() > $last_read->getId()) {
                $marker->setPost($read_post);
                $em->persist($marker);
                $flush = true;
            }

            /** @var ForumThreadSubscription[] $subscriptions */
            $subscriptions = $em->getRepository(ForumThreadSubscription::class)->matching(
                (new Criteria())
                    ->andWhere( Criteria::expr()->eq('user', $user) )
                    ->andWhere( Criteria::expr()->eq('thread', $thread) )
                    ->andWhere( Criteria::expr()->gt('num', 0))
            );

            if (!empty($subscriptions)) {
                foreach ($subscriptions as $s) if ($s->getNum() > 0) {
                    $session->remove('cache_ping');
                    $em->persist($s->setNum(0));
                }
                $flush = true;
            }
        }

        if ($flush) try { $em->flush(); } catch (Exception $e) {}


        foreach ($posts as $post) $hydrate_post($post, $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ));

        // Check for paranoia
        if ($forum->getTown() && $user->getActiveCitizen() && $user->getActiveCitizen()->getTown() === $forum->getTown())
            $paranoid = $ch->hasStatusEffect($user->getActiveCitizen(),'tg_paranoid');
        else $paranoid = false;

        $other_forums_raw = $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) ? array_filter($this->perm->getForumsWithPermission($this->getUser(), ForumUsagePermissions::PermissionModerate), fn(Forum $f) => $f !== $forum ) : [];
        $other_forums = [];

        foreach ( array_merge( [$user->getLanguage()], array_filter($this->generatedLangsCodes, function(string $s) use ($user) { return $s !== $user->getLanguage(); }) ) as $lang )
            $other_forums[ $this->translator->trans('Weltforum', [], 'global', $lang) . " [$lang]"] = array_filter( $other_forums_raw, function(Forum $f) use($lang) { return $f->getTown() === null && $f->getWorldForumLanguage() === $lang; } );

        $other_forums[ $this->translator->trans('Weltforen', [], 'global') ] = array_filter( $other_forums_raw, fn(Forum $f) => $f->getTown() === null && $f->getWorldForumLanguage() === null );
        $other_forums[ $this->translator->trans('Stadtforum', [], 'global') ] = array_filter( $other_forums_raw, fn(Forum $f) => $f->getTown() !== null );

        return $this->render( 'ajax/forum/posts.html.twig', [
            'posts' => $posts,
            'owned' => $thread->getOwner() === $user,
            'locked' => $thread->getLocked(),
            'solved' => $thread->getLocked() && $thread->getSolved(),
            'pinned' => $thread->getPinned(),
            'title' => $thread->getTranslatable() ? $this->translator->trans($thread->getTitle(), [], 'game') : $thread->getTitle(),
            'thread' => $thread,
            'fid' => $fid,
            'tid' => $tid,
            'current_page' => $page,
            'town' => $forum->getTown() ? $forum->getTown() : false,

            'permission' => $this->getPermissionObject($permissions),

            'pages' => $pages,
            'announces' => $announces,
            'markedPost' => $pid,
            'subscribed' => $em->getRepository(ForumThreadSubscription::class)->count( ['user' => $user, 'thread' => $thread] ),

            'paranoid' => $paranoid,

            'thread_moveable_forums' => $other_forums
        ] );
    }

    /**
     * @Route("jx/forum/{id<\d+>}/editor", name="forum_thread_editor_controller")
     * @param int $id
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function editor_thread_api(int $id, EntityManagerInterface $em): Response {
        $forum = $em->getRepository(Forum::class)->find($id);
        $user = $this->getUser();
        $permissions = $this->perm->getEffectivePermissions( $user, $forum );

        if (!$forum || !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionCreateThread ))
            return new Response('');

        $town = $forum->getTown();
        $town_citizen = $town ? $user->getCitizenFor( $town ) : null;

        $username = $town_citizen ? $town_citizen->getName() : $user->getName();
        $is_heroic = !$forum->getTown() || ( $town_citizen && $town_citizen->getAlive() && $town_citizen->getProfession()->getHeroic() );

        $tags = ($this->userHandler->hasSkill($user, 'writer') && $is_heroic) ? array_filter( $forum->getAllowedTags()->getValues(),
            fn(ThreadTag $tag) => $tag->getPermissionMap() === null || $this->perm->isPermitted( $permissions, $tag->getPermissionMap() )
        ) : [];

        return $this->render( 'ajax/forum/editor.html.twig', [
            'fid' => $id,
            'tid' => null,
            'pid' => null,

            'permission' => $this->getPermissionObject( $permissions ),
            'snippets' => $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionPostAsCrow ) ? $this->entity_manager->getRepository(ForumModerationSnippet::class)->findAll() : [],

            'emotes' => $this->getEmotesByUser($user,true),
            'username' => $username,
            'forum' => true,
            'town_controls' => $forum->getTown() !== null,
            'tags' => $tags,
            'langsCodes' => $this->generatedLangsCodes
        ] );
    }

    /**
     * @Route("jx/forum/query", name="forum_query_controller")
     * @param JSONRequestParser $json
     * @return Response
     */
    public function forum_query(JSONRequestParser $json): Response {

        $forum = ($fid = $json->get_int('fid',-1)) > 0 ? $this->entity_manager->getRepository(Forum::class)->find($fid) : null;
        if ($fid > 0 && ($forum === null || !$this->perm->checkEffectivePermissions( $this->getUser(), $forum,ForumUsagePermissions::PermissionRead )) )
            return new Response('');

        $domain = $forum === null ? $this->perm->getForumsWithPermission($this->getUser()) : null;

        $search_titles = $json->get_int('opt_title', 0) > 0;
        $search_user = $json->get_int('user', 0);
        if ($search_user > 0) {
            $search_user = $this->entity_manager->getRepository(User::class)->find($search_user);
            if ($search_user === null) return new Response('');
        } else $search_user = null;

        $query_chars = str_split($json->get('query', ''));

        $q = ''; $in = $not_in = []; $qp = false; $neg = false; $esc = false;

        $commit = function() use (&$q,&$in,&$not_in,&$neg,&$esc) {
            $q = trim($q);
            if (strlen($q) >= 2) {
                if ($neg) $not_in[] = $q;
                else $in[] = $q;
            }
            $q = '';
            $neg = false; $esc = false;
        };

        foreach ($query_chars as $char) {

            if ($char == '█') continue;

            if ($char == '\\' && !$esc)
                $esc = true;
            elseif ($char == '!' && !$qp && !$esc) $neg = true;
            elseif ($char == ' ' && !$qp) $commit();
            elseif ($char == '"' && !$esc) {
                if (!empty(trim($q))) $commit();
                $qp = !$qp;
            } else {
                $q .= in_array($char, ['%','_']) ? "█$char" : $char;
                $esc = false;
            }
        }
        $commit();

        $in = array_unique($in);
        $not_in = array_unique($not_in);

        foreach ($in as $e) if (array_search($e, $not_in) !== false) {
            $in = $not_in = [];
            break;
        }

        $result = [];


        if ($search_titles && !empty($in)) {

            /** @var QueryBuilder $queryBuilder */
            $queryBuilder = $this->entity_manager->getRepository(Thread::class)->createQueryBuilder('t');

            $queryBuilder->andWhere('t.hidden = false OR t.hidden IS NULL');

            if ($search_user !== null)
                $queryBuilder->andWhere('t.owner = :user')->setParameter('user', $search_user);

            if ($forum) $queryBuilder->andWhere('t.forum = :forum')->setParameter('forum', $forum);
            else $queryBuilder->andWhere('t.forum IN (:forum)')->setParameter('forum', $domain);

            foreach ($in as $k => $entry) $queryBuilder->andWhere("t.title LIKE :in{$k} ESCAPE '█'")->setParameter("in{$k}", "%{$entry}%");
            foreach ($not_in as $k => $entry) $queryBuilder->andWhere("t.title NOT LIKE :nin{$k} ESCAPE '█'")->setParameter("nin{$k}", "%{$entry}%");

            $queryBuilder->orderBy('t.lastPost', 'DESC')->setMaxResults(26);

            foreach ($queryBuilder->getQuery()->execute() as $thread) if ($p = $thread->firstPost()) $result[] = $p;
        }

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = $this->entity_manager->getRepository(Post::class)->createQueryBuilder('p');

        $queryBuilder->andWhere('p.searchText IS NOT NULL');
        $queryBuilder->andWhere('p.hidden = false OR p.hidden IS NULL');

        if ($search_user !== null)
            $queryBuilder->andWhere('p.owner = :user')->setParameter('user', $search_user);

        if ($forum) $queryBuilder->andWhere('p.searchForum = :forum')->setParameter('forum', $forum);
        else $queryBuilder->andWhere('p.searchForum IN (:forum)')->setParameter('forum', $domain);

        foreach ($in as $k => $entry) $queryBuilder->andWhere("p.searchText LIKE :in{$k} ESCAPE '█'")->setParameter("in{$k}", "%{$entry}%");
        foreach ($not_in as $k => $entry) $queryBuilder->andWhere("p.searchText NOT LIKE :nin{$k} ESCAPE '█'")->setParameter("nin{$k}", "%{$entry}%");

        $queryBuilder->orderBy('p.date', 'DESC')->setMaxResults(26);

        $result = array_merge($result, $queryBuilder->getQuery()->execute());

        $more = count($result) > 25;
        $result = array_slice($result, 0, 25);

        foreach ($in as &$in_entry) $in_entry = str_replace('█', '', $in_entry);
        foreach ($result as $post)
            /** @var Post $post */
            $post->setHydrated( $this->html->prepareEmotes( $post->getText(), $this->getUser(), $post->getThread()->getForum()->getTown() ) );

        return $this->render( 'ajax/forum/search_result.html.twig', [
            'posts' => $result,
            'more' => $more,
            'highlight' => $in,
        ] );
    }

    public function forum_search(?Forum $default, ?string $query = null, ?int $user = null, ?bool $titles = null): Response {
        if ($user !== null)
            $user = $this->entity_manager->getRepository(User::class)->find($user);

        $forums = $this->perm->getForumsWithPermission($this->getUser());

        $forum_sections = array_unique( array_filter( array_map( fn(Forum $f) => $f->getWorldForumLanguage(), $forums ) ) );
        usort( $forum_sections, function(string $a, string $b) {
            if ($a === $b) return 0;
            if ($a === $this->getUserLanguage()) return -1;
            if ($b === $this->getUserLanguage()) return 1;
            return $a <=> $b;
        } );

        return $this->render( 'ajax/forum/search.html.twig', [
            'forums' => $forums,
            'forumSections' => $forum_sections,
            'select' => $default ? $default->getId() : -1,
            'user' => $user,
            'query' => $query,
            'titles' => (bool)$titles,
        ] );
    }

    /**
     * @Route("jx/forum/{fid<\d+>}/search", name="forum_id_search_controller")
     * @param int $fid
     * @param JSONRequestParser $json
     * @return Response
     */
    public function forum_search_id(int $fid, JSONRequestParser $json): Response {
        $forum = $this->entity_manager->getRepository(Forum::class)->find($fid);
        if (!$forum || !$this->perm->checkEffectivePermissions( $this->getUser(), $forum,ForumUsagePermissions::PermissionRead ))
            return new RedirectResponse($this->generateUrl( 'forum_all_search_controller' ));

        return $this->forum_search($forum, $json->get('query'), $json->get_int('user'), $json->get('titles'));
    }

    /**
     * @Route("jx/forum/global/search", name="forum_all_search_controller")
     * @param JSONRequestParser $json
     * @return Response
     */
    public function forum_search_all(JSONRequestParser $json): Response {
        return $this->forum_search(null, $json->get('query'), $json->get_int('user'), $json->get('titles'));
    }

    /**
     * @Route("jx/forum/search", name="forum_search_wrapper_controller")
     * @param Request $request
     * @return Response
     */
    public function forum_search_wrapper(Request $request): Response {
        $data = [
            'forum'     => $request->query->get('f', null),
            'user'      => $request->query->get('u', null),
            'query'     => $request->query->get('q', null),
            'opt_title' => $request->query->get('ot', null),
        ];

        $data['forum'] = $data['forum'] ? (int)$data['forum'] : null;
        $data['user'] = $data['user'] ? (int)$data['user'] : null;
        $data['opt_title'] = $data['opt_title'] === '1';

        return $this->render( 'ajax/forum/search_wrapper.html.twig', $data);
    }

    /**
     * @Route("jx/forum/{fid<\d+>}/{tid<\d+>}/editor", name="forum_post_editor_controller")
     * @param int $fid
     * @param int $tid
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function editor_post_api(int $fid, int $tid, EntityManagerInterface $em, JSONRequestParser $parser): Response {
        $user = $this->getUser();
        $forum = $em->getRepository(Forum::class)->find($fid);

        $thread = $em->getRepository( Thread::class )->find( $tid );
        if ($thread === null || $thread->getForum()->getId() !== $fid) return new Response('');

        $permissions = $this->perm->getEffectivePermissions( $user, $thread->getForum() );
        if (!$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionCreatePost )) {
            if (!$thread->hasReportedPosts(false) || !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate ) )
                return new Response('');
        }

        $pid = $parser->get('pid', null);
        $post = null;
        if ($pid !== null) {
            $post = $em->getRepository(Post::class)->find((int)$pid);
            if (!$post || (!$post->isEditable() && !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate )) || $post->getThread() !== $thread || (
                (($post->getOwner() !== $user && !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionModerate )) && !($this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionPostAsCrow ) && $post->getOwner()->getId() === 66) && !($this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionPostAsAnim ) && $post->getOwner()->getId() === 67))
                )) return new Response('');
        }

        $town = $forum->getTown();
        $town_citizen = $town ? $user->getCitizenFor( $town ) : null;

        $username = $town_citizen ? $town_citizen->getName() : $user->getName();
        $is_heroic = !$forum->getTown() || ( $town_citizen && $town_citizen->getAlive() && $town_citizen->getProfession()->getHeroic() );

        if ($post !== null && $thread->firstPost(true) === $post && !$thread->getTranslatable())
            $tags = ($this->userHandler->hasSkill($user, 'writer') && $is_heroic) ? array_filter( $forum->getAllowedTags()->getValues(),
                fn(ThreadTag $tag) => $tag->getPermissionMap() === null || $this->perm->isPermitted( $permissions, $tag->getPermissionMap() )
            ) : [];
        else $tags = [];

        return $this->render( 'ajax/forum/editor.html.twig', [
            'fid' => $fid,
            'tid' => $tid,
            'pid' => $pid,

            'edit_title' => ($post !== null && $post === $thread->firstPost(true) && !$thread->getTranslatable()) ? $thread->getTitle() : null,

            'permission' => $this->getPermissionObject( $permissions ),
            'snippets' => $this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionPostAsCrow ) ? $this->entity_manager->getRepository(ForumModerationSnippet::class)->findAll() : [],

            'emotes' => $this->getEmotesByUser($user,true),
            'username' => $username,
            'forum' => true,
            'town_controls' => $thread->getForum()->getTown() !== null,
            'tags' => $tags,
            'current_tag' => $thread->getTag(),
            'langsCodes' => $this->generatedLangsCodes
        ] );
    }

    /**
     * @Route("api/forum/{fid<\d+>}/mute", name="forum_mute_controller", defaults={"mute": true})
     * @Route("api/forum/{fid<\d+>}/unmute", name="forum_unmute_controller", defaults={"mute": false})
     * @param int $fid
     * @param bool $mute
     * @return Response
     */
    public function forum_mute(int $fid, bool $mute): Response {
        /** @var Forum $forum */
        $forum = $this->entity_manager->getRepository(Forum::class)->find($fid);

        $permissions = $this->perm->getEffectivePermissions( $this->getUser(), $forum );
        if (($forum->getTown() && $mute) || !$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionRead ))
            return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if ($mute) $this->getUser()->getMutedForums()->add( $forum );
        else $this->getUser()->getMutedForums()->removeElement( $forum );

        try {
            $this->entity_manager->persist($this->getUser());
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/subscribe", name="forum_thread_subscribe_controller", defaults={"subscribe": true})
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/unsubscribe", name="forum_thread_unsubscribe_controller", defaults={"subscribe": false})
     * @param int $fid
     * @param int $tid
     * @param bool $subscribe
     * @return Response
     */
    public function forum_thread_subscribe(int $fid, int $tid, bool $subscribe): Response {
        /** @var Forum $forum */
        $forum = $this->entity_manager->getRepository(Forum::class)->find($fid);
        /** @var Thread $thread */
        $thread = $this->entity_manager->getRepository(Thread::class)->find($tid);

        if ($thread->getForum() !== $forum) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($subscribe)  {
            $permissions = $this->perm->getEffectivePermissions( $this->getUser(), $thread->getForum() );
            if (!$this->perm->isPermitted( $permissions, ForumUsagePermissions::PermissionRead ))
                return AjaxResponse::error( ErrorHelper::ErrorPermissionError );
        }

        $existing = $this->entity_manager->getRepository(ForumThreadSubscription::class)->count(['user' => $this->getUser(), 'thread' => $thread]);
        if ($existing && !$subscribe) $this->entity_manager->remove($existing);
        elseif (!$existing && $subscribe) $this->entity_manager->persist((new ForumThreadSubscription())->setThread($thread)->setUser($this->getUser()));

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/forum/read_all", name="forum_all_read_controller")
     * @return Response
     */
    public function forum_mark_all_read(): Response {

        $last_post = $this->entity_manager->getRepository(Post::class)->findBy(['hidden' => false], ['id' => 'DESC'], 1);
        if (count($last_post) !== 1) return AjaxResponse::success();

        $global_marker =
            $this->entity_manager->getRepository(ThreadReadMarker::class)->findGlobalAndUser( $this->getUser() )
            ?? (new ThreadReadMarker())->setUser( $this->getUser() );

        try {
            $global_marker->setPost( $last_post[0] );
            $this->entity_manager->persist( $global_marker->setPost( $last_post[0] ) );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/moderate/{mod}", name="forum_thread_mod_controller")
     * @param int $fid
     * @param int $tid
     * @param string $mod
     * @param JSONRequestParser $parser
     * @param CrowService $crow
     * @return Response
     */
    public function mod_thread_api(int $fid, int $tid, string $mod, JSONRequestParser $parser, CrowService $crow): Response {
        $success = false;

        /** @var Forum $forum */
        $forum = $this->entity_manager->getRepository(Forum::class)->find($fid);

        /** @var Thread $thread */
        $thread = $this->entity_manager->getRepository(Thread::class)->find($tid);

        if ($thread->getForum() !== $forum) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        switch ($mod) {
            case 'lock':
                if (!$this->perm->checkEffectivePermissions($this->getUser(), $forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                $thread->setLocked(true)->setSolved(false);

                if ($thread->getOwner() !== $this->getUser()) {
                    $notification = $crow->createPM_moderation( $thread->getOwner(),
                                                                CrowService::ModerationActionDomainForum, CrowService::ModerationActionTargetThread, CrowService::ModerationActionClose,
                                                                $thread->firstPost(true), $parser->get( 'reason', '-' )
                    );
                    if ($notification) $this->entity_manager->persist($notification);
                }

                try {
                    $this->entity_manager->persist($thread);
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                } catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }
            case 'solve':
                if (!$this->perm->checkAnyEffectivePermissions($this->getUser(), $forum, [ForumUsagePermissions::PermissionModerate,ForumUsagePermissions::PermissionHelp]))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                $thread->setLocked(true)->setSolved(true);

                if ($thread->getOwner() !== $this->getUser()) {
                    $notification = $crow->createPM_moderation( $thread->getOwner(),
                                                                CrowService::ModerationActionDomainForum, CrowService::ModerationActionTargetThread, CrowService::ModerationActionSolve,
                                                                $thread->firstPost(true), $parser->get( 'reason', '-' )
                    );
                    if ($notification) $this->entity_manager->persist($notification);
                }

                try {
                    $this->entity_manager->persist($thread);
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                } catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            case 'unlock':
                if (!$this->perm->checkAnyEffectivePermissions($this->getUser(), $forum, $thread->getSolved() ? [ForumUsagePermissions::PermissionModerate,ForumUsagePermissions::PermissionHelp] : [ForumUsagePermissions::PermissionModerate]))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                $thread->setLocked(false)->setSolved(false);

                if ($thread->getOwner() !== $this->getUser()) {
                    $notification = $crow->createPM_moderation( $thread->getOwner(),
                                                                CrowService::ModerationActionDomainForum, CrowService::ModerationActionTargetThread, CrowService::ModerationActionOpen,
                                                                $thread->firstPost(true)
                    );
                    if ($notification) $this->entity_manager->persist($notification);
                }

                try {
                    $this->entity_manager->persist($thread);
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                } catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            case 'pin':
                if (!$this->perm->checkEffectivePermissions($this->getUser(), $forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                $thread->setPinned(true);
                try {
                    $this->entity_manager->persist($thread);
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                } catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            case 'unpin':
                if (!$this->perm->checkEffectivePermissions($this->getUser(), $forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                $thread->setPinned(false);
                try {
                    $this->entity_manager->persist($thread);
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                } catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            case 'delete':

                /** @var Post $post */
                $post = $this->entity_manager->getRepository(Post::class)->find((int)$parser->get('postId'));
                $reason = $parser->get( 'reason', '' );
                if (!$post) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                if (empty($reason)) $reason = "---";

                if (!$this->perm->checkEffectivePermissions($this->getUser(), $forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                if ($post->getHidden() || $post->getThread() !== $thread) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                try {
                    $post->setHidden(true);
                    $this->entity_manager->persist( $post );
                    $this->entity_manager->persist( (new AdminDeletion())
                                                        ->setSourceUser( $this->getUser() )
                                                        ->setTimestamp( new DateTime('now') )
                                                        ->setReason( $reason )
                                                        ->setPost( $post ) );
                    $reports = $post->getAdminReports(true);
                    foreach ($reports as $report)
                        $this->entity_manager->persist($report->setSeen(true));

                    if ($post === $thread->firstPost(true)) {
                        $thread->setHidden(true)->setLocked(true);
                        $this->entity_manager->persist($thread);

                        $notification = $crow->createPM_moderation( $post->getOwner(),
                            CrowService::ModerationActionDomainForum, CrowService::ModerationActionTargetThread, CrowService::ModerationActionDelete,
                            $post, $reason
                        );

                    } else {
                        $notification = $crow->createPM_moderation( $post->getOwner(),
                            CrowService::ModerationActionDomainForum, CrowService::ModerationActionTargetPost, CrowService::ModerationActionDelete,
                            $post, $reason
                        );
                    }

                    if ($notification) $this->entity_manager->persist($notification);

                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                }
                catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            case 'undelete':
                if (!$this->perm->checkEffectivePermissions($this->getUser(), $forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                /** @var Post $post */
                $post = $this->entity_manager->getRepository(Post::class)->find((int)$parser->get('postId'));
                if (!$post || !$post->getHidden() || $post->getThread() !== $thread) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                try {
                    $post->setHidden(false);
                    if ($ad = $this->entity_manager->getRepository(AdminDeletion::class)->findOneBy(['post' => $post]))
                        $this->entity_manager->remove($ad);
                    $this->entity_manager->persist( $post );
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                }
                catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            case 'seen':

                /** @var Post $post */
                $post = $this->entity_manager->getRepository(Post::class)->find((int)$parser->get('postId'));

                if (!$this->perm->checkEffectivePermissions($this->getUser(), $forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                if (!$post || $post->getAdminReports(true)->isEmpty()) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                try {
                    foreach ($post->getAdminReports(true) as $report)
                        $this->entity_manager->persist($report->setSeen(true));
                    $this->entity_manager->persist( $post );
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                }
                catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            case 'move':
                if (!$this->perm->checkEffectivePermissions($this->getUser(), $forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                if ($thread->getTranslatable()) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                $new_forum = $this->entity_manager->getRepository(Forum::class)->find( $parser->get_int('to', -1) );
                if (!$new_forum || $forum === $new_forum) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                if (!$this->perm->checkEffectivePermissions($this->getUser(), $new_forum, ForumUsagePermissions::PermissionModerate))
                    return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

                $thread->setForum( $new_forum );
                foreach ($thread->getPosts() as $post)
                    $this->entity_manager->persist( $post->setSearchForum($new_forum) );
                $notification = $crow->createPM_moderation( $thread->getOwner(),
                                                            CrowService::ModerationActionDomainForum, CrowService::ModerationActionTargetThread, CrowService::ModerationActionMove,
                                                            $thread->firstPost(true)
                );
                if ($notification) $this->entity_manager->persist($notification);

                try {
                    $this->entity_manager->persist($forum);
                    $this->entity_manager->persist($new_forum);
                    $this->entity_manager->persist($thread);
                    $this->entity_manager->flush();
                    return AjaxResponse::success();
                } catch (Exception $e) {
                    return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
                }

            default: break;
        }

        return $success ? AjaxResponse::success() : AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
    }

    /**
     * @Route("api/forum/{fid<\d+>}/{tid<\d+>}/post/report", name="forum_report_post_controller")
     * @param int $fid
     * @param int $tid
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $ti
     * @return Response
     */
    public function report_post_api(int $fid, int $tid, JSONRequestParser $parser, EntityManagerInterface $em, TranslatorInterface $ti, CrowService $crow, RateLimiterFactory $reportToModerationLimiter): Response {
        if (!$parser->has('postId'))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $user = $this->getUser();
        $postId = $parser->get('postId');

        /** @var Post $post */
        $post = $em->getRepository( Post::class )->find( $postId );
        if ($post->getTranslate() || $post->getThread()->getId() !== $tid || $post->getThread()->getForum()->getId() !== $fid) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$this->perm->checkEffectivePermissions($user, $post->getThread()->getForum(), ForumUsagePermissions::PermissionReadThreads))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $targetUser = $post->getOwner();
        if ($targetUser->getName() === "Der Rabe" ) {
            $message = $ti->trans('Das ist keine gute Idee, das ist dir doch wohl klar!', [], 'game');
            $this->addFlash('notice', $message);
            return AjaxResponse::success();
        }

        $reports = $post->getAdminReports();
        foreach ($reports as $report)
            if ($report->getSourceUser()->getId() == $user->getId())
                return AjaxResponse::success();

        if (!$reportToModerationLimiter->create( $user->getId() )->consume($reports->isEmpty() ? 2 : 1)->isAccepted())
            return AjaxResponse::error( ErrorHelper::ErrorRateLimited);

        $details = $parser->trimmed('details');
        $post->addAdminReport(
            $newReport = (new AdminReport())
                ->setSourceUser($user)
                ->setReason( $parser->get_int('reason', 0, 0, 13) )
                ->setDetails( $details ?: null )
                ->setTs(new DateTime('now'))
        );

        try {
            $em->persist($post);
            $em->persist($newReport);
            $em->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        try {
            $crow->triggerExternalModNotification( "A forum message has been reported.", $post, $newReport, "This is report #{$post->getAdminReports()->count()}." );
        } catch (\Throwable $e) {}

        $message = $ti->trans('Du hast die Nachricht von {username} dem Raben gemeldet. Wer weiß, vielleicht wird {username} heute Nacht stääärben...', ['{username}' => '<span>' . $post->getOwner()->getName() . '</span>'], 'game');
        $this->addFlash('notice', $message);
        return AjaxResponse::success( );
    }
}