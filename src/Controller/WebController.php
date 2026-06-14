<?php

namespace App\Controller;

use App\Annotations\GateKeeperProfile;
use App\Entity\MarketingCampaign;
use App\Entity\OfficialGroup;
use App\Enum\Configuration\MyHordesSetting;
use App\Enum\OfficialGroupSemantic;
use App\Response\AjaxResponse;
use App\Service\Actions\Security\GenerateMercureToken;
use App\Service\Actions\Security\RegisterNewTokenAction;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\EternalTwinHandler;
use App\Service\HookExecutor;
use App\Service\InventoryHandler;
use App\Service\Media\ImageService;
use App\Service\TimeKeeperService;
use Doctrine\ORM\EntityManagerInterface;
use App\Translation\T;
use Shivas\VersioningBundle\Service\VersionManagerInterface as VersionManager;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class WebController
 * @package App\Controller
 */
#[GateKeeperProfile(allow_during_attack: true)]
class WebController extends CustomAbstractController
{
    // Format:
    // [ type,icon, name ], with type: 0 => Current team, 1 => Support, 2 => Inactive
    public static array $devs = [
        [0, 'icon_mh_admin.gif', 'Benjamin "<i>Brainbox</i>" Behrendt'],
        [0, 'icon_mh_admin.gif', 'Ludovic "<i>Cheh\'Tan</i>" Le Brech'],
        [0, 'icon_mh_admin.gif', 'Adrien "<i>Adri</i>" Boitelle'],
        [0, 'icon_mh_admin.gif', 'Connor "<i>Dylan57</i>" Ottermann'],
        [0, 'small_dev.png', 'Paul "<i>CountCount</i>" Bruhn'],
        //[1, 'icon_mh_team.gif', 'Ryan "<i>Nayr</i>" Nayrovic'],
        [2, 'small_dev.png', 'Niklas "<i>Choreas</i>" Kosanke'],
        [2, 'small_dev.png', 'Christopher "<i>Vander</i>" Chalfant'],
    ];

    public static array $supporters = [
        'MisterD', 'Mondi', 'Schrödinger', 'Kitsune',
        'MOTZI', 'devwwm', 'alonsopor', 'Termineitron',
        'Rikrdo', 'Valedres', 'Yaken', 'Finne', 'Aeon',
        'Elara', 'MisterSimple', 'Eragony', 'Tristana', 'Bigonoud', 'Bacchus', 'unukun',
        'Docteur', 'Nayr'
    ];

    private VersionManager $version_manager;
    private KernelInterface $kernel;

    private RegisterNewTokenAction $tokenizer;

    public function __construct(VersionManager $v, KernelInterface $k, EntityManagerInterface $e, TranslatorInterface $translator, ConfMaster $conf, TimeKeeperService $tk, CitizenHandler $ch, InventoryHandler $ih, RegisterNewTokenAction $tt, HookExecutor $hookExecutor)
    {
        parent::__construct($conf, $e, $tk, $ch, $ih, $translator, $hookExecutor);
        $this->version_manager = $v;
        $this->kernel = $k;
        $this->tokenizer = $tt;
    }

    private function handleDomainRedirection(): ?Response {
        $redirect = $this->conf->getGlobalConf()->get(MyHordesSetting::DomainRedirect);
        $request = Request::createFromGlobals();

        $current_host = "{$request->getHttpHost()}{$request->getBasePath()}";
        foreach ($redirect as $entry)
            if ($entry['principal'] === $current_host && ($url = $entry[$this->getUserLanguage()] ?? $entry['*'] ?? null))
                return new RedirectResponse( "{$url}{$request->getPathInfo()}", 308 );

        return null;
    }

    private function render_web_framework(Request $request, GenerateMercureToken $mercure, string $ajax_landing, $allow_attract_page = false): Response {
        try {
            $version = $this->version_manager->getVersion();
            $is_debug_version =
                ($version->getMajor() < 1) ||
                ($version->getPreRelease() && !(
                    $version->getPreRelease()->toString() === 'rc' || str_starts_with($version->getPreRelease()->toString(), 'rc.')
                ));
        } catch (\Exception $e) {
            $is_debug_version = false;
            $version = null;
        }

        $devs = self::$devs;
        shuffle($devs);

        $supporters = self::$supporters;
        shuffle($supporters);

        $mercure_token = ($mercure)(renew_url: $this->generateUrl('rest_user_security_renew_core_token', [], UrlGeneratorInterface::ABSOLUTE_URL));

        return $this->render( ($this->getUser() || !$allow_attract_page) ? 'web/framework.html.twig' : 'web/attract.html.twig', [
            'ticket' => ($this->tokenizer)($request),
            'mercure_token' => $mercure_token,
            'version' => $version, 'debug' => $is_debug_version, 'env' => $this->kernel->getEnvironment(),
            'status_page' => $this->conf->getGlobalConf()->get(MyHordesSetting::StatusPageUrl),
            'devs' => array_map(function($dev) {
                $dev[3] = match ($dev[1]) {
                    'code' => T::__('Programmierung', 'global'),
                    'users' => T::__('Community-Management', 'global'),
                    default => '',
                };
                return $dev;
            }, $devs),
            'supporters' => $supporters,
            'ajax_landing' => $ajax_landing,
            'langs' => $this->allLangs
        ] );
    }

    public function render_error_framework(FlattenException $exception, KernelInterface $kernel, ?DebugLoggerInterface $logger = null): Response {
        foreach (Request::createFromGlobals()->getAcceptableContentTypes() as $type)
            switch ($type) {
                case 'application/json':
                    return AjaxResponse::error( ErrorHelper::ErrorInternalError, $kernel->getEnvironment() === 'dev' || $kernel->getEnvironment() !== 'local' ? [
                        'message' => $exception->getMessage(),
                        'trace' => $exception->getTrace()
                    ] : [] );
            }

        try {
            $version = $this->version_manager->getVersion();
            $is_debug_version =
                ($version->getMajor() < 1) ||
                ($version->getPreRelease() && !(
                        $version->getPreRelease()->toString() === 'rc' || str_starts_with($version->getPreRelease()->toString(), 'rc.')
                    ));
        } catch (\Exception $e) {
            $is_debug_version = false;
            $version = null;
        }

        $devs = self::$devs;
        shuffle($devs);

        $supporters = self::$supporters;
        shuffle($supporters);

        $support_groups = $this->entity_manager->getRepository(OfficialGroup::class)->findBy(['lang' => $this->getUserLanguage(), 'semantic' => OfficialGroupSemantic::Support]);

        return $this->render(  'web/error_page.twig', [
            'version' => $version, 'debug' => $is_debug_version, 'env' => $this->kernel->getEnvironment(),
            'status_page' => $this->conf->getGlobalConf()->get(MyHordesSetting::StatusPageUrl),
            'devs' => array_map(function($dev) {
                $dev[3] = match ($dev[1]) {
                    'code' => T::__('Programmierung', 'global'),
                    'users' => T::__('Community-Management', 'global'),
                    default => '',
                };
                return $dev;
            }, $devs),
            'supporters' => $supporters,
            'ajax_landing' => '',
            'langs' => $this->allLangs,
            'exception' => ($kernel->getEnvironment() === 'dev' || $kernel->getEnvironment() !== 'local') ? $exception : 'Internal Error.',
            'support' => count($support_groups) === 1 ? $support_groups[0] : null

        ] );
    }

    /**
     * @param Request $r
     * @return Response
     */
    #[Route(path: '/', name: 'home')]
    public function framework(Request $r, GenerateMercureToken $mercure): Response
    {
        return $this->handleDomainRedirection() ?? $this->render_web_framework($r, $mercure, $this->generateUrl('initial_landing'), true);
    }


    /**
     * @return Response
     */
    #[Route(path: '/swagger', name: 'swagger_legacy')]
    public function swagger(): Response
    {
        return $this->redirectToRoute('swagger', status: 301);
    }

    #[Route(path: '/docs', name: 'swagger')]
    public function docs(): Response
    {
        return $this->render('web/swagger.html.twig');
    }

    /**
     * @param ParameterBagInterface $params
     * @param string $document
     * @param string|null $lang
     * @return Response
     */
    #[Route(path: '/legal/{document}', name: 'legal_doc_default')]
    #[Route(path: '/legal/{lang}/{document}', name: 'legal_doc_lang')]
    public function legal(ParameterBagInterface $params, string $document, ?string $lang = null): Response
    {
        $lang = $lang ?? $this->getUserLanguage();

        if (!in_array($document, ['imprint','privacy-policy','tos'])) return $this->redirect($this->generateUrl('home'));
        if (!in_array($lang, $this->generatedLangsCodes)) return $this->redirect($this->generateUrl('legal_doc_lang', [
            'lang' => $this->getUserLanguage(),
            'document' => $document
        ]));

        $doc_path = "{$params->get('kernel.project_dir')}/var/documents/$document";
        $content = null;
        if (file_exists( "$doc_path/$lang.html" )) $content = file_get_contents( "$doc_path/$lang.html" );
        elseif (file_exists( "$doc_path/en.html" ))
            $content = file_get_contents("$doc_path/en.html");
        else foreach ( $this->generatedLangsCodes as $check_lang) {
            if (file_exists( "$doc_path/$check_lang.html" )) {
                $content = file_get_contents("$doc_path/$check_lang.html");
                break;
            }
        }

        return $this->render('web/legal.html.twig', [
            'content' => $content,
            'langs' => $this->generatedLangsCodes,
            'document' => $document,
            'toc' => $document === 'tos' || $document === 'privacy-policy'
        ]);
    }

    /**
     * @param string $name
     * @param SessionInterface $s
     * @return Response
     */
    #[Route(path: '/ref/{name}')]
    public function refer_incoming(Request $rq, string $name, SessionInterface $s, GenerateMercureToken $mercure): Response
    {
        if ($r = $this->handleDomainRedirection()) return $r;
        $s->set('refer', $name);
        return $this->render_web_framework($rq, $mercure, $this->generateUrl('public_register'));
    }

    /**
     * @param string|null $com
     * @return Response
     */
    #[Route(path: '/pm/{com}', name: 'home_pm')]
    public function standalone_pm(?string $com = null): Response
    {
        if ($r = $this->handleDomainRedirection()) return $r;
        if (!$this->isGranted('ROLE_USER'))
            return $this->redirect($this->generateUrl('home'));

        return $this->render( 'web/pm-host.html.twig', $com ? ['command' => $com, 'langs' => $this->allLangs] : ['langs' => $this->allLangs] );
    }

    /**
     * @param int $id
     * @return Response
     */
    #[Route(path: '/pm/group/{id<\d+>}', name: 'home_pm_group_id', priority: 1)]
    public function standalone_pm_gid(int $id): Response
    {
        if ($r = $this->handleDomainRedirection()) return $r;
        if (!$this->isGranted('ROLE_USER'))
            return $this->redirect($this->generateUrl('home'));

        $group = $this->entity_manager->getRepository(OfficialGroup::class)->find( $id );
        if (!$group) return $this->redirect($this->generateUrl('home'));

        return $this->render( 'web/pm-host.html.twig', ['command' => "oglink_$id", 'langs' => $this->allLangs] );
    }

    /**
     * @param string|null $lang
     * @param string $semantic
     * @return Response
     */
    #[Route(path: '/pm/group/{semantic}', name: 'home_pm_group_sem')]
    #[Route(path: '/pm/group/{lang}/{semantic}', name: 'home_pm_group_sem_lang')]
    public function standalone_pm_sem(string $semantic, ?string $lang = null): Response
    {
        if ($r = $this->handleDomainRedirection()) return $r;
        if (!$this->isGranted('ROLE_USER'))
            return $this->redirect($this->generateUrl('home'));

        $lang = $lang ?? $this->getUserLanguage();
        if (!in_array( $lang, $this->allLangsCodes ))
            return $this->redirect($this->generateUrl('home'));

        $group = null;
        switch ($semantic) {
            case 'support':
                $group = $this->entity_manager->getRepository(OfficialGroup::class)->findOneBy(['lang' => $lang, 'semantic' => OfficialGroupSemantic::Support]);
                break;
            case 'moderation':
                $group = $this->entity_manager->getRepository(OfficialGroup::class)->findOneBy(['lang' => $lang, 'semantic' => OfficialGroupSemantic::Moderation]);
                break;
            case 'animaction':
                $group = $this->entity_manager->getRepository(OfficialGroup::class)->findOneBy(['lang' => $lang, 'semantic' => OfficialGroupSemantic::Animaction]);
                break;
            case 'oracle':
                $group = $this->entity_manager->getRepository(OfficialGroup::class)->findOneBy(['lang' => $lang, 'semantic' => OfficialGroupSemantic::Oracle]);
                break;
        }
        if (!$group) return $this->redirect($this->generateUrl('home'));

        return $this->render( 'web/pm-host.html.twig', ['command' => "oglink_{$group->getId()}", 'langs' => $this->allLangs] );
    }

    /**
     * @param SessionInterface $session
     * @return Response
     */
    #[Route(path: '/r/ach', name: 'revert_ach_language')]
    #[GateKeeperProfile('skip')]
    public function rescue_mode_lang_ach( SessionInterface $session ): Response
    {
        if (!$this->isGranted('ROLE_USER'))
            return $this->redirect($this->generateUrl('home'));

        if (!($user = $this->getUser())) return $this->redirect($this->generateUrl('home'));

        if ($user->getLanguage() === 'ach') {
            $user->setLanguage($this->getUserLanguage(true));

            $this->entity_manager->persist($user);
            $this->entity_manager->flush();
            $session->set('_user_lang',$user->getLanguage());
        }

        return $this->redirect($this->generateUrl('home'));
    }

    /**
     * @param SessionInterface $session
     * @return Response
     */
    #[Route(path: '/r/pivot/{id}', name: 'pivot_user_account')]
    #[GateKeeperProfile('skip')]
    public function pivot_user_account( SessionInterface $session ): Response
    {
        if (!$this->isGranted('ROLE_USER'))
            return $this->redirect($this->generateUrl('home'));

        if (!($user = $this->getUser())) return $this->redirect($this->generateUrl('home'));

        if ($user->getLanguage() === 'ach') {
            $user->setLanguage($this->getUserLanguage(true));

            $this->entity_manager->persist($user);
            $this->entity_manager->flush();
            $session->set('_user_lang',$user->getLanguage());
        }

        return $this->redirect($this->generateUrl('home'));
    }

    /**
     * @param EternalTwinHandler $etwin
     * @param SessionInterface $session
     * @param string $state
     * @param bool $remember
     * @return Response
     */
    #[Route(path: 'gateway/eternal-twin/{state}', name: 'gateway-etwin', defaults: ['remember' => false])]
    #[Route(path: 'gateway/eternal-twin-rm/{state}', name: 'gateway-remember-etwin', defaults: ['remember' => true])]
    public function gateway_etwin(EternalTwinHandler $etwin, SessionInterface $session, string $state = 'etwin-login', bool $remember = false): Response {
        if (!$etwin->isReady()) return new Response('Error: No gateway to EternalTwin is configured.');
        $session->set('_etwin_rm', $remember);
        $request = Request::createFromGlobals();
        return new RedirectResponse($etwin->createAuthorizationRequest( "$state#{$request->getHost()}{$request->getBaseUrl()}"));
    }

    /**
     * @param EternalTwinHandler $etwin
     * @param ConfMaster $conf
     * @return Response
     */
    #[Route(path: 'gateway/eternal-twin-registration', name: 'gateway-etwin-reg')]
    public function gateway_etwin_reg(EternalTwinHandler $etwin, ConfMaster $conf): Response {
        if (!$etwin->isReady()) return new Response('Error: No gateway to EternalTwin is configured.');
        return new RedirectResponse( $conf->getGlobalConf()->get( MyHordesSetting::EternalTwinReg ) );
    }

    /**
     * @param ConfMaster $conf
     * @return Response
     */
    #[Route(path: '/twinoid', name: 'twinoid_auth_endpoint')]
    public function framework_import(Request $r, ConfMaster $conf, GenerateMercureToken $mercure): Response
    {
        $request = Request::createFromGlobals();
        $state = explode('#',$request->query->get('state'));
        $target_domain = count($state) === 1 ? null : $state[1];
        $state = $state[0];
        $code  = $request->query->get('code');
        $error = $request->query->get('error');

        if ($error) return new Response('Error: No code obtained! Reported error is "' . htmlentities($error) . '".');
        if (empty( $code )) return new Response('Error: No code obtained!');

        if ($target_domain !== null && Request::createFromGlobals()->getHost() !== null && !str_starts_with($target_domain, $request->getHost())) {
            foreach ($conf->getGlobalConf()->get(MyHordesSetting::Domains) as $domain)
                if (str_starts_with( $target_domain, $domain )) return new RedirectResponse( "https://{$target_domain}/twinoid?code={$code}&state={$state}" );
            return new Response('Error: Untrusted domain!');
        }

        return match ($state) {
            'import' => $this->render_web_framework($r, $mercure, $this->generateUrl('soul_import', ['code' => $code])),
            'etwin-login' => $this->render_web_framework($r, $mercure, $this->generateUrl('etwin_login', ['code' => $code])),
            'etwin-update' => $this->render_web_framework($r, $mercure, $this->generateUrl('soul_settings', ['code' => $code])),
            default => new Response('Error: Invalid state, can\'t redirect!'),
        };


    }

    /**
     * @param string $ajax
     * @param Request $q
     * @param GenerateMercureToken $mercure
     * @return Response
     */
    #[Route(path: '/jx/{ajax}', requirements: ['ajax' => '.+'], condition: '!request.isXmlHttpRequest()')]
    public function loader(string $ajax, Request $q, GenerateMercureToken $mercure): Response
    {
        if ($q->query->count() > 0) {
            $bag = [];
            foreach ($q->query as $p => $v)
                $bag[] = urlencode($p) . '=' . urlencode($v);
            $bag = '?' . implode('&',$bag);
        } else $bag = '';

        $whitelisted = function($s): bool {
            if (in_array( $s, [
                'public/welcome',
                'public/about',
                'public/news'
            ] )) return true;

            foreach ([
                'help',
                'public/changelog'
            ] as $item)
                if ($s === $item || str_starts_with( $s, "$item/" )) return true;

            return false;
        };

        return $this->handleDomainRedirection() ?? $this->render_web_framework($q, $mercure, $q->getBasePath() . "/jx/{$ajax}{$bag}", $whitelisted( $ajax ));
    }

    /**
     * @param string $url
     * @return Response
     */
    #[Route(path: '/cdn/{url}', requirements: ['url' => '.+'], condition: '!request.isXmlHttpRequest()')]
    #[GateKeeperProfile('skip')]
    public function cdn_fallback(string $url): Response {
        return new Response(
            "File not found: cdn/{$url}",
            Response::HTTP_NOT_FOUND,
            ['content-type' => 'text/plain']
        );
    }


    /**
     * @param MarketingCampaign|null $campaign
     * @param SessionInterface $session
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route(path: '/c/{campaign_slug}', name: 'campaign_redirect', methods: ['GET'], condition: '!request.isXmlHttpRequest()')]
    public function redirect_campaign(
        #[MapEntity(mapping: ['campaign_slug' => 'slug'])] ?MarketingCampaign $campaign,
        SessionInterface $session,
        EntityManagerInterface $em
    ): Response {
        if ($campaign && !$this->getUser()) {
            $session->set('campaign', $campaign->registerClick()->getId());
            try {
                $em->persist($campaign);
                $em->flush();
            } catch (\Throwable $e) {}
        }
        return $this->redirectToRoute('home');
    }

}
