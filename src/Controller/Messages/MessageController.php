<?php

namespace App\Controller\Messages;

use App\Controller\CustomAbstractController;
use App\Entity\Award;
use App\Entity\Citizen;
use App\Entity\Emotes;
use App\Entity\Forum;
use App\Entity\ForumUsagePermissions;
use App\Entity\Post;
use App\Entity\Town;
use App\Entity\User;
use App\Service\CitizenHandler;
use App\Service\ErrorHelper;
use App\Service\HTMLService;
use App\Service\InventoryHandler;
use App\Service\PermissionHandler;
use App\Service\RandomGenerator;
use App\Service\TimeKeeperService;
use App\Service\ConfMaster;
use App\Service\UserHandler;
use App\Structures\ForumPermissionAccessor;
use Doctrine\ORM\EntityManagerInterface;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @IsGranted("ROLE_USER")
 * @method User getUser
 */
class MessageController extends CustomAbstractController
{
    const ErrorForumNotFound     = ErrorHelper::BaseForumErrors + 1;
    const ErrorPostTextLength    = ErrorHelper::BaseForumErrors + 2;
    const ErrorPostTitleLength   = ErrorHelper::BaseForumErrors + 3;
    const ErrorPMItemLimitHit    = ErrorHelper::BaseForumErrors + 4;
    const ErrorForumLimitHit     = ErrorHelper::BaseForumErrors + 5;
    const ErrorGPMMemberLimitHit = ErrorHelper::BaseForumErrors + 6;
    const ErrorGPMThreadLimitHit = ErrorHelper::BaseForumErrors + 7;
    const ErrorPMItemChaosOut    = ErrorHelper::BaseForumErrors + 8;

    protected HTMLService $html;
    protected RandomGenerator $rand;
    protected Packages $asset;
    protected PermissionHandler $perm;
    protected UserHandler $userHandler;

    public function __construct(HTMLService $html, RandomGenerator $r, TranslatorInterface $t, Packages $a, EntityManagerInterface $em, InventoryHandler $ih, TimeKeeperService $tk, PermissionHandler $p, ConfMaster $conf, CitizenHandler $ch, UserHandler $uh)
    {
        parent::__construct($conf, $em, $tk, $ch, $ih, $t);
        $this->asset = $a;
        $this->rand = $r;
        $this->perm = $p;
        $this->userHandler = $uh;
        $this->html = $html;
    }

    protected function preparePost(User $user, ?Forum $forum, $post, int &$tx_len, ?Town $town = null, ?bool &$editable = null, ?array &$polls = []): bool {
        if (!$town && $forum && $forum->getTown())
            $town = $forum->getTown();

        $p = $forum ? $this->perm->getEffectivePermissions($this->getUser(), $forum) : (
            ($this->isGranted("ROLE_ADMIN")  * ForumUsagePermissions::PermissionFormattingAdmin) |
            ($this->isGranted("ROLE_CROW")   * ForumUsagePermissions::PermissionFormattingModerator) |
            (($this->isGranted("ROLE_ORACLE") || $this->isGranted("ROLE_ANIMAC")) * ForumUsagePermissions::PermissionFormattingOracle)
        );

        $tx = $post->getText();
        $this->html->htmlPrepare($user, $p, true, $tx, $town, $tx_len, $editable, $polls);

        if ($town && $user->getActiveCitizen() && $town->getCitizens()->contains($user->getActiveCitizen()) && (!is_a( $post, Post::class) || $post->getType() === 'USER')) {
            $citizen = $user->getActiveCitizen();
            $tx = $this->html->htmlDistort( $tx,
                    ($this->citizen_handler->hasStatusEffect($citizen, 'drunk') ? HTMLService::ModulationDrunk : HTMLService::ModulationNone) |
                    ($this->citizen_handler->hasStatusEffect($citizen, 'terror') ? HTMLService::ModulationTerror : HTMLService::ModulationNone) |
                    ($this->citizen_handler->hasStatusEffect($citizen, 'wound1') ? HTMLService::ModulationHead : HTMLService::ModulationNone)
                , $this->getUserLanguage(), $d );

            if ($d) $editable = false;
        }

        $post->setText($tx);

        if ($post instanceof Post) {
            $post->setSearchText( strip_tags( $tx ) );

            if ($post->getType() !== 'CROW' && $post->getType() !== 'ANIM' && $forum !== null && $forum->getTown()){
                $citizen = $user->getActiveCitizen();
                if ($citizen && $citizen->getTown() === $forum->getTown()) {

                    if ($citizen->getZone() && ($citizen->getZone()->getX() !== 0 || $citizen->getZone()->getY() !== 0))  {
                        if($citizen->getTown()->getChaos()){
                            $note = $this->translator->trans('Draußen', [], 'game');
                        } else {
                            $note = "[{$citizen->getZone()->getX()}, {$citizen->getZone()->getY()}]";
                        }
                    }
                    else {
                        $note = '{at_00}';
                    }

                    $post->setNote("<img alt='' src='{$this->asset->getUrl("build/images/professions/{$citizen->getProfession()->getIcon()}.gif")}' /> <img alt='' src='{$this->asset->getUrl('build/images/icons/item_map.gif')}' /> <span>$note</span>");
                }
            }
        } elseif (!empty($polls)) return false;

        return true;
    }

    protected function getEmotesByUser(User $user, bool $url_only = false): array {
        $repo = $this->entity_manager->getRepository(Emotes::class);
        $emotes = $repo->getDefaultEmotes();
        $awards = $this->entity_manager->getRepository(Award::class)->getAwardsByUser($user);
        $results = array();

        foreach($awards as $entry) {
            /** @var $entry Award */
            if ($entry->getPrototype()->getAssociatedTag() === null) continue;
            $emote = $repo->findByTag($entry->getPrototype()->getAssociatedTag());
            if(!in_array($emote, $emotes)) {
                $emotes[] = $emote;
            }
        }

        foreach($emotes as $entry) {
            /** @var $entry Emotes */
            if ($entry === null) continue;
            $results[$entry->getTag()] = $url_only ? $entry->getPath() : "<img alt='{$entry->getTag()}' src='{$this->asset->getUrl( $entry->getPath() )}'/>";
        }
        return $results;
    }

    protected function getPermissionObject($forumOrPermission = null): ForumPermissionAccessor {
        $p = 0;
        if (is_int($forumOrPermission)) $p = $forumOrPermission;
        elseif (is_a($forumOrPermission, Forum::class)) $p = $this->perm->getEffectivePermissions($this->getUser(), $forumOrPermission);

        return new ForumPermissionAccessor($p, $this->perm);
    }

    /**
     * @Route("jx/admin/numb/editor", name="admin_numb_editor")
     * @return Response
     */
    public function admin_numb_editor(): Response {
        $user = $this->getUser();

        return $this->render( 'ajax/forum/editor.html.twig', [
            'fid' => null,
            'tid' => null,
            'pid' => null,

            'permission' => $this->getPermissionObject( ForumUsagePermissions::PermissionOwn ),
            'snippets' => [],
            'emotes' => $this->getEmotesByUser($user,true),

            'forum' => false,
            'type' => 'numb',
            'username' => $user->getName(),
            'target_url' => '',
            'town_controls' => false
        ] );
    }
}
