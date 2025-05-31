<?php

namespace App\Controller\REST\User;

use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Controller\CustomAbstractCoreController;
use App\Entity\AdminReport;
use App\Entity\BlackboardEdit;
use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\ExternalApp;
use App\Entity\ForumUsagePermissions;
use App\Entity\GlobalPrivateMessage;
use App\Entity\Post;
use App\Entity\PrivateMessage;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserGroupAssociation;
use App\Enum\AdminReportSpecification;
use App\Service\Actions\Mercure\BroadcastViaMercureAction;
use App\Service\CrowService;
use App\Service\EventProxyService;
use App\Service\JSONRequestParser;
use App\Service\PermissionHandler;
use App\Service\RandomGenerator;
use App\Service\RateLimitingFactoryProvider;
use App\Service\TimeKeeperService;
use App\Service\User\UserCapabilityService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;
use Throwable;

#[Route(path: '/rest/v1/user/commons', name: 'rest_user_commons_', condition: "request.headers.get('Accept') === 'application/json'")]
#[GateKeeperProfile('skip')]
class CommonsController extends CustomAbstractCoreController
{
    /**
     * @param Packages $asset
     * @return JsonResponse
     */
    #[Route(path: '', name: 'base', methods: ['GET'])]
    public function index(Packages $asset): JsonResponse {

        return new JsonResponse([
            'apps' => [
                'list' => [
                    'headline' => $this->translator->trans('Verzeichnis', [], 'global'),
                    'description' => $this->translator->trans('Die folgenden Links verweisen alle auf Web- und Fanseiten, die von Spielern kreiert wurden. Ihr findet auf ihnen zusätzliche Informationen oder nützliche Tools für das Spiel:', [], 'global'),
                    'maintenance' => $this->translator->trans('Aufgrund technischer Probleme ist der Funktionsumfang dieser externen Anwendung aktuell eingeschränkt.', [], 'global'),
                    'testMode' => $this->translator->trans('Testbetrieb', [], 'admin'),
                    'sk' => $this->translator->trans('SK: {sk}', [], 'global'),

                    'icon' => $asset->getUrl('build/images/icons/small_archive.gif'),
                    'no_icon' => $asset->getUrl('build/images/apps/null.gif'),
                ],

                'details' => [
                    'notice' => $this->translator->trans('Achtung! Der von dir ausgewählte Link, leitet dich auf eine <strong>nicht-offizielle Seite</strong> weiter (sprich: eine Fan- oder Spielerseite).', [], 'global'),
                    'warning_pk_1' => $this->translator->trans('Du hast keine Externe ID.', [], 'global'),
                    'warning_pk_2' => $this->translator->trans('Ohne diese kannst du keine externen Apps nutzen. Um eine Externe ID zu setzen, gehe in deine Seele > Einstellungen und generiere eine ID.', [], 'global'),
                    'warning_pw_1' => $this->translator->trans('Du darfst für diese Seite unter keinen Umständen dein MyHordes-Passwort verwenden!', [], 'global'),
                    'warning_pw_2' => $this->translator->trans('Auch dann nicht, wenn du dazu aufgefordert wirst!', [], 'global'),
                    'contact' => $this->translator->trans('Wenn du Fragen oder Probleme mit der Seite hast, kontaktiere bitte {contact}.', [], 'global'),
                    'notice_secure_1' => $this->translator->trans('Diese Seite ist durch eine verschlüsselte Verbindung mit dem Spiel verbunden:', [], 'global'),
                    'notice_secure_2' => $this->translator->trans('Wenn du hier unten auf "Weiter" klickst wird dein Spielername automatisch erkannt, so dass du nichts weiteres mehr eingeben musst.', [], 'global'),
                    'warning_maintenance_1' => $this->translator->trans('Diese Anwendung befindet sich im Wartungsmodus.', [], 'global'),
                    'warning_maintenance_2' => $this->translator->trans('Sei vorsichtig, das Betreten geschieht auf eigene Gefahr...', [], 'global'),
                    'confirm' => $this->translator->trans('Weiter zu {app}', [], 'global'),
                    'cancel' => $this->translator->trans('Schön hier im Warmen bleiben', [], 'global'),

                    'dev' => [
                        'help' => $this->translator->trans('Hilfe', [], 'global'),
                        'save' => $this->translator->trans('Speichern', [], 'global'),
                        'headline' => $this->translator->trans('Entwickler-Einstellungen', [], 'global'),
                        'description' => $this->translator->trans('Als Entwickler dieser Webseite kannst du hier einige Einstellungen anpassen.', [], 'global'),
                        'fields' => [
                            'email' => [
                                'title' => $this->translator->trans('Kontakt', [], 'admin'),
                                'help' => [
                                    $this->translator->trans('Diese E-Mail Adresse wird als Kontakt-Adresse angezeigt.', [], 'global'),
                                    $this->translator->trans('Alternativ zu einer E-Mail Adresse kannst du hier auch eine URL eintragen, auf der Spieler eine Kontaktmöglichkeit zu dir finden.', [], 'global'),
                                ],
                            ],
                            'url' => [
                                'title' => $this->translator->trans('URL', [], 'admin'),
                                'help' => [
                                    $this->translator->trans('Dies ist die URL, zu der Spieler weitergeleitet werden, wenn sie auf den Link klicken.', [], 'global'),
                                ],
                            ],
                            'dev_url' => [
                                'title' => $this->translator->trans('Entwickler-URL', [], 'admin'),
                                'help' => [
                                    $this->translator->trans('Hier kannst du eine URL für eine Test- oder Entwicklungsumgebung hinterlegen (z.B. "localhost"). Du siehst dann einen zusätzlichen Button, der dich auf diese Umgebung weiterleitet. Diese URL sowie der zusätzliche Button sind ausschließlich für dich sichtbar.', [], 'global'),
                                ],
                            ],
                            'sk' => [
                                'title' => $this->translator->trans('Schlüssel', [], 'admin'),
                                'help' => [
                                    $this->translator->trans('Mit diesem Schlüssel authentifiziert sich deine Webseite gegenüber MyHordes.', [], 'global'),
                                    $this->translator->trans('Du darfst ihn keinesfalls weitergeben oder öffentlich verfügbar machen, beispielsweise in einem Code-Repository.', [], 'global'),
                                    $this->translator->trans('Wenn du den Verdacht hast, dass dein Schlüssel kompromittiert wurde, kannst du einen neuen Schlüssel anfordern.', [], 'global'),
                                    $this->translator->trans('Schlüssel regenerieren', [], 'admin'),
                                    $this->translator->trans('Wenn du deinen Schlüssel änderst, musst du diesen in deiner Anwendung austauschen. Bitte bestätige, dass du den Schlüssel jetzt austauschen möchtest, indem du die Zahl {num} eingibst.', [], 'admin'),
                                ],
                            ],
                        ]
                    ]
                ]
            ],

            'clock' => [
                'panda' => $this->translator->trans('Pandämonium', [], 'game'),
                'day' => $this->translator->trans('Tag {day}', [], 'game'),
                'gametime' => $this->translator->trans('Spielzeit', [], 'game'),
                'attack' => $this->translator->trans('Zeit bis zum nächsten Zombieangriff', [], 'game'),
            ],

            'logout' => [
                'url' => $this->generateUrl('auto_logout', [], UrlGeneratorInterface::ABSOLUTE_URL),
                'title' => $this->translator->trans('Logout', [], 'soul')
            ]
        ]);
    }

    protected function decodeUrl(?string $url): ?string {
        if ($url === null) return $url;
        while (($a = strpos( $url, '{' )) !== false && ($b = strpos( $url, '}' )) && $a < $b) {

            [$word, $options] = array_merge(explode(':', substr( $url, $a+1, $b - $a - 1 ), 2), [null]);

            $replacement = '';
            switch ($word) {
                case 'lang':
                    if (!$options) $replacement = $this->getUser()->getLanguage() ?? 'en';
                    else {
                        foreach (explode('|', $options) as $option) {
                            [$lang, $value] = array_merge(explode(';', $option, 2), [null]);
                            if ($lang === '*' || $this->getUser()->getLanguage() === $lang) {
                                $replacement = $value ?? $lang;
                                break;
                            }
                        }
                    }
                    break;
            }

            $url = substr( $url, 0, $a ) . $replacement . substr( $url, $b + 1 );
        }

        return str_replace(['{','}'], '', $url);
    }

    protected function renderApp(ExternalApp $app): array {
        return [
            'id' => $app->getId(),
            'name' => $app->getName(),
            'icon' => $app->getImageName() ? $this->generateUrl('app_web_app_icon', [ 'aid' => $app->getId(), 'name' =>  $app->getImageName(), 'ext' => $app->getImageFormat() ]) : null,
            'wiki' => $app->isWiki(),
            'testing' => $app->getTesting(),
            'auth' => ($app->getSecret() && !$app->getLinkOnly()),
            'pk' => ($app->getSecret() && !$app->getLinkOnly()) ? $this->getUser()->getExternalId() : null,
            'maintenance' => $app->getMaintenance() <> 0,
            'url' => $this->decodeUrl( $app->getUrl() ),
            'contact' => [
                'label' => $app->getContact() ?: null,
                'uri' => empty($app->getContact()) ? null : ( str_contains( $app->getContact(), '@' ) ? "mailto:{$app->getContact()}" : $app->getContact() ),
            ],
            'dev' => $app->getOwner()?->getId() === $this->getUser()->getId() ? [
                'sk' => $app->getSecret(),
                'url' => $this->decodeUrl( $app->getDevurl() ),
            ] : null,
        ];
    }

    /**
     * @param EntityManagerInterface $em
     * @param UserCapabilityService $capability
     * @return JsonResponse
     */
    #[Route(path: '/apps', name: 'app_list', methods: ['GET'])]
    public function app_list(EntityManagerInterface $em, UserCapabilityService $capability): JsonResponse {

        if (!$this->getUser() || !$capability->hasRole( $this->getUser(), 'ROLE_USER' ))
            return new JsonResponse([]);
        $isSubAdmin = $capability->hasRole( $this->getUser(), 'ROLE_SUB_ADMIN' );

        $criteria = (new Criteria())
            ->where( Criteria::expr()->eq('active', true) );

        if (!$isSubAdmin)
            $criteria->andWhere( Criteria::expr()->orX(
                Criteria::expr()->eq('testing', false),
                Criteria::expr()->eq('owner', $this->getUser()),
            ) );

        return new JsonResponse([
            'apps' => $em->getRepository(ExternalApp::class)->matching( $criteria )
                ->map( fn(ExternalApp $app) => $this->renderApp( $app ) )->toArray()
        ]);

    }

    /**
     * @param EntityManagerInterface $em
     * @param UserCapabilityService $capability
     * @return JsonResponse
     */
    #[Route(path: '/apps/{id<\d+>}', name: 'app_single', methods: ['GET'])]
    public function app_single(ExternalApp $app, UserCapabilityService $capability): JsonResponse {

        if (!$this->getUser() || !$capability->hasRole( $this->getUser(), 'ROLE_USER' ))
            return new JsonResponse([]);
        $isSubAdmin = $capability->hasRole( $this->getUser(), 'ROLE_SUB_ADMIN' );

        if (!$app->getActive() || ($app->getTesting() && $app->getOwner()?->getId() !== $this->getUser()->getId()))
            return new JsonResponse([], Response::HTTP_NOT_FOUND);


        return new JsonResponse([
            'app' => $this->renderApp( $app ),
        ]);

    }

    /**
     * @param ExternalApp $app
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @param HubInterface $hub
     * @return JsonResponse
     */
    #[Route(path: '/apps/{id<\d+>}', name: 'app_patch', methods: ['PATCH'])]
    public function app_patch(ExternalApp $app, EntityManagerInterface $em, JSONRequestParser $parser, BroadcastViaMercureAction $broadcast): JsonResponse {

        if ($app->getOwner() === null || $app->getOwner()->getId() !== $this->getUser()?->getId())
            return new JsonResponse([], Response::HTTP_FORBIDDEN);

        if (!$parser->has_all( ['contact','url'], true ))
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        $violations = Validation::createValidator()->validate( array_merge($parser->all( true ), [
            'url' => preg_replace('/\{.*?\}/', 'SYMBOL', $parser->get('url')),
            'dev_url' => preg_replace('/\{.*?\}/', 'SYMBOL', $parser->get('dev_url', '')),
        ]), new Collection([
            'url' => [ new Url( ['relativeProtocol' => false, 'protocols' => ['http', 'https'], 'message' => 'a' ] ) ],
            'dev_url' => [
                new AtLeastOneOf([
                    new Url( ['relativeProtocol' => false, 'protocols' => ['http', 'https'], 'message' => 'a' ] ),
                    new Blank( ['message' => 'a' ] )
                ])
            ],
            'contact' => [
                new AtLeastOneOf([
                    new Url( ['relativeProtocol' => false, 'protocols' => ['http', 'https'], 'message' => 'a' ] ),
                    new Email( ['message' => 'v'])
                ])
            ],
            'sk' => [  ],
            'key' => [  ],
        ]) );

        if ($violations->count() > 0) return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        $app->setUrl( $parser->trimmed('url') )->setContact( $parser->trimmed('contact') )->setDevurl( $parser->trimmed('dev_url') ?: null );

        $em->persist( $app->setUrl( $parser->trimmed('url') )->setContact( $parser->trimmed('contact') )->setDevurl( $parser->trimmed('dev_url') ?: null ) );
        $em->flush();

        ($broadcast)(
            'external-app-update',
            [ 'app' => $app->getId() ],
            public: !$app->getTesting(),
            users: $app->getOwner()
        );

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param ExternalApp $app
     * @param EntityManagerInterface $em
     * @param RandomGenerator $rand
     * @return JsonResponse
     */
    #[Route(path: '/apps/{id<\d+>}', name: 'app_put', methods: ['PUT'])]
    public function app_put(ExternalApp $app, EntityManagerInterface $em, RandomGenerator $rand): JsonResponse {
        if ($app->getLinkOnly() || $app->getOwner() === null || $app->getOwner()->getId() !== $this->getUser()?->getId())
            return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $s = '';
        for ($i = 0; $i < 32; $i++) $s .= $rand->pick(['0','1','2','3','4','5','6','7','8','9','a','b','c','d','e','f']);
        $app->setSecret( $s );

        $em->persist( $app->setSecret( $s ) );
        $em->flush();

        return new JsonResponse(['sk' => $s]);
    }

    /**
     * @param UserCapabilityService $capability
     * @return JsonResponse
     */
    #[Route(path: '/mods', name: 'mod_list', methods: ['GET'])]
    public function mod_list(UserCapabilityService $capability): JsonResponse {

        if (!$this->getUser() || !$capability->hasAnyRole( $this->getUser(), ['ROLE_CROW', 'ROLE_ELEVATED'] ))
            return new JsonResponse([]);

        $crow = $capability->hasRole( $this->getUser(), 'ROLE_CROW' );

        $data = $crow
            ? AdminActionController::getAdminActions()
            : AdminActionController::getCommunityActions();

        return new JsonResponse([
            'cat' => $crow ? $this->translator->trans('Moderation', [], 'global') : $this->translator->trans('Community-Tools', [], 'global'),
            'links' => array_map( fn(array $entry) => [
                'name' => $entry['name'],
                'url' => $this->generateUrl($entry['route']),
            ], $data)
        ]);

    }

    /**
     * @param TimeKeeperService $timeKeeper
     * @return JsonResponse
     */
    #[Route(path: '/clock', name: 'get_clock', methods: ['GET'])]
    public function clock(TimeKeeperService $timeKeeper): JsonResponse {

        if (!$this->getUser())
            return new JsonResponse([]);

        $town = $this->getUser()->getActiveCitizen()?->getTown();

        $during_attack = $timeKeeper->isDuringAttack();
        if (!$during_attack && !$this->getUser()->getActiveCitizen()?->getAlive())
            $town = null;

        return new JsonResponse([
            'id'        => $town?->getId() ?? null,
            'type'      => $town?->getType()?->getName() ?? null,
            'desc'      => $town?->getName() ?? $this->translator->trans('Worauf warten Sie noch?', [], 'ghost'),
            'day'       => $during_attack ? -1 : ($town?->getDay() ?? -1),
            'timestamp' => (new DateTime('now'))->getTimestamp(),
            'attack'    => $timeKeeper->secondsUntilNextAttack(null, true),
            'offset'    => timezone_offset_get( timezone_open( date_default_timezone_get ( ) ), new DateTime() ),
            'show'      =>
                (!$this->getUser()->getActiveCitizen() && !$during_attack) ||
                ($this->getUser()->getActiveCitizen() && ($this->getUser()->getActiveCitizen()->getAlive() || $during_attack))
        ]);

    }
}
