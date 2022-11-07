<?php

namespace App\Controller\Soul;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractController;
use App\Entity\AccountRestriction;
use App\Entity\AdminReport;
use App\Entity\Announcement;
use App\Entity\AntiSpamDomains;
use App\Entity\Award;
use App\Entity\CauseOfDeath;
use App\Entity\Changelog;
use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\ExternalApp;
use App\Entity\FeatureUnlock;
use App\Entity\FeatureUnlockPrototype;
use App\Entity\ForumPollAnswer;
use App\Entity\FoundRolePlayText;
use App\Entity\GlobalPoll;
use App\Entity\HeroSkillPrototype;
use App\Entity\OfficialGroup;
use App\Entity\Picto;
use App\Entity\PictoPrototype;
use App\Entity\RememberMeTokens;
use App\Entity\ShoutboxEntry;
use App\Entity\ShoutboxReadMarker;
use App\Entity\SocialRelation;
use App\Entity\TownClass;
use App\Entity\TownRankingProxy;
use App\Entity\TwinoidImport;
use App\Entity\User;
use App\Entity\RolePlayTextPage;
use App\Entity\Season;
use App\Entity\UserDescription;
use App\Entity\UserGroupAssociation;
use App\Entity\UserPendingValidation;
use App\Entity\UserReferLink;
use App\Entity\UserSponsorship;
use App\Enum\AdminReportSpecification;
use App\Enum\DomainBlacklistType;
use App\Enum\UserSetting;
use App\Response\AjaxResponse;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\ErrorHelper;
use App\Service\EternalTwinHandler;
use App\Service\HTMLService;
use App\Service\JSONRequestParser;
use App\Service\RandomGenerator;
use App\Service\RateLimitingFactoryProvider;
use App\Service\UserFactory;
use App\Service\UserHandler;
use App\Service\AdminHandler;
use App\Service\CitizenHandler;
use App\Service\InventoryHandler;
use App\Service\TimeKeeperService;
use App\Structures\MyHordesConf;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\Validation;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @method User getUser
 */
class SoulController extends CustomAbstractController
{
    const ErrorUserEditPasswordIncorrect     = ErrorHelper::BaseSoulErrors + 1;
    const ErrorTwinImportInvalidResponse     = ErrorHelper::BaseSoulErrors + 2;
    const ErrorTwinImportNoToken             = ErrorHelper::BaseSoulErrors + 3;
    const ErrorTwinImportProfileMismatch     = ErrorHelper::BaseSoulErrors + 4;
    const ErrorTwinImportProfileInUse        = ErrorHelper::BaseSoulErrors + 5;
    const ErrorETwinImportProfileInUse       = ErrorHelper::BaseSoulErrors + 6;
    const ErrorETwinImportServerCrash        = ErrorHelper::BaseSoulErrors + 7;
    const ErrorUserEditUserName              = ErrorHelper::BaseSoulErrors + 8;
    const ErrorUserEditTooSoon               = ErrorHelper::BaseSoulErrors + 9;
    const ErrorUserUseEternalTwin            = ErrorHelper::BaseSoulErrors + 10;
    const ErrorUserConfirmToken              = ErrorHelper::BaseSoulErrors + 11;
    const ErrorUserEditUserNameTooLong       = ErrorHelper::BaseSoulErrors + 12;

    const ErrorCoalitionAlreadyMember        = ErrorHelper::BaseSoulErrors + 20;
    const ErrorCoalitionNotSet               = ErrorHelper::BaseSoulErrors + 21;
    const ErrorCoalitionUserAlreadyMember    = ErrorHelper::BaseSoulErrors + 22;
    const ErrorCoalitionFull                 = ErrorHelper::BaseSoulErrors + 23;


    protected UserFactory $user_factory;
    protected UserHandler $user_handler;
    protected KernelInterface $kernel;
    protected Packages $asset;
    protected CrowService $crow;

    public function __construct(EntityManagerInterface $em, UserFactory $uf, Packages $a, UserHandler $uh, TimeKeeperService $tk, TranslatorInterface $translator, ConfMaster $conf, CitizenHandler $ch, InventoryHandler $ih, KernelInterface $kernel, CrowService $crow)
    {
        parent::__construct($conf, $em, $tk, $ch, $ih, $translator);
        $this->user_factory = $uf;
        $this->asset = $a;
        $this->user_handler = $uh;
        $this->kernel = $kernel;
        $this->crow = $crow;
    }

    protected function addDefaultTwigArgs(?string $section = null, ?array $data = null ): array {
        $data = parent::addDefaultTwigArgs($section, $data);

        $user = $this->getUser();

        $data = $data ?? [];

        $user_coalition = $this->entity_manager->getRepository(UserGroupAssociation::class)->findOneBy( [
            'user' => $user,
            'associationType' => [UserGroupAssociation::GroupAssociationTypeCoalitionMember, UserGroupAssociation::GroupAssociationTypeCoalitionMemberInactive]
        ]);

        $user_invitations = $user_coalition ? [] : $this->entity_manager->getRepository(UserGroupAssociation::class)->findBy( [
            'user' => $user,
            'associationType' => UserGroupAssociation::GroupAssociationTypeCoalitionInvitation ]
        );

        $sb = $this->user_handler->getShoutbox($user);
        $messages = false;
        if ($sb) {
            $last_entry = $this->entity_manager->getRepository(ShoutboxEntry::class)->findOneBy(['shoutbox' => $sb], ['timestamp' => 'DESC', 'id' => 'DESC']);
            if ($last_entry) {
                $marker = $this->entity_manager->getRepository(ShoutboxReadMarker::class)->findOneBy(['user' => $user]);
                if (!$marker || $last_entry !== $marker->getEntry()) $messages = true;
            }
        }

        $data["soul_tab"] = $section;
        $data["new_message"] = !empty($user_invitations) || $messages;
        $data["new_news"] = $this->entity_manager->getRepository(Announcement::class)->countUnreadByUser($user, $this->getUserLanguage()) > 0;
        $data["season"] = $this->entity_manager->getRepository(Season::class)->findOneBy(['current' => true]);
        return $data;
    }

    /**
     * @Route("jx/soul/disabled_message", name="soul_disabled")
     * @return Response
     */
    public function soul_disabled(): Response
    {
        $user = $this->getUser();
        if (!$this->user_handler->isRestricted( $user, AccountRestriction::RestrictionGameplay ))
            return $this->redirect($this->generateUrl( 'soul_me' ));

        $largest = null;

        /** @var AccountRestriction[] $restrictions */
        $restrictions = $this->entity_manager->getRepository(AccountRestriction::class)->findBy(['user' => $user, 'active' => true, 'confirmed' => true]);
        foreach ($restrictions as $restriction) {
            if (($restriction->getRestriction() & AccountRestriction::RestrictionGameplay) === AccountRestriction::RestrictionGameplay) {
                if ($largest === null || $restriction->getExpires() === null || ($largest->getExpires() !== null && $restriction->getExpires() > $largest->getExpires()))
                    $largest = $restriction;
            }
        }

        return $this->render( 'ajax/soul/acc_disabled.html.twig', $this->addDefaultTwigArgs(null, ['restriction' => $largest]));
    }

    /**
     * @Route("jx/soul/me", name="soul_me")
     * @param HTMLService $html
     * @return Response
     */
    public function soul_me(HTMLService $html): Response
    {
        $user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        // Get all the picto & count points
        $pictos = $this->entity_manager->getRepository(Picto::class)->findNotPendingByUser($user);
        $top3 = array_slice($pictos, 0, 3);
    	$points = $this->user_handler->getPoints($user);
        $latestSkill = $this->entity_manager->getRepository(HeroSkillPrototype::class)->getLatestUnlocked($user->getAllHeroDaysSpent());
        $nextSkill = $this->entity_manager->getRepository(HeroSkillPrototype::class)->getNextUnlockable($user->getAllHeroDaysSpent());

        $factor1 = $latestSkill !== null ? $latestSkill->getDaysNeeded() : 0;

        $progress = $nextSkill !== null ? ($user->getAllHeroDaysSpent() - $factor1) / ($nextSkill->getDaysNeeded() - $factor1) * 100.0 : 0;

        $desc = $this->entity_manager->getRepository(UserDescription::class)->findOneBy(['user' => $user]);

        $features = [];
        $season = $this->entity_manager->getRepository(Season::class)->findOneBy(['current' => true]);
        foreach ($this->entity_manager->getRepository(FeatureUnlockPrototype::class)->findAll() as $p)
            if ($ff = $this->entity_manager->getRepository(FeatureUnlock::class)->findOneActiveForUser($user,$season,$p))
                $features[] = $ff;

        return $this->render( 'ajax/soul/me.html.twig', $this->addDefaultTwigArgs("soul_me", [
            'user' => $user,
            'pictos' => $pictos,
            'top3' => $top3,
            'features' => $features,
            'points' => round($points),
            'latestSkill' => $latestSkill,
            'progress' => floor($progress),
            'seasons' => $this->entity_manager->getRepository(Season::class)->findPastAndPresent(),
            'user_desc' => $desc ? $html->prepareEmotes($desc->getText(), $this->getUser()) : null
        ]));
    }

    /**
     * @Route("jx/soul/pictos/{id}", name="soul_pictos_all", requirements={"id"="\d+"})
     * @param int $id
     * @return Response
     */
    public function soul_pictos_all(int $id): Response
    {
        $current_user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($current_user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        /** @var User $user */
        $user = $this->entity_manager->getRepository(User::class)->find($id);
        if($user === null)
            return $this->redirect($this->generateUrl('soul_me'));

        // Get all the picto & count points
        $pictos_mh = $this->entity_manager->getRepository(Picto::class)->findNotPendingByUser($user, false);
        $pictos_im = $this->entity_manager->getRepository(Picto::class)->findNotPendingByUser($user, true);
        $pictos_old = $this->entity_manager->getRepository(Picto::class)->findOldByUser($user);

        $points_mh = $this->user_handler->getPoints($user, false);
        $points_im = $this->user_handler->getPoints($user, true);
        $points_old = $this->user_handler->getPoints($user, null, true);

        return $this->render( 'ajax/soul/pictos.html.twig', $this->addDefaultTwigArgs(null, [
            'user' => $user,
            'pictos_mh' => $pictos_mh,
            'pictos_import' => $pictos_im,
            'pictos_old' => $pictos_old,
            'points_mh' => round($points_mh),
            'points_import' => round($points_im),
            'points_old' => round($points_old),
        ]));
    }

    /**
     * @Route("jx/soul/refer", name="soul_refer")
     * @Route("jx/soul/contacts/{opt}", name="soul_contacts", requirements={"opt"="\d"})
     * @param Request $request
     * @param ConfMaster $conf
     * @param int $opt
     * @return Response
     */
    public function soul_refer(Request $request, ConfMaster $conf, int $opt = 0): Response {
        $refer = $this->entity_manager->getRepository(UserReferLink::class)->findOneBy(['user' => $this->getUser()]);
        if ($refer === null && !$this->user_handler->hasRole($this->getUser(), 'ROLE_DUMMY')) {

            $name_base = strtolower($this->getUser()->getUsername());

            $refer = (new UserReferLink)->setUser($this->getUser())->setActive(true)->setName($name_base);
            $n = 2;

            while ($this->entity_manager->getRepository(UserReferLink::class)->findOneBy(['name' => $refer->getName()]) && $n <= 999)
                $refer->setName( sprintf("%s.%03u", $name_base, $n++) );

            if ($n > 999) $refer = null;
            else try {
                $this->entity_manager->persist($refer);
                $this->entity_manager->flush();
            } catch (Exception $e) {
                $refer = null;
            }
        }

        $sponsored = array_filter(
            $this->entity_manager->getRepository(UserSponsorship::class)->findBy(['sponsor' => $this->getUser()]),
            fn(UserSponsorship $s) => !$this->user_handler->hasRole($s->getUser(), 'ROLE_DUMMY') && $s->getUser()->getValidated()
        );

        /** @var UserGroupAssociation|null $user_coalition */
        $user_coalition = $this->user_handler->getCoalitionMembership($this->getUser());

        $coa_full = false;

        $coa_members = [];

        if ($user_coalition) {
            $all_users = $this->entity_manager->getRepository(UserGroupAssociation::class)->findBy( [
                'association' => $user_coalition->getAssociation(),
                'associationType' => [UserGroupAssociation::GroupAssociationTypeCoalitionMember, UserGroupAssociation::GroupAssociationTypeCoalitionMemberInactive, UserGroupAssociation::GroupAssociationTypeCoalitionInvitation] ]
            );

            foreach ($all_users as $coa_user)
                $coa_members[$coa_user->getUser()->getId()] = $coa_user->getAssociationType();

            $coa_full = count($all_users) >= $conf->getGlobalConf()->get(MyHordesConf::CONF_COA_MAX_NUM, 5);
        }

        $reverse_friends = $this->entity_manager->getRepository(User::class)->findInverseFriends($this->getUser(), true);
        $all_reverse_friends = count($reverse_friends);
        if ($opt !== 1) $reverse_friends = array_filter( $reverse_friends, fn(User $friend) => !$this->user_handler->checkRelation( $this->getUser(), $friend, SocialRelation::SocialRelationTypeNotInterested ) );

        return $this->render( 'ajax/soul/social.html.twig', $this->addDefaultTwigArgs("soul_refer", [
            'tab' => $request->attributes->get('_route') === 'soul_refer' ? 'refer' : 'friends',

            'refer' => $refer,
            'sponsored' => $sponsored,
            'lang' => $this->getUserLanguage(),

            'friends' => $this->getUser()->getFriends(),
            'reverse_friends' => $reverse_friends,
            'reverse_friends_hidden' => count($reverse_friends) < $all_reverse_friends,
            'opt' => $opt,

            'blocklist' => $this->entity_manager->getRepository( SocialRelation::class )->findBy( ['owner' => $this->getUser(), 'type' => SocialRelation::SocialRelationTypeBlock ] ),

            'coa' => $user_coalition,
            'coa_leader' => $user_coalition && $user_coalition->getAssociationLevel() === UserGroupAssociation::GroupAssociationLevelFounder,
            'coa_full' => $coa_full,
            'coa_members' => $coa_members]));
    }

    /**
     * @Route("jx/soul/fuzzyfind/{url}", name="users_fuzzyfind")
     * @GateKeeperProfile(allow_during_attack=true,record_user_activity=false)
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @param string $url
     * @return Response
     */
    public function users_fuzzyfind(JSONRequestParser $parser, EntityManagerInterface $em, $url = 'soul_visit'): Response
    {
        $user = $this->getUser();

        if (!$parser->has_all(['name'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        $searchName = $parser->get('name', '');
        $searchSkip = $parser->get_array('exclude', []);
        // if ($url !== 'pm_manage_users' && $url !== 'plain') $searchSkip[] = $user->getId();

        if ($url === 'soul_invite_friend')
            $searchSkip = array_merge($searchSkip, $user->getFriends()->getValues(), [$user]);

        $selected_group = false;
        if ($url === 'town_add_users' && str_contains($searchName,',')) {
            $searchNames = explode(',', $searchName);
            $users = [];
            foreach ($searchNames as $searchName) {
                $r = mb_strlen($searchName) >= 3 ? $em->getRepository(User::class)->findOneByNameOrDisplayName(trim($searchName)) : null;
                if ($r && !in_array($r->getId(), $searchSkip)) $users[] = $r;
            }
            $selected_group = true;
        } else $users = mb_strlen($searchName) >= 3 ? $em->getRepository(User::class)->findBySoulSearchQuery($searchName, -1, $searchSkip) : [];

        $data = [
            'var' => $url,
            'single_entry' => $selected_group,
            'users' => in_array($url, ['soul_visit','soul_invite_coalition','soul_invite_friend','pm_manage_users','pm_add_users','town_add_users','plain','post_add_users']) ? $users : [],
            'route' => in_array($url, ['soul_visit','soul_invite_coalition']) ? $url : ''
        ];

        if ($url === 'pm_add_users') $data['gid'] = $parser->get_int('group', 0);

        return $this->render( 'ajax/soul/users_list.html.twig', $data);
    }

    /**
     * @Route("jx/soul/exists", name="user_exists")
     * @GateKeeperProfile(allow_during_attack=true,record_user_activity=false)
     */
    public function users_exists(JSONRequestParser $parser, TranslatorInterface $translator): Response {
        $return = [];

        $fixed_account_translators = [
            66 => 'Der Rabe',
            67 => 'Animateur-Team',
        ];

        $add = function(?User $u, string $name, int $id) use (&$return, &$translator, &$fixed_account_translators) {
            $name_fixed = ($fixed_account_translators[$u?->getId() ?? -1] ?? null)
                ? $translator->trans($fixed_account_translators[$u?->getId() ?? -1], [], 'global')
                : null;

            $return[] =
                [
                    'exists' => $u !== null ? 1 : 0,
                    'id' => $u?->getId() ?? $id,
                    'displayName' => $name_fixed ?? $u?->getName() ?? $name,
                    'queryName' => $name
                ];
        };

        $missing_name = $this->translator->trans( 'Specify using player search', [], 'soul' );

        $addMultiple = function(int $count, string $name) use (&$return, $missing_name) {
            if ($count > 1)
                $return[] =
                    [
                        'exists' => $count,
                        'id' => -1,
                        'displayName' => $missing_name,
                        'queryName' => $name
                    ];
        };

        foreach ( array_slice( $parser->get_array( 'names', [] ), 0, 100 ) as $name ) {
            if ($name === 'me') $add( $this->getUser(), 'me', $this->getUser()->getId() );
            elseif (($count = $this->entity_manager->getRepository(User::class)->countByNameOrDisplayName( trim($name) )) < 2)
                $add( $this->entity_manager->getRepository(User::class)->findOneByNameOrDisplayName( trim($name) ), $name, -1 );
            else $addMultiple( $count, $name );
        }


        foreach ( array_slice( $parser->get_array( 'ids', [] ), 0, 100 ) as $id )
            $add( $this->entity_manager->getRepository(User::class)->find( (int)$id ), '', $id );

        return AjaxResponse::success( true, ['data' => $return] );
    }


    /**
     * @Route("jx/soul/heroskill", name="soul_heroskill")
     * @return Response
     */
    public function soul_heroskill(): Response
    {
        $user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        // Get all the picto & count points
        $latestSkill = $this->entity_manager->getRepository(HeroSkillPrototype::class)->getLatestUnlocked($user->getAllHeroDaysSpent());
        $nextSkill = $this->entity_manager->getRepository(HeroSkillPrototype::class)->getNextUnlockable($user->getAllHeroDaysSpent());

        $allSkills = $this->entity_manager->getRepository(HeroSkillPrototype::class)->findAll();

        $factor1 = $latestSkill !== null ? $latestSkill->getDaysNeeded() : 0;
        $progress = $nextSkill !== null ? ($user->getAllHeroDaysSpent() - $factor1) / ($nextSkill->getDaysNeeded() - $factor1) * 100.0 : 0;

        return $this->render( 'ajax/soul/heroskills.html.twig', $this->addDefaultTwigArgs("soul_me", [
            'latestSkill' => $latestSkill,
            'nextSkill' => $nextSkill,
            'progress' => floor($progress),
            'skills' => $allSkills
        ]));
    }

    /**
     * @Route("jx/soul/future/{id}", name="soul_future")
     * @param Request $request
     * @param UserHandler $userHandler
     * @param int $id
     * @return Response
     */
    public function soul_future(Request $request, UserHandler $userHandler, int $id = 0): Response
    {
        $user = $this->getUser();

        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        $lang = $user->getLanguage() ?? $request->getLocale() ?? 'de';
        $news = $this->entity_manager->getRepository(Changelog::class)->findByLang($lang);

        $selected = $id > 0 ? $this->entity_manager->getRepository(Changelog::class)->find($id) : null;
        if ($selected === null)
            $selected = $news[0] ?? null;

        try {
            $userHandler->setSeenLatestChangelog( $user, $lang );
            $this->entity_manager->flush();
        } catch (Exception $e) {}

        return $this->render( 'ajax/soul/future.html.twig', $this->addDefaultTwigArgs("soul_future", [
            'news' => $news, 'selected' => $selected,
            'has_polls' => !empty($this->entity_manager->getRepository(GlobalPoll::class)->findByState(true, true, false))
        ]) );
    }

    /**
     * @Route("jx/soul/polls/{id}/{group}/{tag}", name="soul_polls")
     * @param int $id
     * @param string $group
     * @param string $tag
     * @return Response
     */
    public function soul_polls(int $id = 0, string $group = '', string $tag = ''): Response
    {
        if (($group !== '' || $tag !== '') && !$this->isGranted('ROLE_ADMIN'))
            return $this->redirect($this->generateUrl( 'soul_polls' ));

        if (!$this->isGranted('ROLE_ADMIN')) {
            $group = 'antigrief';
            $tag = 'pass';
        }

        $polls = $this->entity_manager->getRepository(GlobalPoll::class)->findByState(true, true, $this->isGranted('ROLE_ORACLE'));

        $selected = ($id > 0 ? $this->entity_manager->getRepository(GlobalPoll::class)->find($id) : null);
        if ($selected && $selected->getStartDate() > new DateTime() && !$this->isGranted('ROLE_ORACLE'))
            $selected = null;

        if ($selected === null && $id > 0)
            return $this->redirect($this->generateUrl( 'soul_polls' ));

        $selected = $selected ?? $polls[0] ?? null;

        return $this->render( 'ajax/soul/polls.html.twig', $this->addDefaultTwigArgs("soul_future", [
            'all_tags' => $this->isGranted('ROLE_ADMIN') ? $selected->getPoll()->getAllAnswerTags() : [],
            'group' => $group, 'tag' => $tag,
            'polls' => $polls, 'selected' => $selected
        ]) );
    }

    /**
     * @Route("jx/soul/events", name="soul_events")
     * @return Response
     */
    public function soul_events(): Response
    {
        $now = new DateTime();

        $schedule =
            array_filter(
                array_map( function(string $name) use (&$now) {
                    $enabled = $this->conf->getEventScheduleByName( $name, $now, $begin, $end, true );
                    return [ $name, $begin, $end, $enabled ];
                }, $this->conf->getAllEventNames() ),
            function( array $event ) use (&$now) {
                return $this->conf->eventIsPublic( $event[0] ) && ( $event[3] || $event[1] > $now || $event[2] > $now );
            }
        );

        usort( $schedule, fn(array $a, array $b) => $a[1] <=> $b[1] );

        return $this->render( 'ajax/soul/events.html.twig', $this->addDefaultTwigArgs("soul_future", [
            'active_events' => array_filter( $schedule, fn($event) =>  $event[3] ),
            'future_events' => array_filter( $schedule, fn($event) => !$event[3] ),
        ]) );
    }


    /**
     * @Route("api/soul/polls/{id<\d+>}/{answer<\d+>}", name="soul_poll_participate")
     * @param int $id
     * @param int $answer
     * @return Response
     */
    public function soul_poll_participate(int $id = 0, int $answer = 0): Response
    {
        $user = $this->getUser();
        $now = new DateTime();

        $poll = $this->entity_manager->getRepository(GlobalPoll::class)->find($id);
        if (!$poll || $poll->getStartDate() > $now || $poll->getEndDate() < $now || $poll->getPoll()->getParticipants()->contains($user))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if ($answer > 0) {

            $answer = $this->entity_manager->getRepository(ForumPollAnswer::class)->find($answer);
            if (!$answer || !$poll->getPoll()->getAnswers()->contains($answer))
                return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

            $answer->setNum( $answer->getNum() + 1 );

            $main = 'none';
            foreach ($user->getTwinoidImports() as $import)
                if ($import->getMain()) $main = $import->getScope() ?? 'none';

            $sp = $user->getAllSoulPoints();
            $anti_grief =
                $user->getAllSoulPoints() < $this->conf->getGlobalConf()->get(MyHordesConf::CONF_ANTI_GRIEF_SP, 20) ||
                $this->user_handler->isRestricted( $user, AccountRestriction::RestrictionGameplay ) ||
                $this->isGranted( 'ROLE_DUMMY' );

            $answer->incTagNumber('origin', $main);
            $answer->incTagNumber('lang', $user->getLanguage());
            if ($sp < 100)       $answer->incTagNumber('sp', '0_99');
            elseif ($sp < 1000)  $answer->incTagNumber('sp', '100_999');
            elseif ($sp < 10000) $answer->incTagNumber('sp', '1000_9999');
            else                 $answer->incTagNumber('sp', '10000');
            $answer->incTagNumber('antigrief', $anti_grief ? 'fail' : 'pass');

            $this->entity_manager->persist($answer);
        }

        $poll->getPoll()->addParticipant( $user );
        $this->entity_manager->persist( $poll->getPoll() );
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/soul/news", name="soul_news")
     * @return Response
     */
    public function soul_news(): Response
    {
        return $this->render( 'ajax/soul/news.html.twig', $this->addDefaultTwigArgs("soul_news", []) );
    }


    /**
     * @Route("jx/soul/settings", name="soul_settings")
     * @param EternalTwinHandler $etwin
     * @return Response
     */
    public function soul_settings(EternalTwinHandler $etwin): Response
    {
        $user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        $user_desc = $this->entity_manager->getRepository(UserDescription::class)->findOneBy(['user' => $user]);

        $a_max_size = $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD, 3145728);
        $b_max_size = $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_STORAGE, 1048576);

        if     ($a_max_size >= 1048576) $a_max_size = round($a_max_size/1048576, 2) . ' MB';
        elseif ($a_max_size >= 1024)    $a_max_size = round($a_max_size/1024, 0) . ' KB';
        else                            $a_max_size = $a_max_size . ' B';

        if     ($b_max_size >= 1048576) $b_max_size = round($b_max_size/1048576, 2) . ' MB';
        elseif ($b_max_size >= 1024)    $b_max_size = round($b_max_size/1024, 0) . ' KB';
        else                            $b_max_size = $b_max_size . ' B';

        return $this->render( 'ajax/soul/settings.html.twig', $this->addDefaultTwigArgs("soul_settings", [
            'et_ready' => $etwin->isReady(),
            'user_desc' => $user_desc ? $user_desc->getText() : null,
            'flags' => $this->getFlagList(),
            'next_name_change_days' => $user->getLastNameChange() ? max(0, (30 * 4) - $user->getLastNameChange()->diff(new DateTime())->days ) : 0,
            'show_importer'     => $this->conf->getGlobalConf()->get(MyHordesConf::CONF_IMPORT_ENABLED, true),
            'importer_readonly' => $this->conf->getGlobalConf()->get(MyHordesConf::CONF_IMPORT_READONLY, false),
            'avatar_max_size' => [$a_max_size, $b_max_size,$this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD, 3145728)],
            'langs' => $this->allLangs,
        ]) );
    }

    protected function getFlagList(): array {
        $flags = [];
        foreach (scandir("{$this->kernel->getProjectDir()}/assets/img/lang/any") as $f)
            if ($f !== '.' && $f !== '..' && str_ends_with( strtolower($f), '.svg' )) $flags[] = substr( $f, 0, -4);
        return $flags;
    }

    /**
     * @Route("api/soul/settings/header", name="api_soul_header")
     * @param JSONRequestParser $parser
     * @param HTMLService $html
     * @return Response
     */
    public function soul_set_header(JSONRequestParser $parser, HTMLService $html): Response {
        $user = $this->getUser();

        $title = $parser->get_int('title', -1);
        $icon  = $parser->get_int('icon', -1);
        $flag  = $parser->get('flag', '');
        $desc  = mb_substr(trim($parser->get('desc')) ?? '', 0, 256);
        $displayName = mb_substr(trim($parser->get('displayName')) ?? '', 0, 30);
        $pronoun = $parser->get('pronoun','none', ['male','female','none']);

        if ($pronoun !== 'none' && $user->getUseICU() !== true)
            $user->setUseICU(true);

        switch ($pronoun) {
            case 'male': $user->setPreferredPronoun( User::PRONOUN_MALE ); break;
            case 'female': $user->setPreferredPronoun( User::PRONOUN_FEMALE ); break;
            default: $user->setPreferredPronoun( User::PRONOUN_NONE ); break;
        }

        if ($title < 0 && $icon >= 0)
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($flag !== '' && !in_array( $flag, $this->getFlagList() ))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $name_change = ($displayName !== $user->getDisplayName() && $user->getDisplayName() !== null) || ($displayName !== $user->getUsername() && $user->getDisplayName() === null);

        if ($name_change && !$this->user_handler->isNameValid($displayName, $too_long))
            return AjaxResponse::error(!$too_long ? self::ErrorUserEditUserName : self::ErrorUserEditUserNameTooLong);

        if ($name_change && $user->getLastNameChange() !== null && $user->getLastNameChange()->diff(new DateTime())->days < (30 * 4)) { // 6 months
            return  AjaxResponse::error(self::ErrorUserEditTooSoon);
        }
        if ($name_change && $user->getEternalID() !== null && !$user->getNoAutomaticNameManagement())
            return AjaxResponse::error(self::ErrorUserUseEternalTwin);

        if ($this->user_handler->isRestricted($user, AccountRestriction::RestrictionProfileDisplayName) && $name_change)
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if ($title < 0)
            $user->setActiveTitle(null);
        else {
            $award = $this->entity_manager->getRepository(Award::class)->find( $title );
            if ($award === null || $award->getUser() !== $user || ($award->getCustomTitle() === null && ($award->getPrototype() === null || $award->getPrototype()->getTitle() === null) ))
                return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

            if ($this->user_handler->isRestricted($user, AccountRestriction::RestrictionProfileTitle) && $user->getActiveTitle() !== $award)
                return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

            $user->setActiveTitle($award);
        }

        if ($icon < 0)
            $user->setActiveIcon(null);
        else {
            $award = $this->entity_manager->getRepository(Award::class)->find( $icon );
            if ($award === null || $award->getUser() !== $user || ($award->getPrototype() === null && $award->getCustomIcon() === null) || ($award->getPrototype() !== null && $award->getPrototype()->getIcon() === null))
                return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

            if ($this->user_handler->isRestricted($user, AccountRestriction::RestrictionProfileTitle) && $user->getActiveIcon() !== $award)
                return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

            $user->setActiveIcon($award);
        }

        $user->setFlag($flag === '' ? null : $flag);

        $desc_obj = $this->entity_manager->getRepository(UserDescription::class)->findOneBy(['user' => $user]);
        if (!empty($desc) && $html->htmlPrepare($user, 0, false, $desc, null, $insight) && $insight->text_length > 0) {
            if (!$this->user_handler->isRestricted($user, AccountRestriction::RestrictionProfileDescription)) {
                if (!$desc_obj) $desc_obj = (new UserDescription())->setUser($user);
                $desc_obj->setText($desc);
                $this->entity_manager->persist($desc_obj);
            }
        } elseif ($desc_obj) $this->entity_manager->remove($desc_obj);

        if(!empty($displayName) && $displayName !== $user->getName() && ($user->getEternalID() === null || $user->getNoAutomaticNameManagement())) {
            $history = $user->getNameHistory() ?? [];
            if(!in_array($user->getName(), $history))
                $history[] = $user->getName();
            $user->setNameHistory(array_filter(array_unique($history)));
            if ($displayName === $user->getUsername())
                $user->setDisplayName(null);
            else {
                $user->setDisplayName($displayName);
            }

            $user->setLastNameChange(new DateTime());
        }

        $this->entity_manager->persist($user);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/soul/ranking/{type<\d+>}/{season<\d+|c|all|a>}", name="soul_season")
     * @param JSONRequestParser $parser
     * @param null $type Type of town we're looking the ranking for
     * @param null $season
     * @return Response
     */
    public function soul_season(JSONRequestParser $parser, $type = null, $season = null): Response
    {
        $user = $this->getUser();

        $seasonId = $season ?? $parser->get('season', 'c');

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        $seasons = $this->entity_manager->getRepository(Season::class)->matching((Criteria::create())
            ->orWhere(Criteria::expr()->gt('number', 0))
            ->orWhere(Criteria::expr()->gt('subNumber', 14))
        );
        if ($seasonId === null || $seasonId === 'c')
            $currentSeason = $this->entity_manager->getRepository(Season::class)->findOneBy(['current' => true]);
        elseif ($seasonId === 'a')
            $currentSeason = null;
        else {
            $currentSeason = $this->entity_manager->getRepository(Season::class)->find($seasonId);
            if ($currentSeason === null) return $this->redirect($this->generateUrl( 'soul_season', ['type' => $type, 'season' => 'c'] ));
        }

        if ($type === null)
            $currentType = $this->entity_manager->getRepository(TownClass::class)->findBy(['ranked' => true], ['orderBy' => 'ASC'])[0];
        else
            $currentType = $this->entity_manager->getRepository(TownClass::class)->find($type);

        if ($currentType === null)
            return $this->redirect($this->generateUrl('soul_season'));

        $towns = $this->entity_manager->getRepository(TownRankingProxy::class)->findTopOfSeason($currentSeason, $currentType);
        $played = [];
        foreach ($towns as $town) {
            /* @var TownRankingProxy $town */
            foreach ($town->getCitizens() as $citizen) {
                /* @var CitizenRankingProxy $citizen */
                if($citizen->getUser() === $user) {
                    $played[$town->getId()] = true;
                    break;
                }
            }
        }

        return $this->render( 'ajax/soul/season.html.twig', $this->addDefaultTwigArgs("soul_season", [
            'seasons' => $seasons,
            'currentSeason' => $currentSeason,
            'virtualSeason' => false,
            'towns' => $towns,
            'townTypes' => $this->entity_manager->getRepository(TownClass::class)->findBy(['ranked' => true], ['orderBy' => 'ASC']),
            'currentType' => $currentType,
            'played' => $played,
            'user' => $user
        ]) );
    }

    /**
     * @Route("jx/soul/ranking/soul/{page}/{season<\d+|c|all|myh|a>}", name="soul_season_solo")
     * @param JSONRequestParser $parser
     * @param int $page
     * @param null $season
     * @return Response
     */
    public function soul_season_solo(JSONRequestParser $parser, int $page = 1, $season = null): Response
    {
        $resultsPerPage = 30;
        $offset = $resultsPerPage * ($page - 1);

        $user = $this->getUser();

        $seasonId = $season ?? $parser->get('season', 'all');

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        $seasons = $this->entity_manager->getRepository(Season::class)->matching((Criteria::create())
            ->orWhere(Criteria::expr()->gt('number', 0))
            ->orWhere(Criteria::expr()->gt('subNumber', 14))
        );
        if ($seasonId === 'all' || $seasonId === 'myh')
            $currentSeason = $seasonId;
        elseif ($seasonId === null || $seasonId === 'c')
            $currentSeason = $this->entity_manager->getRepository(Season::class)->findOneBy(['current' => true]);
        elseif ($seasonId === 'a')
            $currentSeason = null;
        else {
            $currentSeason = $this->entity_manager->getRepository(Season::class)->find($seasonId);
            if ($currentSeason === null) return $this->redirect($this->generateUrl( 'soul_season_solo', ['season' => 'c'] ));
        }

        if ($currentSeason === 'all' || $currentSeason === 'myh') {
            $ranking = $this->entity_manager->getRepository(User::class)->getGlobalSoulRankingPage($offset, $resultsPerPage, $currentSeason === 'myh');
            $pages = $this->entity_manager->getRepository(User::class)->countGlobalSoulRankings($currentSeason === 'myh');
            $this->entity_manager->getRepository(User::class)->getGlobalSoulRankingUserStats($user, $currentSeason === 'myh', $user_sp, $user_rank);
        } else {
            $ranking = $this->entity_manager->getRepository(User::class)->getSeasonSoulRankingPage($offset, $resultsPerPage, $currentSeason);
            $pages = $this->entity_manager->getRepository(User::class)->countSeasonSoulRankings($currentSeason);
            $this->entity_manager->getRepository(User::class)->getSeasonSoulRankingUserStats($user, $currentSeason, $user_sp, $user_rank);
        }

        //if (!$ranking || !$pages)
        //    return $this->redirect($this->generateUrl( 'soul_season' ));

        return $this->render( 'ajax/soul/season.html.twig', $this->addDefaultTwigArgs("soul_season", [
            'seasons' => $seasons,
            'currentSeason' => $seasonId === 'a' ? 'a' : $currentSeason,
            'virtualSeason' => is_string($currentSeason) || $seasonId === 'a',
            'ranking' => $ranking,
            'currentType' => 0,
            'soloType' => 'soul',
            'page' => $page,
            'pages' => ceil($pages / $resultsPerPage),
            'townTypes' => $this->entity_manager->getRepository(TownClass::class)->findBy(['ranked' => true], ['orderBy' => 'ASC']),
            'offset' => $offset,
            'user' => $user,
            'user_sp' => $user_sp,
            'user_rank' => $user_rank,
            'page_size' => $resultsPerPage,
        ]) );
    }

    /**
     * @Route("jx/soul/rps", name="soul_rps")
     * @return Response
     */
    public function soul_rps(): Response
    {
        $user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        $rps = $this->entity_manager->getRepository(FoundRolePlayText::class)->findByUser($user);
        foreach ($rps as $rp) {
            // We mark as new RPs found in the last 5 minutes
            /** @var FoundRolePlayText $rp */
            $elapsed = $rp->getDateFound()->diff(new \DateTime());
            if ($elapsed->y == 0 && $elapsed->m == 0 && $elapsed->d == 0 && $elapsed->h == 0 && $elapsed->i <= 5)
                $rp->setNew(true);
        }
        return $this->render( 'ajax/soul/rps.html.twig', $this->addDefaultTwigArgs("soul_rps", array(
            'rps' => $rps
        )));
    }

    /**
     * @Route("jx/soul/rps/read/{id}-{page}", name="soul_rp", requirements={"id"="\d+", "page"="\d+"})
     * @param int $id
     * @param int $page
     * @return Response
     */
    public function soul_view_rp(int $id, int $page): Response
    {
        $user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));
        /** @var FoundRolePlayText $rp */
        $rp = $this->entity_manager->getRepository(FoundRolePlayText::class)->find($id);
        if($rp === null || !$user->getFoundTexts()->contains($rp)){
            return $this->redirect($this->generateUrl('soul_rps'));
        }

        if($page > count($rp->getText()->getPages()))
            return $this->redirect($this->generateUrl('soul_rps'));

        $rp->setNew(false);
        $this->entity_manager->persist($rp);

        $pageContent = $this->entity_manager->getRepository(RolePlayTextPage::class)->findOneByRpAndPageNumber($rp->getText(), $page);

        preg_match('/{asset}([a-zA-Z0-9.\/]+){endasset}/', $pageContent->getContent(), $matches);

        if(count($matches) > 0) {
            $pageContent->setContent(preg_replace("={$matches[0]}=", "<img src='" . $this->asset->getUrl($matches[1]) . "' alt='' />", $pageContent->getContent()));
        }
        
        $this->entity_manager->flush();

        return $this->render( 'ajax/soul/view_rp.html.twig', $this->addDefaultTwigArgs("soul_rps", array(
            'page' => $pageContent,
            'rp' => $rp,
            'current' => $page
        )));
    }


    /**
     * @Route("jx/soul/{sid}/town/{idtown}/{return_path}", name="soul_view_town")
     * @param string $sid
     * @param int $idtown
     * @param string $return_path
     * @return Response
     */
    public function soul_view_town(int $idtown, string $sid = 'me', string $return_path = "soul_me"): Response
    {
        $user = $this->getUser();

        try {
            $this->generateUrl($return_path);
        } catch (RouteNotFoundException $e) {
            $return_path = "soul_me";
        }

        if ($sid !== 'me' && !is_numeric($sid))
            return $this->redirect($this->generateUrl('soul_me'));

        $id = $sid === 'me' ? -1 : (int)$sid;
        if ($id === $user->getId())
            return $this->redirect($this->generateUrl( 'soul_view_town', ['idtown' => $idtown] ));

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        $target_user = $this->entity_manager->getRepository(User::class)->find($id);
        if($target_user === null && $id !== -1)  return $this->redirect($this->generateUrl('soul_me'));

        $town = $this->entity_manager->getRepository(TownRankingProxy::class)->find($idtown);
        if($town === null)
            return $target_user === null ? $this->redirect($this->generateUrl('soul_me')) : $this->redirect($this->generateUrl('soul_visit', ['id' => $id]));

        if ($target_user === null) $target_user = $user;

        $pictoname = $town->getType()->getName() == 'panda' ? 'r_suhard_#00' : 'r_surlst_#00';
        $proto = $this->entity_manager->getRepository(PictoPrototype::class)->findOneBy(['name' => $pictoname]);

        $picto = $this->entity_manager->getRepository(Picto::class)->findOneBy(['townEntry' => $town, 'prototype' => $proto]);

        return $this->render(  $user === $target_user ? 'ajax/soul/view_town.html.twig' : 'ajax/soul/view_town_foreign.html.twig', $this->addDefaultTwigArgs("soul_visit", array(
            'user' => $target_user,
            'town' => $town,
            'last_user_standing' => $picto !== null ? $picto->getUser() : null,
            'return_path' => $return_path,
            'self_path' => $this->generateUrl('soul_view_town', ['sid' => $sid, 'idtown' => $idtown, 'return_path' => $return_path])
        )));
    }

    /**
     * @Route("api/soul/town/add_comment", name="soul_add_comment")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function soul_add_comment(JSONRequestParser $parser): Response
    {
        $user = $this->getUser();

        if ($this->user_handler->isRestricted( $user, AccountRestriction::RestrictionComments )) return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $id = $parser->get("id");
        /** @var CitizenRankingProxy $citizenProxy */
        $citizenProxy = $this->entity_manager->getRepository(CitizenRankingProxy::class)->find($id);
        if ($citizenProxy === null || $citizenProxy->getUser() !== $user || $citizenProxy->getCommentLocked() )
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if ($citizenProxy->getCitizen() !== null && $citizenProxy->getCitizen()->getAlive())
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $comment = $parser->get("comment");
        $citizenProxy->setComment($comment);
        $citizenProxy->getCitizen()?->setComment($comment ?? '');

        $this->entity_manager->persist($citizenProxy);
        $this->entity_manager->flush();

        $this->addFlash('notice', $this->translator->trans('Deine Nachricht wurde gespeichert.', [], 'game'));

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/{sid}/report", name="soul_report_user")
     * @param int $sid
     * @param JSONRequestParser $parser
     * @param RateLimitingFactoryProvider $rateLimiter
     * @return Response
     */
    public function soul_report_user(int $sid, JSONRequestParser $parser, RateLimitingFactoryProvider $rateLimiter): Response
    {
        $user = $this->getUser();
        if ($sid === $user->getId())
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable );

        $target_user = $this->entity_manager->getRepository(User::class)->find($sid);

        if (!$target_user)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest );

        return $this->reportUser( $target_user, $parser, $rateLimiter->reportLimiter( $user ) );
    }

    /**
     * @Route("api/soul/{sid}/town/{idtown}/report/{topic}", name="soul_report_comment")
     * @param int $sid
     * @param int $idtown
     * @param string $topic
     * @param JSONRequestParser $parser
     * @param RateLimitingFactoryProvider $rateLimiter
     * @return Response
     */
    public function soul_report_comment(int $sid, int $idtown, string $topic, JSONRequestParser $parser, RateLimitingFactoryProvider $rateLimiter): Response
    {
        if (!in_array( $topic, ['lw','com'] )) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest );

        $user = $this->getUser();
        if ($sid === $user->getId())
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable );

        $target_user = $this->entity_manager->getRepository(User::class)->find($sid);
        $town = $this->entity_manager->getRepository(TownRankingProxy::class)->find($idtown);

        if (!$town || !$target_user)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest );

        $citizen = null;
        foreach ($town->getCitizens() as $c) if ($c->getUser() === $target_user) $citizen = $c;
        if (!$citizen)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest );

        return $this->reportCitizen( $citizen, $topic === 'lw' ? AdminReportSpecification::CitizenLastWords : AdminReportSpecification::CitizenTownComment, $parser, $rateLimiter->reportLimiter( $user ) );
    }

    /**
     * @Route("api/soul/settings/generateid", name="api_soul_settings_generateid")
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function soul_settings_generate_id(EntityManagerInterface $entityManager): Response {
        $user = $this->getUser();
        if (!$user)
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable);

        $user->setExternalId(md5($user->getEmail() . mt_rand()));
        $entityManager->persist( $user );
        $entityManager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/deleteid", name="api_soul_settings_deleteid")
     * @param EntityManagerInterface $entityManager
     * @return Response
     */
    public function soul_settings_deleteid(EntityManagerInterface $entityManager): Response {
        $user = $this->getUser();
        if (!$user)
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable);

        $user->setExternalId('');
        $entityManager->persist( $user );
        $entityManager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/common", name="api_soul_common")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function soul_settings_common(JSONRequestParser $parser, SessionInterface $session): Response {
        $user = $this->getUser();

        $user->setPreferSmallAvatars( (bool)$parser->get('sma', false) );
        $user->setDisableFx( (bool)$parser->get('disablefx', false) );
        $user->setUseICU( (bool)$parser->get('useicu', false) );
        $user->setNoAutoFollowThreads( !$parser->get('autofollow', true) );
        $user->setClassicBankSort( (bool)$parser->get('clasort', false) );
        $user->setSetting( UserSetting::LimitTownListSize, (bool)$parser->get('town10', true) );
        $user->setSetting( UserSetting::NotifyMeWhenMentioned, (int)$parser->get('notify', 0) );
        $user->setSetting( UserSetting::NotifyMeOnFriendRequest, (bool)$parser->get('notifyFriend', true) );
        $user->setAdminLang($parser->get("adminLang", null));
        $session->set('_admin_lang',$user->getAdminLang() ?? $user->getLanguage());
        $this->entity_manager->persist( $user );
        $this->entity_manager->flush();

        $this->addFlash('notice', $this->translator->trans('Du hast die Seiteneinstellungen geändert.', [], 'global'));

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/setlanguage", name="api_soul_set_language")
     * @param JSONRequestParser $parser
     * @param Request $request
     * @param UserHandler $userHandler
     * @param SessionInterface $session
     * @return Response
     */
    public function soul_settings_set_language(JSONRequestParser $parser, Request $request, UserHandler $userHandler, SessionInterface $session): Response {
        $user = $this->getUser();

        if (!$parser->has('lang', true))
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        
        $lang = $parser->get('lang', 'de');
        if (!in_array($lang, $this->allLangsCodes))
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);

        // Check if the user has seen all news in the previous language
        $previous_lang = $user->getLanguage() ?? $request->getLocale() ?? 'de';
        $seen_news = $userHandler->hasSeenLatestChangelog($user, $previous_lang);

        $user->setLanguage( $lang );
        $session->set('_user_lang',$lang);

        if ($seen_news) $userHandler->setSeenLatestChangelog($user, $lang);
        else $user->setLatestChangelog(null);

        $this->entity_manager->persist( $user );
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/defaultrole", name="api_soul_defaultrole")
     * @param JSONRequestParser $parser
     * @param AdminHandler $admh
     * @return Response
     */
    public function soul_settings_default_role(JSONRequestParser $parser, AdminHandler $admh): Response {
        $user = $this->getUser();

        $asDev = $parser->get('dev', false);
        if ($admh->setDefaultRoleDev($user->getId(), $asDev))
            return AjaxResponse::success();

        return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
    }

    /**
     * @Route("api/soul/settings/mod_tools_window", name="api_soul_mod_tools_window")
     * @param JSONRequestParser $parser
     * @param AdminHandler $admh
     * @return Response
     */
    public function soul_mod_tools_window(JSONRequestParser $parser, AdminHandler $admh): Response {
        $user = $this->getUser();

        $new_window = $parser->get('same_window', false);
        $user->setOpenModToolsSameWindow($new_window);

        $this->entity_manager->persist($user);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/avatar", name="api_soul_avatar")
     * @param JSONRequestParser $parser
     * @param ConfMaster $conf
     * @return Response
     */
    public function soul_settings_avatar(JSONRequestParser $parser, ConfMaster $conf): Response {

        $payload = $parser->get_base64('image');
        $upload = (int)$parser->get('up', 1);
        $mime = $parser->get('mime');

        $user = $this->getUser();

        if ($upload) {
            if ($this->user_handler->isRestricted($user, AccountRestriction::RestrictionProfileAvatar))
                return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

            if (!$payload) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
            
            $raw_processing = $conf->getGlobalConf()->get(MyHordesConf::CONF_RAW_AVATARS, false);
            $error = $this->user_handler->setUserBaseAvatar($user, $payload, $raw_processing ? UserHandler::ImageProcessingPreferImagick : UserHandler::ImageProcessingForceImagick, $raw_processing ? $mime : null);
            if ($error !== UserHandler::NoError)
                return AjaxResponse::error($error);

            $this->entity_manager->persist( $user );
        } elseif ($user->getAvatar()) {

            $this->entity_manager->remove($user->getAvatar());
            $user->setAvatar(null);
        }

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/avatar/crop", name="api_soul_small_avatar")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function soul_settings_small_avatar(JSONRequestParser $parser): Response
    {
        if (!$parser->has_all(['x', 'y', 'dx', 'dy'], false))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $user = $this->getUser();
        if ($this->user_handler->isRestricted($user, AccountRestriction::RestrictionProfileAvatar))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $x  = (int)floor((float)$parser->get('x', 0));
        $y  = (int)floor((float)$parser->get('y', 0));
        $dx = (int)floor((float)$parser->get('dx', 0));
        $dy = (int)floor((float)$parser->get('dy', 0));

        $error = $this->user_handler->setUserSmallAvatar($user, null, $x, $y, $dx, $dy);
        if ($error !== UserHandler::NoError) return AjaxResponse::error( $error );

        $this->entity_manager->persist($user);

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/change_account_details", name="api_soul_change_account_details")
     * @param UserPasswordHasherInterface $passwordEncoder
     * @param JSONRequestParser $parser
     * @param TokenStorageInterface $token
     * @return Response
     */
    public function soul_settings_change_account_details(UserPasswordHasherInterface $passwordEncoder, JSONRequestParser $parser, TokenStorageInterface $token): Response
    {
        $user = $this->getUser();
        $new_pw = $parser->trimmed('pw_new', '');
        $new_email = $parser->trimmed('email_new', '');
        $confirm_token = $parser->trimmed('email_token', '');
        $message = [];

        if ($this->isGranted('ROLE_DUMMY') && !$this->isGranted( 'ROLE_CROW' ))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if ($this->isGranted('ROLE_ETERNAL') && $user->getPassword() === null && !empty($new_pw))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $change = false;

        if(!empty($new_pw)) {
            if (mb_strlen($new_pw) < 6) return AjaxResponse::error(self::ErrorUserEditPasswordIncorrect);

            if (!$passwordEncoder->isPasswordValid( $user, $parser->trimmed('pw') ))
                return AjaxResponse::error(self::ErrorUserEditPasswordIncorrect );

            $user
                ->setPassword( $passwordEncoder->hashPassword($user, $parser->trimmed('pw_new')) )
                ->setCheckInt($user->getCheckInt() + 1);

            if ($rm_token = $this->entity_manager->getRepository(RememberMeTokens::class)->findOneBy(['user' => $user]))
                $this->entity_manager->remove($rm_token);
            $change = true;

        }

        if (!empty($new_email)) {
            if ($this->entity_manager->getRepository(User::class)->findOneByMail( $new_email ))
                return AjaxResponse::error(UserFactory::ErrorMailExists);

            if ($this->entity_manager->getRepository(AntiSpamDomains::class)->findOneBy( ['type' => DomainBlacklistType::EmailAddress, 'domain' => DomainBlacklistType::EmailAddress->convert( $new_email )] ))
                return AjaxResponse::error(UserFactory::ErrorMailExists);

            $user->setPendingEmail($new_email);
            if (!$this->user_factory->announceValidationToken($this->user_factory->ensureValidation($user, UserPendingValidation::ChangeEmailValidation, true)))
                return AjaxResponse::error(ErrorHelper::ErrorSendingEmail);
            $change = true;
        } else if (!empty($confirm_token)) {
            if (($pending = $this->entity_manager->getRepository(UserPendingValidation::class)->findOneByTokenAndUserandType(
                    $confirm_token, $user, UserPendingValidation::ChangeEmailValidation)) === null) {
                return AjaxResponse::error(self::ErrorUserConfirmToken);
            }

            if ($pending->getUser() === null || ($user !== null && !$user->isEqualTo( $pending->getUser() ))) {
                return AjaxResponse::error(self::ErrorUserConfirmToken);
            }

            if ($pending->getPkey() !== $confirm_token) {
                return AjaxResponse::error(self::ErrorUserConfirmToken);
            }

            $user->setEmail($user->getPendingEmail());
            $user->setPendingEmail(null);
            $user->setPendingValidation(null);
            $this->entity_manager->remove( $pending );
            $change = true;
            $message[] = $this->translator->trans('Deine E-Mail-Adresse wurde erfolgreich geändert.', [], 'login');
        }

        if ($change){
            $this->entity_manager->persist($user);
            $this->entity_manager->flush();

            if (!empty($new_pw)) {
                $message[] = $this->translator->trans('Dein Passwort wurde erfolgreich geändert. Bitte logge dich mit deinem neuen Passwort ein.', [], 'login');
                $token->setToken(null);
            }

            if (!empty($new_email)) {
                $message[] = $this->translator->trans('Deine E-Mail Adresse wurde geändert. Bitte validiere die neue Adresse, damit du sie verwenden kannst.', [], 'login');
            }

            $this->addFlash('notice', implode('<hr />', $message));
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/resend_token_email", name="api_soul_resend_token_email")
     * @return Response
     */
    public function soul_settings_resend_token_email(): Response
    {
        $user = $this->getUser();

        if ($this->user_factory->announceValidationToken($this->user_factory->ensureValidation($user, UserPendingValidation::ChangeEmailValidation, true))) {
            return AjaxResponse::success();
        }

        return AjaxResponse::error();
    }

    /**
     * @Route("api/soul/settings/cancel_token_email", name="api_soul_cancel_email_token")
     * @return Response
     */
    public function soul_settings_cancel_token_email(): Response
    {
        $user = $this->getUser();

        $token = $this->entity_manager->getRepository(UserPendingValidation::class)->findOneByUserAndType($user,UserPendingValidation::ChangeEmailValidation);

        if ($token) {
            $this->entity_manager->remove( $token );
        }
        $user->setPendingEmail(null);
        $this->entity_manager->persist($user);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/unremember_me", name="api_soul_unremember_me")
     * @param TokenStorageInterface $token
     * @return Response
     */
    public function soul_settings_unremember(TokenStorageInterface $token): Response
    {
        $user = $this->getUser();
        $user->setCheckInt($user->getCheckInt() + 1);

        if ($rm_token = $this->entity_manager->getRepository(RememberMeTokens::class)->findOneBy(['user' => $user]))
            $this->entity_manager->remove($rm_token);

        $this->entity_manager->persist($user);
        $this->entity_manager->flush();

        $this->addFlash( 'notice', $this->translator->trans('Du wurdest erfolgreich von allen Geräten abgemeldet.', [], 'login') );
        $token->setToken(null);
        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/settings/delete_account", name="api_soul_delete_account")
     * @param UserPasswordHasherInterface $passwordEncoder
     * @param JSONRequestParser $parser
     * @param TokenStorageInterface $token
     * @return Response
     */
    public function soul_settings_delete_account(UserPasswordHasherInterface $passwordEncoder, JSONRequestParser $parser, TokenStorageInterface $token): Response
    {
        $user = $this->getUser();

        if ($this->user_handler->getActiveRestrictions( $user ) !== AccountRestriction::RestrictionNone || $this->isGranted('ROLE_ETERNAL') || $this->isGranted('ROLE_DUMMY'))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if (!$passwordEncoder->isPasswordValid( $user, $parser->trimmed('pw') ))
            return AjaxResponse::error(self::ErrorUserEditPasswordIncorrect );

        $name = $user->getUsername();
        $user->setDeleteAfter( new DateTime('+24hour') );
        $user->setCheckInt($user->getCheckInt() + 1);

        if ($rm_token = $this->entity_manager->getRepository(RememberMeTokens::class)->findOneBy(['user' => $user]))
            $this->entity_manager->remove($rm_token);

        $this->entity_manager->flush();

        $this->addFlash( 'notice', $this->translator->trans('Auf wiedersehen, {name}. Wir werden dich vermissen und hoffen, dass du vielleicht doch noch einmal zurück kommst.', ['{name}' => $name], 'login') );
        $token->setToken(null);
        return AjaxResponse::success();
    }

    /**
     * @Route("jx/soul/{id}", name="soul_visit", requirements={"id"="\d+"})
     * @param int $id
     * @param HTMLService $html
     * @return Response
     */
    public function soul_visit(int $id, HTMLService $html): Response
    {
        $current_user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        if ($this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($current_user))
            return $this->redirect($this->generateUrl( 'soul_death' ));

        /** @var User $user */
    	$user = $this->entity_manager->getRepository(User::class)->find($id);
    	if($user === null || $user === $current_user) 
            return $this->redirect($this->generateUrl('soul_me'));

        $pictos = $this->entity_manager->getRepository(Picto::class)->findNotPendingByUser($user);
        $top3 = array_slice($pictos, 0, 3);
    	$points = $this->user_handler->getPoints($user);

        $returnUrl = null; // TODO: get the referer, it can be empty!
        if(empty($returnUrl))
            $returnUrl = $this->generateUrl('soul_me');

        $cac = $current_user->getActiveCitizen();
        $uac = $user->getActiveCitizen();
        $citizen_id = ($cac && $uac && $cac->getAlive() && !$cac->getZone() && $cac->getTown() === $uac->getTown()) ? $uac->getId() : null;

        $desc = $this->entity_manager->getRepository(UserDescription::class)->findOneBy(['user' => $user]);

        return $this->render( 'ajax/soul/visit.html.twig', $this->addDefaultTwigArgs("soul_visit", [
        	'user' => $user,
            'pictos' => $pictos,
            'top3' => $top3,
            'points' => round($points),
            'seasons' => $this->entity_manager->getRepository(Season::class)->findPastAndPresent(),
            'returnUrl' => $returnUrl,
            'citizen_id' => $citizen_id,
            'user_desc' => $desc ? $html->prepareEmotes($desc->getText(), $this->getUser()) : null
        ]));
    }

    /**
     * @Route("api/soul/{id}/block/{action}", name="soul_block_control", requirements={"id"="\d+", "action"="\d+"})
     * @GateKeeperProfile(allow_during_attack=true)
     * @param int $id
     * @param int $action
     * @return Response
     */
    public function soul_control_block(int $id, int $action): Response
    {
        if ($action !== 0 && $action !== 1) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $target_user = $this->entity_manager->getRepository(User::class)->find($id);
        if ($target_user === null || $target_user === $this->getUser())
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($this->user_handler->hasRole($target_user, 'ROLE_CROW'))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $is_blocked = $this->user_handler->checkRelation($this->getUser(), $target_user, SocialRelation::SocialRelationTypeBlock);
        if (($is_blocked && $action === 1) || (!$is_blocked && $action === 0)) return AjaxResponse::success();

        if ($action === 1) {
            $this->entity_manager->persist((new SocialRelation())->setType(SocialRelation::SocialRelationTypeBlock)->setOwner($this->getUser())->setRelated($target_user));
            $this->entity_manager->persist( $this->getUser()->removeFriend( $target_user ) );
            $this->entity_manager->persist( $target_user->removeFriend( $this->getUser() ) );
        } else $this->entity_manager->remove( $this->entity_manager->getRepository( SocialRelation::class )->findOneBy( ['owner' => $this->getUser(), 'related' => $target_user, 'type' => SocialRelation::SocialRelationTypeBlock ] ) );

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) { return AjaxResponse::error( ErrorHelper::ErrorDatabaseException ); }

        if ($action === 1) $this->addFlash('notice', $this->translator->trans('Du hast diesen Spieler auf deine Blacklist gesetzt.', [], 'global'));
        else $this->addFlash('notice', $this->translator->trans('Du hast diesen Spieler von deiner Blacklist genommen.', [], 'global'));

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/unsubscribe", name="api_unsubscribe")
     * @param JSONRequestParser $parser
     * @param SessionInterface $session
     * @return Response
     */
    public function unsubscribe_api(JSONRequestParser $parser, SessionInterface $session): Response {
        $this->user_handler->confirmNextDeath( $this->getUser(), $parser->get('lastwords', '') );

        if ($session->has('_town_lang')) {
            $session->remove('_town_lang');
            return AjaxResponse::success()->setAjaxControl(AjaxResponse::AJAX_CONTROL_RESET);
        } else return AjaxResponse::success();
    }

    /**
     * @Route("jx/soul/welcomeToNowhere", name="soul_death")
     * @return Response
     */
    public function soul_death_page(): Response
    {
        $user = $this->getUser();

        /** @var CitizenRankingProxy $nextDeath */
        $nextDeath = $this->entity_manager->getRepository(CitizenRankingProxy::class)->findNextUnconfirmedDeath($user);
        if ($nextDeath === null || ($nextDeath->getCitizen() && $nextDeath->getCitizen()->getAlive()))
            return $this->redirect($this->generateUrl('initial_landing'));

        $pictosDuringTown = $this->entity_manager->getRepository(Picto::class)->findPictoByUserAndTown($user, $nextDeath->getTown());
        $pictosWonDuringTown = [];
        $pictosNotWonDuringTown = [];

        foreach ($pictosDuringTown as $picto) {
            if ($picto->getPrototype()->getName() == "r_ptame_#00") continue;
            if ($picto->getPersisted() > 0)
                $pictosWonDuringTown[] = $picto;
            else
                $pictosNotWonDuringTown[] = $picto;
        }

        $canSeeGazette = $nextDeath->getTown() !== null;
        if($canSeeGazette){
            $citizensAlive = false;
            foreach ($nextDeath->getTown()->getCitizens() as $citizen) {
                if($citizen->getCod() === null){
                    $citizensAlive = true;
                    break;
                }
            }
            if($citizensAlive || $nextDeath->getCod()->getRef() === CauseOfDeath::Radiations) {
                $canSeeGazette = true;
            } else {
                $canSeeGazette = false;
            }
        }


        return $this->render( 'ajax/soul/death.html.twig', $this->addDefaultTwigArgs(null, [
            'dead_citizen' => $nextDeath,
            'citizen' => $nextDeath,
            'sp' => $nextDeath->getPoints(),
            'day' => $nextDeath->getDay(),
            'pictos' => $pictosWonDuringTown,
            'gazette' => $canSeeGazette,
            'denied_pictos' => $pictosNotWonDuringTown
        ]) );
    }

    /**
     * @Route("api/soul/{user_id}/towns_all", name="soul_get_towns")
     * @param int $user_id
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function soul_town_list(int $user_id, JSONRequestParser $parser): Response {
        /** @var User $user */
        $user = $this->entity_manager->getRepository(User::class)->find($user_id);
        if ($user === null) return new Response("");

        $season_id = $parser->get('season', '');
        if(empty($season_id)) return new Response("");

        $season = $this->entity_manager->getRepository(Season::class)->findOneBy(['id' => $season_id]);

        $limit = (bool)$parser->get('limit10', $user->getSetting( UserSetting::LimitTownListSize ));

        $commonTowns = [];
        $citizens = $this->entity_manager->getRepository(CitizenRankingProxy::class)->findPastByUserAndSeason($user, $season, $limit, true);

        if ($this->getUser() !== $user) {
            /** @var CitizenRankingProxy $citizen */
            foreach ($citizens as $citizen) {
                foreach ($citizen->getTown()->getCitizens() as $c) {
                    if ($c->getUser() === $this->getUser()) {
                        $commonTowns[] = $citizen->getId();
                        break;
                    }
                }
            }
        }

        return $this->render( 'ajax/soul/town_list.html.twig', [
            'towns' => $citizens,
            'commonTowns' => $commonTowns,
            'editable' => $user->getId() === $this->getUser()->getId()
        ]);
    }

    /**
     * @Route("jx/help", name="help_me")
     * @return Response
     */
    public function help_me(): Response
    {
        $support_groups = $this->entity_manager->getRepository(OfficialGroup::class)->findBy(['lang' => $this->getUserLanguage(), 'semantic' => OfficialGroup::SEMANTIC_SUPPORT]);
        return $this->render( 'ajax/help/shell.html.twig', [
            'support' => count($support_groups) === 1 ? $support_groups[0] : null
        ]);
    }

    /**
     * @Route("api/soul/app/{id<\d+>}", name="soul_own_app_update")
     * @param int $id
     * @param JSONRequestParser $parser
     * @param RandomGenerator $rand
     * @return Response
     */
    public function api_update_own_app(int $id, JSONRequestParser $parser, RandomGenerator $rand) {
        /** @var ExternalApp $app */
        $app = $this->entity_manager->getRepository(ExternalApp::class)->find($id);

        if ($app === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        if ($app->getOwner() === null || $app->getOwner() !== $this->getUser()) return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if (!$parser->has_all( ['contact','url'], true )) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $violations = Validation::createValidator()->validate( $parser->all( true ), new Constraints\Collection([
            'url' => [ new Constraints\Url( ['relativeProtocol' => false, 'protocols' => ['http', 'https'], 'message' => 'a' ] ) ],
            'contact' => [ new Constraints\Email( ['message' => 'v']) ],
            'sk' => [  ]
        ]) );

        if ($violations->count() > 0) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $app->setUrl( $parser->trimmed('url') )->setContact( $parser->trimmed('contact') );
        if ( !$app->getLinkOnly() && $parser->get('sk', null) ) {
            $s = '';
            for ($i = 0; $i < 32; $i++) $s .= $rand->pick(['0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f']);
            $app->setSecret( $s );
        }

        $this->entity_manager->persist($app);
        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/soul/game_history", name="soul_game_history")
     * @param JSONRequestParser $parser
     * @param RandomGenerator $rand
     * @return Response
     */
    public function soul_game_history(JSONRequestParser $parser, RandomGenerator $rand) {
        $lifes = $this->getUser()->getPastLifes()->getValues();
        usort($lifes, fn(CitizenRankingProxy $b, CitizenRankingProxy $a) =>
            ($a->getTown()->getSeason() ? $a->getTown()->getSeason()->getNumber() : 0) <=> ($b->getTown()->getSeason() ? $b->getTown()->getSeason()->getNumber() : 0) ?:
            ($a->getTown()->getSeason() ? $a->getTown()->getSeason()->getSubNumber() : 15) <=> ($b->getTown()->getSeason() ? $b->getTown()->getSeason()->getSubNumber() : 15) ?:
            ($a->getImportID() ?? 0) <=> ($b->getImportID() ?? 0) ?:
            $a->getID() <=> $b->getID()
        );
        return $this->render( 'ajax/soul/game_history.html.twig', $this->addDefaultTwigArgs('soul_me', [
            'towns' => $lifes
        ]));
    }

    /**
     * @Route("api/soul/tooltip", name="soul_tooltip")
     * @GateKeeperProfile(allow_during_attack=true)
     * @param int $id
     * @param HTMLService $html
     * @return Response
     */
    public function api_soul_tooltip(JSONRequestParser $parser, HTMLService $html, TimeKeeperService $timeKeeper) {
        $id = $parser->get("id");
        $user = $id ? $this->entity_manager->getRepository(User::class)->find($id) : null;

        if (!$user) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $desc = $this->entity_manager->getRepository(UserDescription::class)->findOneBy(['user' => $user]);
        $isFriend = $this->getUser()->getFriends()->contains($user);

        $is_dummy = $this->user_handler->hasRole($user,'ROLE_DUMMY');

        if ($user->getEmail() !== null && !str_contains($user->getEmail(), '@'))
            $is_dummy = true;

        $is_deleted = strstr($user->getName(), '$ deleted') !== false;

        return $this->render("ajax/soul/user_tooltip.html.twig", [
            'user' => $user,
            'userDesc' => $desc ? $html->prepareEmotes($desc->getText(), $this->getUser()) : null,
            'isFriend' => $isFriend,
            'dummy' => $is_dummy,
            'is_deleted' => $is_deleted,
            'during_attack' => $timeKeeper->isDuringAttack(),
            'crow'   => $this->user_handler->hasRole($user,'ROLE_CROW'),
            'admin'  => $this->user_handler->hasRole($user,'ROLE_ADMIN'),
            'super'  => $this->user_handler->hasRole($user,'ROLE_SUPER'),
            'oracle' => $this->user_handler->hasRole($user,'ROLE_ORACLE'),
            'anim'   => $this->user_handler->hasRole($user,'ROLE_ANIMAC'),
            'team'   => $this->user_handler->hasRole($user,'ROLE_TEAM'),
        ]);
    }

    /**
     * @Route("api/soul/friend/{action}", name="soul_friend_control")
     * @GateKeeperProfile(allow_during_attack=true)
     * @param int $action
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function api_friend_control(int $action, JSONRequestParser $parser) {
        if ($action !== 0 && $action !== 1) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $id = $parser->get("id");
        $user = $this->entity_manager->getRepository(User::class)->find($id ?? -1);

        if (!$user || $this->user_handler->hasRole($user, 'ROLE_DUMMY') || !str_contains($user->getEmail(), '@'))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($action && $this->user_handler->checkRelation($this->getUser(), $user,SocialRelation::SocialRelationTypeBlock, true))
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable);

        if ($action) {
            $this->getUser()->addFriend($user);
            $ignoreRelation = $this->entity_manager->getRepository( SocialRelation::class )->findOneBy( ['owner' => $this->getUser(), 'related' => $user, 'type' => SocialRelation::SocialRelationTypeNotInterested ] );
            if ($ignoreRelation) $this->entity_manager->remove( $ignoreRelation );
        } else
            $this->getUser()->removeFriend($user);

        $this->entity_manager->persist($this->getUser());
        $this->entity_manager->flush();

        if ($action && $user->getSetting( UserSetting::NotifyMeOnFriendRequest )) {
            $this->entity_manager->persist( $this->crow->createPM_friendNotification( $user, $this->getUser() ) );
            try { $this->entity_manager->flush(); } catch (\Throwable) {}
        }

        if($action){
            $this->addFlash("notice", $this->translator->trans("Du hast {username} zu deinen Kontakten hinzugefügt!", ['{username}' => $user], "soul"));
        } else {
            $this->addFlash("notice", $this->translator->trans("Du hast {username} aus deinen Kontakten gelöscht!", ['{username}' => $user], "soul"));
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/soul/ignore/{action}", name="soul_ignore_control")
     * @GateKeeperProfile(allow_during_attack=true)
     * @param int $action
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function api_ignore_control(int $action, JSONRequestParser $parser) {
        if ($action !== 0 && $action !== 1) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $id = $parser->get("id");
        $user = $this->entity_manager->getRepository(User::class)->find($id);

        if (!$user || $this->user_handler->hasRole($user, 'ROLE_DUMMY') || !str_contains($user->getEmail(), '@'))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($this->getUser()->getFriends()->contains($user) && $action)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($action)
            $this->entity_manager->persist((new SocialRelation())->setType(SocialRelation::SocialRelationTypeNotInterested)->setOwner($this->getUser())->setRelated($user));
        else $this->entity_manager->remove( $this->entity_manager->getRepository( SocialRelation::class )->findOneBy( ['owner' => $this->getUser(), 'related' => $user, 'type' => SocialRelation::SocialRelationTypeNotInterested ] ) );
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    public function reportCitizen( Citizen|CitizenRankingProxy $citizen, AdminReportSpecification $specification, JSONRequestParser $parser, RateLimiterFactory $reportToModerationLimiter ): Response {

        $user = $this->getUser();

        $proxy   = is_a( $citizen, CitizenRankingProxy::class ) ? $citizen : $citizen->getRankingEntry();
        $citizen = $proxy->getCitizen();

        if ($specification === AdminReportSpecification::CitizenAnnouncement && (!$citizen || $citizen->getTown()->getId() !== $this->getActiveCitizen()->getTown()->getId()) )
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable );

        $payload = match ($specification) {
            AdminReportSpecification::CitizenAnnouncement => $citizen?->getHome()->getDescription(),
            AdminReportSpecification::CitizenLastWords => $proxy->getLastWords(),
            AdminReportSpecification::CitizenTownComment => $proxy->getComment(),
            default => null
        };

        if (empty( $payload ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $reports = $this->entity_manager->getRepository(AdminReport::class)->findBy(['citizen' => $proxy, 'specification' => $specification->value]);
        foreach ($reports as $report)
            if ($report->getSourceUser()->getId() == $user->getId())
                return AjaxResponse::success();

        $report_count = count($reports) + 1;

        if (!$reportToModerationLimiter->create( $user->getId() )->consume()->isAccepted())
            return AjaxResponse::error( ErrorHelper::ErrorRateLimited);

        $details = $parser->trimmed('details');
        $newReport = (new AdminReport())
            ->setSourceUser($user)
            ->setReason( $parser->get_int('reason', 0, 0, 13) )
            ->setDetails( $details ?: null )
            ->setTs(new \DateTime('now'))
            ->setCitizen( $proxy )
            ->setSpecification( $specification );

        try {
            $this->entity_manager->persist($newReport);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        try {
            $this->crow->triggerExternalModNotification( match ($specification) {
                AdminReportSpecification::CitizenAnnouncement => "A citizen's home message has been reported.",
                AdminReportSpecification::CitizenLastWords => "A citizen's last words have been reported.",
                AdminReportSpecification::CitizenTownComment => "A town comment has been reported.",
                default => '[invalid]'
            }, $proxy, $newReport, "This is report #{$report_count}." );
        } catch (\Throwable $e) {}

        $message = $this->translator->trans('Du hast die Nachricht von {username} dem Raben gemeldet. Wer weiß, vielleicht wird {username} heute Nacht stääärben...', ['{username}' => '<span>' . $proxy->getUser()->getName() . '</span>'], 'game');
        $this->addFlash('notice', $message);
        return AjaxResponse::success( );
    }

    public function reportUser( User $reportedUser, JSONRequestParser $parser, RateLimiterFactory $reportToModerationLimiter ): Response {

        $user = $this->getUser();
        if ($user === $reportedUser)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $reports = $this->entity_manager->getRepository(AdminReport::class)->findBy(['user' => $reportedUser]);
        foreach ($reports as $report)
            if ($report->getSourceUser()->getId() == $user->getId())
                return AjaxResponse::success();

        $report_count = count($reports) + 1;

        if (!$reportToModerationLimiter->create( $user->getId() )->consume( $report_count <= 1 ? 2 : 1 )->isAccepted())
            return AjaxResponse::error( ErrorHelper::ErrorRateLimited);

        $details = $parser->trimmed('details');
        $newReport = (new AdminReport())
            ->setSourceUser($user)
            ->setReason( $parser->get_int('reason', 0, 0, 13) )
            ->setDetails( $details ?: null )
            ->setTs(new \DateTime('now'))
            ->setUser( $reportedUser );

        try {
            $this->entity_manager->persist($newReport);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        try {
            $this->crow->triggerExternalModNotification( 'A user account has been reported.', $reportedUser, $newReport, "This is report #{$report_count}." );
        } catch (\Throwable $e) {}

        $message = $this->translator->trans('Du hast {username} dem Raben gemeldet. Wer weiß, vielleicht wird {username} heute Nacht stääärben...', ['{username}' => '<span>' . $reportedUser->getName() . '</span>'], 'game');
        $this->addFlash('notice', $message);
        return AjaxResponse::success( );
    }

}
