<?php

namespace App\Controller\REST\User;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\AccountRestriction;
use App\Entity\ExternalApp;
use App\Entity\RememberMeTokens;
use App\Entity\UserPendingValidation;
use App\Response\AjaxResponse;
use App\Service\Actions\EMail\GetEMailDomainAction;
use App\Service\Actions\Security\GenerateKeyAction;
use App\Service\Actions\Security\GenerateMercureToken;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use App\Service\RandomGenerator;
use App\Service\UserHandler;
use App\Structures\MyHordesConf;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Impersonate\ImpersonateUrlGenerator;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

#[Route(path: '/rest/v1/user/header', name: 'rest_user_header_', condition: "request.headers.get('Accept') === 'application/json'")]
#[GateKeeperProfile('skip')]
class HeaderController extends CustomAbstractCoreController
{
    public function __construct(
        ConfMaster $conf,
        TranslatorInterface $translator,
        private readonly ImpersonateUrlGenerator $impersonateUrlGenerator
    ) {
        parent::__construct($conf, $translator);

    }

    /**
     * @param Packages $assets
     * @return JsonResponse
     */
    #[Route(path: '', name: 'base', methods: ['GET'])]
    public function index(Packages $assets): JsonResponse {

        return new JsonResponse([
            'imp' => [
                'cancel' => $this->translator->trans('Impersonifizierung beenden', [], 'admin'),
                'url' => $this->impersonateUrlGenerator->generateExitPath( $this->generateUrl('initial_landing') )
            ],
            'logout' => [
                'tooltip' => $this->translator->trans('Logout', [], 'soul'),
                'url' => $this->generateUrl('auto_logout'),
                'icon' => $assets->getUrl( 'build/images/icons/b_exit.png' ),
            ],
            'apps' => [
                'directory' => $this->translator->trans('Verzeichnis', [], 'global'),
                'help' => $this->translator->trans('Die folgenden Links verweisen alle auf Web- und Fanseiten, die von Spielern kreiert wurden. Ihr findet auf ihnen zusätzliche Informationen oder nützliche Tools für das Spiel:', [], 'global'),
                'test' => $this->translator->trans('Testbetrieb', [], 'admin'),
                'icon' => $assets->getUrl( 'build/images/icons/small_archive.gif' ),
            ]
        ]);
    }

    /**
     * @param EntityManagerInterface $em
     * @param Packages $assets
     * @return JsonResponse
     */
    #[Route(path: '/apps', name: 'apps', methods: ['GET'])]
    public function apps(EntityManagerInterface $em, Packages $assets): JsonResponse {
        return new JsonResponse([
            'apps' => $em->getRepository(ExternalApp::class)
                ->matching((new Criteria())->where(Criteria::expr()->eq('active', true)))
                ->filter(fn(ExternalApp $app) => !$app->getTesting() || ($app->getOwner() === $this->getUser()) || $this->isGranted('ROLE_SUB_ADMIN'))
                ->map( fn(ExternalApp $app) => [
                    'i' => $app->getId(),
                    'w' => $app->isWiki(),
                    's' => $app->getOwner() === $this->getUser() ? $app->getSecret() : null,
                    'n' => $app->getName(),
                    't' => $app->getTesting(),
                    'u' => $this->generateUrl('disclaimer', ['id' => $app->getId()]),
                    'p' => $app->getImageName() ? $this->generateUrl('app_web_app_icon', [
                        'aid' => $app->getId(),
                        'name' => $app->getImageName(),
                        'ext' => $app->getImageFormat()
                    ]) : $assets->getUrl('build/images/apps/null.gif')
                ] )->toArray()
            ]
        );
    }

}
