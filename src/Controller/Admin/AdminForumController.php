<?php

namespace App\Controller\Admin;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Entity\AdminReport;
use App\Entity\ForumModerationSnippet;
use App\Entity\ForumUsagePermissions;
use App\Entity\GlobalPrivateMessage;
use App\Entity\Post;
use App\Entity\PrivateMessage;
use App\Entity\ReportSeenMarker;
use App\Entity\User;
use App\Response\AjaxResponse;
use App\Service\AdminActionHandler;
use App\Service\CrowService;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\PermissionHandler;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @method User getUser
 * @GateKeeperProfile(allow_during_attack=true)
 */
class AdminForumController extends AdminActionController
{
    /**
     * @Route("jx/admin/forum/report/pm", name="admin_pm_viewer")
     * @param JSONRequestParser $parser
     * @param PermissionHandler $perm
     * @return Response
     */
    public function render_pm(JSONRequestParser $parser, PermissionHandler $perm) {
        $user = $this->getUser();

        $pmid = $parser->get('pmid', null);
        if ($pmid === null) return new Response();

        /** @var PrivateMessage $pm */
        if (!($pm = $this->entity_manager->getRepository(PrivateMessage::class)->find($pmid)))
            return new Response();

        if ($pm->getAdminReports(false)->isEmpty())  return new Response();

        $thread = $pm->getPrivateMessageThread();
        if (!$thread || !$thread->getSender() || !$thread->getSender()->getTown()->getForum()) return new Response();

        if (!$perm->checkAnyEffectivePermissions($user, $thread->getSender()->getTown()->getForum(), [ForumUsagePermissions::PermissionModerate]))
            return new Response();

        $posts = $thread->getMessages();

        return $this->render( 'ajax/admin/reports/pn-viewer.html.twig', $this->addDefaultTwigArgs(null, [
            'thread' => $thread,
            'posts' => $posts,
            'markedPost' => $pmid,
            'emotes' => []
        ] ));
    }

    /**
     * @Route("jx/admin/forum/report/gpm", name="admin_gpm_viewer")
     * @param JSONRequestParser $parser
     * @param PermissionHandler $perm
     * @return Response
     */
    public function render_gpm(JSONRequestParser $parser) {
        $user = $this->getUser();

        $pmid = $parser->get('pmid', null);
        if ($pmid === null) return new Response();

        /** @var GlobalPrivateMessage $message */
        if (!($message = $this->entity_manager->getRepository(GlobalPrivateMessage::class)->find($pmid)))
            return new Response();

        if ($message->getAdminReports(false)->isEmpty()) return new Response();

        $group = $message->getReceiverGroup();
        if (!$group || !$message->getSender()) return new Response();

        $posts = $this->entity_manager->getRepository(GlobalPrivateMessage::class)->findByGroup( $group, 0, 15, $message->getId() );

        return $this->render( 'ajax/admin/reports/gpn-viewer.html.twig', $this->addDefaultTwigArgs(null, [
            'group' => $group,
            'posts' => $posts,
            'markedPost' => $pmid,
            'emotes' => []
        ] ));
    }

    /**
     * @Route("api/admin/forum/reports/clear", name="admin_reports_clear")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @param AdminActionHandler $admh
     * @return Response
     */
    public function reports_clear(JSONRequestParser $parser, AdminActionHandler $admh): Response
    {
        if (!$parser->has_all(['postId'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $user = $this->getUser();
        $postId = $parser->get('postId');
        if ($admh->clearReports($user->getId(), $postId)){
            $this->logger->invoke("Admin <info>{$this->getUser()->getName()}</info> cleared reports");
            return AjaxResponse::success();
        }
        return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
    }

    /**
     * @Route("api/admin/forum/reports/seen", name="admin_reports_seen")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @param AdminActionHandler $admh
     * @return Response
     */
    public function reports_seen(JSONRequestParser $parser, AdminActionHandler $admh): Response
    {
        if (!$parser->has_all(['postId'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $user = $this->getUser();
        $post = $this->entity_manager->getRepository(Post::class)->find($parser->get('postId'));
        $reports = $post->getAdminReports();

        try
        {
            foreach ($reports as $report) {
                $existing_seen = $this->entity_manager->getRepository(ReportSeenMarker::class)->findOneBy(['user' => $user, 'report' => $report]);
                if (!$existing_seen) $this->entity_manager->persist( (new ReportSeenMarker())->setUser($user)->setReport($report) );
            }
            $this->entity_manager->flush();
        }
        catch (\Throwable $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/forum/reports/moderate-pm", name="admin_reports_mod_pm")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @param PermissionHandler $perm
     * @param CrowService $crow
     * @return Response
     */
    public function reports_moderate_pm(JSONRequestParser $parser, PermissionHandler $perm, CrowService $crow): Response
    {
        $user = $this->getUser();

        $pmid = $parser->get('pmid', null);
        if ($pmid === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var PrivateMessage $pm */
        if (!($pm = $this->entity_manager->getRepository(PrivateMessage::class)->find($pmid)))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $has_report = false;
        foreach ($pm->getPrivateMessageThread()->getMessages() as $ppm)
        if (!$ppm->getAdminReports(true)->isEmpty()) $has_report = true;

        if (!$has_report) return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $thread = $pm->getPrivateMessageThread();
        if (!$thread || !$thread->getSender() || !$thread->getSender()->getTown()->getForum()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$perm->checkAnyEffectivePermissions($user, $thread->getSender()->getTown()->getForum(), [ForumUsagePermissions::PermissionModerate]))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $seen = (bool)$parser->get('seen', false);
        $hide = (bool)$parser->get('hide', false);
        $message = $parser->get('message', null);

        if (!$seen && !$hide && !$message) return AjaxResponse::success();

        if ($seen)
            foreach ($pm->getAdminReports(true) as $report)
                $this->entity_manager->persist($report->setSeen(true));

        if ($hide) $pm->setHidden(true);
        if ($message) $pm->setModMessage( $message );
        $this->entity_manager->persist($pm->setModerator($user));

        if ($hide || $message) {
            $notification = $crow->createPM_moderation( $pm->getOwner()->getUser(),
                CrowService::ModerationActionDomainTownPM, CrowService::ModerationActionTargetPost, $hide ? CrowService::ModerationActionDelete : CrowService::ModerationActionEdit,
                $pm, $message ?? ''
            );
            if ($notification) $this->entity_manager->persist($notification);
        }

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/forum/reports/seen-pm", name="admin_reports_seen_pm")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @param PermissionHandler $perm
     * @param CrowService $crow
     * @return Response
     */
    public function reports_seen_pm(JSONRequestParser $parser, PermissionHandler $perm, CrowService $crow): Response
    {
        $user = $this->getUser();

        $pmid = $parser->get('pmid', null);
        if ($pmid === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var PrivateMessage $pm */
        if (!($pm = $this->entity_manager->getRepository(PrivateMessage::class)->find($pmid)))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach ($pm->getAdminReports() as $report) {
            $existing_seen = $this->entity_manager->getRepository(ReportSeenMarker::class)->findOneBy(['user' => $user, 'report' => $report]);
            if (!$existing_seen) $this->entity_manager->persist( (new ReportSeenMarker())->setUser($user)->setReport($report) );
        }

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/forum/reports/moderate-gpm", name="admin_reports_mod_gpm")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @param CrowService $crow
     * @return Response
     */
    public function reports_moderate_gpm(JSONRequestParser $parser, CrowService $crow): Response
    {
        $user = $this->getUser();

        $pmid = $parser->get('pmid', null);
        if ($pmid === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var GlobalPrivateMessage $pm */
        if (!($pm = $this->entity_manager->getRepository(GlobalPrivateMessage::class)->find($pmid)))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$pm->getReceiverGroup()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $has_report = false;
        foreach ($this->entity_manager->getRepository(GlobalPrivateMessage::class)->findBy(['receiverGroup' => $pm->getReceiverGroup()]) as $ppm)
            if (!$ppm->getAdminReports(true)->isEmpty()) $has_report = true;

        if (!$has_report) return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $seen = (bool)$parser->get('seen', false);
        $hide = (bool)$parser->get('hide', false);
        $message = $parser->get('message', null);

        if (!$seen && !$hide && !$message) return AjaxResponse::success();

        if ($seen)
            foreach ($pm->getAdminReports(true) as $report)
                $this->entity_manager->persist($report->setSeen(true));

        if ($hide) $pm->setHidden(true)->setPinned(false)->setCollapsed(false);
        if ($message) $pm->setModMessage( $message );
        $this->entity_manager->persist($pm->setModerator($user));

        if ($hide || $message) {
            $notification = $crow->createPM_moderation( $pm->getSender(),
                CrowService::ModerationActionDomainGlobalPM, CrowService::ModerationActionTargetPost, $hide ? CrowService::ModerationActionDelete : CrowService::ModerationActionEdit,
                $pm, $message ?? ''
            );
            if ($notification) $this->entity_manager->persist($notification);
        }

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/forum/reports/seen-gpm", name="admin_reports_seen_gpm")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @param CrowService $crow
     * @return Response
     */
    public function reports_seen_gpm(JSONRequestParser $parser, CrowService $crow): Response
    {
        $user = $this->getUser();

        $pmid = $parser->get('pmid', null);
        if ($pmid === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var GlobalPrivateMessage $pm */
        if (!($pm = $this->entity_manager->getRepository(GlobalPrivateMessage::class)->find($pmid)))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$pm->getReceiverGroup()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach ($pm->getAdminReports() as $report) {
            $existing_seen = $this->entity_manager->getRepository(ReportSeenMarker::class)->findOneBy(['user' => $user, 'report' => $report]);
            if (!$existing_seen) $this->entity_manager->persist( (new ReportSeenMarker())->setUser($user)->setReport($report) );
        }

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/forum/reports/snippet/add", name="admin_reports_add_snippet")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function add_snippet(JSONRequestParser $parser): Response {

        if (!$parser->has_all(['id','lang','content'],true)) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $lang = strtolower( $parser->trimmed('lang') );

        if (!in_array($lang, ['de','en','fr','es'])) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($this->entity_manager->getRepository(ForumModerationSnippet::class)->findOneBy(['id' => $parser->trimmed('id'), 'lang' => $lang]))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );



        $this->entity_manager->persist( (new ForumModerationSnippet)
            ->setShort( $parser->trimmed('id') )
            ->setLang( $lang )
            ->setText( $parser->trimmed( 'content' ) )
        );

        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/forum/reports/snippet/remove/{id<\d+>}", name="admin_reports_remove_snippet")
     * @AdminLogProfile(enabled=true)
     * @param int $id
     * @return Response
     */
    public function remove_snippet(int $id): Response {


        $snippet = $this->entity_manager->getRepository(ForumModerationSnippet::class)->find($id);

        if ($snippet) {
            $this->entity_manager->remove($snippet);
            try {
                $this->entity_manager->flush();
            } catch (\Exception $e) {
                return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
            }

            return AjaxResponse::success();
        } else return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
    }

    /**
     * @Route("jx/admin/forum/{tab}/{opt}", name="admin_reports")
     * @param PermissionHandler $perm
     * @param string $tab
     * @param string $opt
     * @return Response
     */
    public function reports(PermissionHandler $perm, string $tab = 'reports', string $opt = ''): Response
    {
        $show_bin = $opt === 'bin';
        $show_all = $show_bin || $opt === 'all';

        $allowed_forums = [];

        $reports = $this->entity_manager->getRepository(AdminReport::class)->findBy(['seen' => $show_bin]);

        $forum_reports = array_filter($reports, function(AdminReport $r) use (&$allowed_forums, $perm) {
            if ($r->getPost() === null) return false;
            $tid = $r->getPost()->getThread()->getForum()->getId();
            if (isset($allowed_forums[$tid])) return $allowed_forums[$tid];
            else return $allowed_forums[$tid] = $perm->checkAnyEffectivePermissions($this->getUser(), $r->getPost()->getThread()->getForum(), [ForumUsagePermissions::PermissionReadThreads, ForumUsagePermissions::PermissionModerate]);
        });

        // Make sure to fetch only unseen reports for posts with at least 2 unseen reports
        $postsList = [
            'post' => array_map(fn(AdminReport $report) => $report->getPost(), $forum_reports),
            'reporter' => array_map(fn(AdminReport $report) => $report->getSourceUser(), $forum_reports)
        ];

        $alreadyCountedIndexes = [];
        $selectedReports = [];
        foreach ($postsList['post'] as $idx => $post) {
            if (in_array($idx, $alreadyCountedIndexes))
                continue;
            $keys = array_keys($postsList['post'], $post);
            $alreadyCountedIndexes = array_merge($alreadyCountedIndexes, $keys);
            $reportCount = count($keys);
            $seenCount = array_reduce($keys, fn(int $i, $key) => $i + ( $this->entity_manager->getRepository(ReportSeenMarker::class)->findOneBy(['user' => $this->getUser(), 'report' => $forum_reports[$key]]) ? 1 : 0 ), 0);
            if ($show_all || ($reportCount > 1 && $reportCount > $seenCount)) {
                $reporters = [];
                foreach ($keys as $key){
                    $reporters[] = $postsList['reporter'][$key];
                }
                $selectedReports[] = array('post' => $post, 'count' => $reportCount, 'reporters' => $reporters);
            }
        }

        /** @var AdminReport[] $pm_reports */
        $pm_reports = array_filter($reports, function(AdminReport $r) use (&$allowed_forums, $perm) {
            if ($r->getPm() === null || $r->getPm()->getOwner() === null || $r->getPm()->getOwner()->getTown()->getForum() === null) return false;
            $tid = $r->getPm()->getOwner()->getTown()->getForum()->getId();
            if (isset($allowed_forums[$tid])) return $allowed_forums[$tid];
            else return $allowed_forums[$tid] = $perm->checkAnyEffectivePermissions($this->getUser(), $r->getPm()->getOwner()->getTown()->getForum(), [ForumUsagePermissions::PermissionModerate]);
        });

        $pm_cache = [];
        foreach ($pm_reports as $report) {
            $seen = $this->entity_manager->getRepository(ReportSeenMarker::class)->findOneBy(['user' => $this->getUser(), 'report' => $report]) !== null;
            if (!isset($pm_cache[$report->getPm()->getId()]))
                $pm_cache[$report->getPm()->getId()] = [
                    'post' => $report->getPm(), 'count' => 1, 'seen' => $seen ? 1 : 0, 'reporters' => [ $report->getSourceUser() ]
                ];
            else {
                $pm_cache[$report->getPm()->getId()]['count']++;
                $pm_cache[$report->getPm()->getId()]['seen'] += $seen ? 1 : 0;
                $pm_cache[$report->getPm()->getId()]['reporters'][] = $report->getSourceUser();
            }
        }

        /** @var AdminReport[] $pm_reports */
        $gpm_reports = array_filter($reports, function(AdminReport $r) {
            if ($r->getGpm() === null || $r->getGpm()->getReceiverGroup() === null || $r->getGpm()->getSender() === null) return false;
            return true;
        });

        $gpm_cache = [];
        foreach ($gpm_reports as $report) {
            $seen = $this->entity_manager->getRepository(ReportSeenMarker::class)->findOneBy(['user' => $this->getUser(), 'report' => $report]) !== null;
            if (!isset($gpm_cache[$report->getGpm()->getId()]))
                $gpm_cache[$report->getGpm()->getId()] = [
                    'post' => $report->getGpm(), 'count' => 1, 'seen' => $seen ? 1 : 0, 'reporters' => [ $report->getSourceUser() ]
                ];
            else {
                $gpm_cache[$report->getGpm()->getId()]['count']++;
                $gpm_cache[$report->getGpm()->getId()]['seen'] += $seen ? 1 : 0;
                $gpm_cache[$report->getGpm()->getId()]['reporters'][] = $report->getSourceUser();
            }
        }

        if (!$show_all) {
            $pm_cache = array_filter( $pm_cache, fn($e) => $e['count'] > $e['seen'] );
            $gpm_cache = array_filter( $gpm_cache, fn($e) => $e['count'] > $e['seen'] );
        }

        return $this->render( 'ajax/admin/reports/reports.html.twig', $this->addDefaultTwigArgs(null, [
            'tab' => $tab,

            'posts' => $selectedReports,
            'pms'  => $pm_cache,
            'gpms' => $gpm_cache,

            'opt' => $opt,
            'all_shown' => $show_all,
            'bin_shown' => $show_bin,

            'snippets' => $this->entity_manager->getRepository(ForumModerationSnippet::class)->findAll()
        ]));
    }
}
