<?php

namespace App\Controller\Messages;

use App\Entity\Announcement;
use App\Entity\Changelog;
use App\Entity\ForumPoll;
use App\Entity\ForumPollAnswer;
use App\Entity\ForumUsagePermissions;
use App\Entity\GlobalPoll;
use App\Entity\ReactionSet;
use App\Entity\User;
use App\Enum\Configuration\ExternalTokenPurpose;
use App\Enum\Configuration\ExternalTokenType;
use App\Enum\Configuration\MyHordesSetting;
use App\Messages\Discord\DiscordMessage;
use App\Response\AjaxResponse;
use App\Service\Actions\External\GetExternalTokenWithFallbackAction;
use App\Service\ErrorHelper;
use App\Service\EventProxyService;
use App\Service\HTMLService;
use App\Service\JSONRequestParser;
use App\Service\Media\MediaService;
use App\Service\Morph\MorphService;
use DateTime;
use DiscordWebhooks\Client;
use DiscordWebhooks\Embed;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @method User getUser
 */
#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[IsGranted('ROLE_USER')]
class MessageAnnouncementController extends MessageController
{
    /**
     * @param string $tab
     * @return Response
     */
    #[Route(path: 'jx/admin/com/changelogs/{tab}', name: 'admin_changelogs')]
    public function changelogs( string $tab = 'changelog' ): Response
    {
        return $this->render( 'ajax/admin/changelogs/changelogs.html.twig', $this->addDefaultTwigArgs(null, [
            'news' => $this->isGranted('ROLE_CROW') ? $this->entity_manager->getRepository(Changelog::class)->findAll() : [],
            'announces' => $this->entity_manager->getRepository(Announcement::class)->findAll(),
            'tab' => $tab
        ]));
    }

    /**
     * @return Response
     */
    #[Route(path: 'jx/admin/com/changelogs/polls', name: 'admin_polls', priority: 1)]
    public function polls(  ): Response
    {
        return $this->render( 'ajax/admin/changelogs/polls.html.twig', $this->addDefaultTwigArgs());
    }

    /**
     * @return Response
     */
    #[Route(path: 'jx/admin/changelogs/c/editor', name: 'admin_new_changelog_editor_controller')]
    public function admin_new_changelog_editor_controller(): Response {
        return $this->render( 'ajax/editor/changelog.html.twig', [
            'permission' => $this->getPermissionObject( match(true) {
                $this->isGranted('ROLE_SUB_ADMIN') => ForumUsagePermissions::PermissionOwn,
                $this->isGranted('ROLE_CROW') => ForumUsagePermissions::PermissionReadWrite | ForumUsagePermissions::PermissionFormattingModerator,
                default => ForumUsagePermissions::PermissionReadWrite | ForumUsagePermissions::PermissionFormattingOracle,
            } ),
        ] );
    }

    /**
     * @return Response
     */
    #[Route(path: 'jx/admin/com/changelogs/a/editor', name: 'admin_new_announcement_editor_controller')]
    public function admin_new_announcement_editor_controller(): Response {
        return $this->render( 'ajax/editor/announcement.html.twig', [
            'uuid' => Uuid::v4(),
            'permission' => $this->getPermissionObject( match(true) {
                $this->isGranted('ROLE_SUB_ADMIN') => ForumUsagePermissions::PermissionOwn,
                $this->isGranted('ROLE_CROW') => ForumUsagePermissions::PermissionReadWrite | ForumUsagePermissions::PermissionFormattingModerator,
                default => ForumUsagePermissions::PermissionReadWrite | ForumUsagePermissions::PermissionFormattingOracle,
            } ),
        ] );
    }

    /**
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'api/admin/changelogs/new_changelog', name: 'admin_changelog_new_changelog')]
    public function create_changelog_api(EntityManagerInterface $em, JSONRequestParser $parser): Response {
        $title     = $parser->get('title', '');
        $content   = $parser->get('content', '');
        $version   = $parser->get('version', '');
        $lang      = $parser->get('lang', 'de');

        $author    = $this->getUser();

        if(empty($title) || empty($content) || empty($version))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($em->getRepository(Changelog::class)->findOneBy(['lang' => $lang, 'version' => $version]))
            return AjaxResponse::errorMessage( $this->translator->trans( 'Es existiert bereits ein Changelog für diese Version in der angegebenen Sprache.', [], 'admin' ) );

        $change = (new Changelog())
            ->setTitle($title)->setText($content)->setVersion($version)->setLang($lang)->setAuthor($author)
            ->setDate(new DateTime());

        if (!$this->preparePost($author,null,$change))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $em->persist($change);
        $em->flush();

        return AjaxResponse::success( true, ['url' => $this->generateUrl('admin_changelogs', ['tab' => 'changelog'])] );
    }

    /**
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @param UrlGeneratorInterface $urlGenerator
     * @param MessageBusInterface $bus
     * @param EventProxyService $proxy
     * @param MediaService $mediaService
     * @param GetExternalTokenWithFallbackAction $get_endpoints
     * @param MorphService $morphService
     * @param Request $request
     * @return Response
     * @throws ExceptionInterface|Exception
     */
    #[Route(path: 'api/admin/com/changelogs/new_announcement', name: 'admin_changelog_new_announcement')]
    public function create_announcement_api(
        EntityManagerInterface $em,
        JSONRequestParser $parser,
        UrlGeneratorInterface $urlGenerator,
        MessageBusInterface $bus,
        EventProxyService $proxy,
        MediaService $mediaService,
        GetExternalTokenWithFallbackAction $get_endpoints,
        MorphService $morphService,
        Request $request
    ): Response {
        $title     = $parser->get('title', '');
        $content   = $parser->get('content', '');
        $lang      = $parser->get('lang', 'de');

        $author = $this->getUser();

        if(empty($title) || empty($content)) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $announcement = new Announcement()
            ->setTitle($title)->setText($content)->setLang($lang)->setSender($author)->setTimestamp(new DateTime())
            ->setValidated( $this->isGranted( 'ROLE_CROW' ) || $this->isGranted( 'ROLE_SUB_ADMIN' ) );

        if (!$this->preparePost($author,null,$announcement))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $em->persist($announcement);
        $em->flush();

        $em->persist( $morphService->firstOrCreateMorph( ReactionSet::class, $announcement ) );
        if ($announcement->isValidated())
            $proxy->newAnnounceEvent( $announcement );

        $em->flush();

        if (!$announcement->isValidated()) {

            $endpoints = $get_endpoints(
                ExternalTokenType::DiscordWebhook,
                ExternalTokenPurpose::EventReporting,
                MyHordesSetting::HookAnimDiscord
            );

            $dc_avatar =
                $this->generateUrl( 'home', [],  UrlGeneratorInterface::ABSOLUTE_URL ) .
                $this->asset->getUrl('build/images/default/user-dionysia.png');

            foreach ($endpoints as $endpoint) {
                $discord = new Client($endpoint)
                    ->username( 'Corvus Dionysia' )
                    ->avatar( $dc_avatar )
                    ->message(":black_joker: **Please validate my announcement.**");

                $discord->embed( new Embed()
                                     ->color('B434EB')
                                     ->title($announcement->getTitle())
                                     ->description(mb_substr(strip_tags(
                                                                 preg_replace(
                                                                     ['/(?:<br ?\/?>)+/', '/<span class="quoteauthor">([\w\d ._-]+)<\/span>/',  '/<blockquote>/', '/<\/blockquote>/', '/<a href="(.*?)">(.*?)<\/a>/'],
                                                                     ["\n", '${1}:', '[**', '**]', '[${2}](${1})'],
                                                                     $this->html->prepareEmotes( $announcement->getText())
                                                                 )
                                                             ), 0, 2000))
                                     ->field('Language', $announcement->getLang(), true)
                                     ->author(
                                         $this->getUser()->getName(),
                                         $urlGenerator->generate( 'admin_users_account_view', ['id' => $this->getUser()->getId()], UrlGeneratorInterface::ABSOLUTE_URL ),
                                         $mediaService->getSingleMediaForObject( $this->getUser(), 'avatar' )?->getLargestConversionByTag('default', 200)?->getUrl($request->getBaseUrl())
                                     )
                );

                $bus->dispatch( new DiscordMessage( $discord ) );
            }
        }

        return AjaxResponse::success( true, ['url' => $this->generateUrl('admin_changelogs', ['tab' => 'announcement'])] );
    }

    /**
     * @param Changelog $changelog
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route(path: 'api/admin/com/changelogs/del_c/{id<\d+>}', name: 'admin_changelog_del_changelog')]
    public function delete_changelog_api(#[MapEntity(id: 'id')] Changelog $changelog, EntityManagerInterface $em): Response {
        if (!$this->isGranted('ROLE_SUB_ADMIN') && $this->getUser() !== $changelog->getAuthor())
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $em->remove($changelog);
        $em->flush();

        return AjaxResponse::success( );
    }

    /**
     * @param Announcement $announcement
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route(path: 'api/admin/com/changelogs/del_a/{id<\d+>}', name: 'admin_changelog_del_announcement')]
    public function delete_announcement_api(#[MapEntity(id: 'id')] Announcement $announcement, EntityManagerInterface $em): Response {
        if (!$this->isGranted('ROLE_SUB_ADMIN') && $this->getUser() !== $announcement->getSender())
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $em->remove($announcement);
        $em->flush();

        return AjaxResponse::success( );
    }

    /**
     * @param Announcement $announcement
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route(path: 'api/admin/com/changelogs/validate/{id<\d+>}', name: 'admin_changelog_val_announcement')]
    public function validate_announcement_api(#[MapEntity(id: 'id')] Announcement $announcement, EntityManagerInterface $em, EventProxyService $proxy): Response {
        if (!$this->isGranted('ROLE_ELEVATED'))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if (!$this->isGranted('ROLE_SUB_ADMIN') && $this->getUser() === $announcement->getSender())
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if (!$announcement->isValidated()) {
            $em->persist( $announcement->setValidated(true)->setValidatedBy( $this->getUser() ) );
            $em->flush();

            $proxy->newAnnounceEvent( $announcement );
            $em->flush();
        }

        return AjaxResponse::success( );
    }

    /**
     * @param Announcement $announcement
     * @return Response
     */
    #[Route(path: 'api/admin/com/changelogs/render/{id<\d+>}', name: 'admin_changelog_render_announcement')]
    public function render_announcement_api(#[MapEntity(id: 'id')] Announcement $announcement): Response {
        return AjaxResponse::success( additional: [
            'html' => $this->html->prepareEmotes( $announcement->getText(), $announcement->getSender() )
                                                  ] );
    }
}
