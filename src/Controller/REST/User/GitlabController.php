<?php

namespace App\Controller\REST\User;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\AccountRestriction;
use App\Enum\Configuration\MyHordesSetting;
use App\Messages\Gitlab\GitlabCreateIssueMessage;
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

#[Route(path: '/rest/v1/user/issues', name: 'rest_user_issues_', condition: "request.headers.get('Accept') === 'application/json'")]
#[GateKeeperProfile('skip')]
class GitlabController extends CustomAbstractCoreController
{
    private function validateConfig(ConfMaster $confMaster): bool {
        $data = $confMaster->getGlobalConf()->get( MyHordesSetting::IssueReportingGitlabToken ) ?? [];
        return Arr::get( $data, 'token', null ) && Arr::get( $data, 'project-id', null );
    }

    private function collectProxyInformation(Request $request, ?VersionManagerInterface $version = null): array {
        $user_profile = $this->generateUrl( 'soul_visit', ['id' => $this->getUser()->getId()], UrlGeneratorInterface::ABSOLUTE_URL );

        $town = $this->getUser()->getActiveCitizen()?->getTown();
        $zone = $this->getUser()->getActiveCitizen()?->getZone();

        $town_url = $town ? $this->generateUrl( 'admin_town_dashboard', ['id' => $town->getId()], UrlGeneratorInterface::ABSOLUTE_URL ) : null;

        return array_filter( [
            'Active User' => "[{$this->getUser()->getName()} #{$this->getUser()->getId()}]({$user_profile})",
            'Current Town' => $town ? "[{$town->getName()} #{$town->getId()}]({$town_url})" : null,
            'Current Zone' => $zone ? "{$zone->getX()} / {$zone->getY()}" : null,
            'Page URL' => $request->headers->get('referer'),
            'User Agent' => $request->headers->get('User-Agent'),
            'Version' => $version?->getVersion()?->toString()
        ] );
    }

    /**
     * @param ConfMaster $confMaster
     * @param UserHandler $handler
     * @return JsonResponse
     * @throws \Exception
     */
    #[Route(path: '', name: 'base', methods: ['GET'])]
    public function index(ConfMaster $confMaster, UserHandler $handler): JsonResponse {

        $blocked = !$this->getUser() || ($handler->isRestricted($this->getUser(), AccountRestriction::RestrictionReportToGitlab)) || $handler->isRestricted( $this->getUser(), AccountRestriction::RestrictionGameplay );

        return new JsonResponse([
            'strings' => [
                'redirect' => (!$blocked && $this->validateConfig( $confMaster )) ? null : $confMaster->getGlobalConf()->get( MyHordesSetting::IssueReportingFallbackUrl ),

                'common' => [
                    'prompt' => $this->translator->trans('Über dieses Formular kannst du uns einen technischen Fehler melden. Bitte verwende diese Funktion nicht, um uns unangemessene Inhalte oder inhaltliche Vorschläge für zukünftige Updates zu senden.', [], 'global'),
                    'warn' => $this->translator->trans('Der Missbrauch dieses Formulars führt zu Sanktionen für deinen Account.', [], 'global'),

                    'add_file' => $this->translator->trans('Datei anhängen', [], 'global'),
                    'add_screenshot' => $this->translator->trans('Screenshot anfertigen', [], 'global'),
                    'screenshot_failed' => $this->translator->trans('Leider scheint dein Gerät oder Browser diese Funktion nicht zu unterstützen.', [], 'global'),
                    'delete_file' => $this->translator->trans('Anhang entfernen', [], 'global'),

                    'ok' => $this->translator->trans('Absenden', [], 'global'),
                    'cancel' => $this->translator->trans('Abbrechen', [], 'global'),

                    'success' => $this->translator->trans('Dein Fehlerbericht wurde erfolgreich erfasst. Vielen Dank!', [], 'global'),
                ],

                'errors' => [
                    'too_large' => $this->translator->trans('Eine oder mehrere ausgewählte Dateien sind zu groß, um angehängt zu werden.', [], 'global'),
                    'error_400' => $this->translator->trans('Bitte prüfe deinen Bericht auf Vollständigkeit.', [], 'global'),
                    'error_407' => $this->translator->trans('Bei der Weiterleitung deines Fehlerberichts an GitLab ist ein Kommunkationsfehler aufgetreten. Bitte versuche es in einigen Augenblicken erneut.', [], 'global'),
                    'error_412' => $this->translator->trans('Dein Fehlerbericht konnte nicht weitergeleitet werden. Dieser MyHordes-Server ist nicht an eine GitLab-Instanz angeschlossen.', [], 'global'),
                ],

                'fields' => [
                    'title' => [
                        'title' => $this->translator->trans('Kurzzusammenfassung des Fehlers', [], 'global'),
                        'hint' => $this->translator->trans('Beschreibe das Problem in einem Stichpunkt.', [], 'global'),
                        'example' => $this->translator->trans('Beispiel: Kommunikationsfehler beim Benutzen des Katapults', [], 'global'),
                    ],
                    'desc' => [
                        'title' => $this->translator->trans('Beschreibung des Fehlers', [], 'global'),
                        'hint' => $this->translator->trans('Bitte beschreibe detailliert, wie es zu diesem Fehler gekommen ist. Wenn du eine Fehlermeldung erhalten hast, gib bitte wenn möglich deren Wortlaut mit an.', [], 'global'),
                    ],
                    'attachment' => [
                        'title' => $this->translator->trans('Datei anhängen (optional)', [], 'global'),
                        'hint' => $this->translator->trans('Wenn du zusätzliche Dateien (Screenshots, Videos, ...) zu deiner Fehlermeldung hinzufügen möchtest, kannst du das hier tun. Bitte beachte, dass die Gesamtgröße aller hochgeladenen Dateien die Grenze von 3MB nicht übersteigen darf.', [], 'global'),
                    ],
                ]
            ]
        ]);
    }


    /**
     * @param JSONRequestParser $parser
     * @param ConfMaster $confMaster
     * @param VersionManagerInterface $version
     * @param UserHandler $handler
     * @param RateLimitingFactoryProvider $rateLimiter
     * @param MessageBusInterface $bus
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    #[Route(path: '', name: 'create_issue', methods: ['PUT'])]
    public function create_issue(JSONRequestParser $parser, ConfMaster $confMaster, VersionManagerInterface $version, UserHandler $handler, RateLimitingFactoryProvider $rateLimiter, MessageBusInterface $bus): JsonResponse {

        if (!$this->getUser() || !$this->isGranted('ROLE_USER') || $handler->isRestricted($this->getUser(), AccountRestriction::RestrictionReportToGitlab))
            return new JsonResponse([], Response::HTTP_FORBIDDEN);

        if ( !$this->isGranted('ROLE_ELEVATED') && !$rateLimiter->reportLimiter($this->getUser())->create( $this->getUser()->getId() )->consume( 2 )->isAccepted())
            return new JsonResponse([], Response::HTTP_TOO_MANY_REQUESTS);

        if (!$this->validateConfig( $confMaster )) return new JsonResponse([], Response::HTTP_PRECONDITION_FAILED);

        $title = $parser->trimmed('issue_title', null);
        $desc  = $parser->trimmed('issue_details', null);

        if (!$title || !$desc)
            return new JsonResponse([], Response::HTTP_BAD_REQUEST);

        $bus->dispatch( new GitlabCreateIssueMessage(
            owner: $this->getUser()->getId(),
            title: $title,
            description: $desc,
            issue_type: 'issue',
            confidential: true,
            trusted_info: $this->collectProxyInformation( Request::createFromGlobals(), $version ),
            passed_info: $parser->get_array('pass', []),
            attachments: $parser->get_array( 'issue_attachments' )
        ) );

        return new JsonResponse([
            'success' => true
        ], Response::HTTP_CREATED);
    }

}
