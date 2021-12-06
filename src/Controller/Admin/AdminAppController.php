<?php

namespace App\Controller\Admin;

use App\Annotations\GateKeeperProfile;
use App\Entity\ExternalApp;
use App\Entity\User;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\MediaService;
use App\Service\RandomGenerator;
use App\Structures\MyHordesConf;
use App\Translation\T;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @GateKeeperProfile(allow_during_attack=true)
 */
class AdminAppController extends AdminActionController
{
    /**
     * @Route("jx/admin/apps/all", name="admin_app_view")
     * @return Response
     */
    public function ext_app_view(): Response
    {
        $apps = $this->entity_manager->getRepository(ExternalApp::class)->findAll();
        return $this->render( 'ajax/admin/apps/list.html.twig', $this->addDefaultTwigArgs(null, ['all_apps' => $apps]));
    }

    /**
     * @Route("jx/admin/apps/{id<\d+>}", name="admin_app_edit")
     * @param int $id
     * @return Response
     */
    public function ext_app_edit(int $id): Response
    {
        T::__("Neue Anwendung registrieren", "admin");
        T::__("Änderungen an '{appname}' speichern", "admin");
        if (!$this->isGranted('ROLE_ADMIN')) $this->redirect($this->generateUrl('admin_app_view'));
        $app = $this->entity_manager->getRepository(ExternalApp::class)->find($id);
        if ($app === null) return $this->redirect($this->generateUrl('admin_app_view'));
        return $this->render( 'ajax/admin/apps/edit.html.twig', $this->addDefaultTwigArgs(null, [
            'current_app' => $app,
            'icon_max_size' => $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD, 3145728)
        ]));
    }

    /**
     * @Route("jx/admin/apps/new", name="admin_app_new")
     * @return Response
     */
    public function ext_app_new(): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) $this->redirect($this->generateUrl('admin_app_view'));
        return $this->render( 'ajax/admin/apps/edit.html.twig', $this->addDefaultTwigArgs(null, [
            'current_app' => null,
            'icon_max_size' => $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD, 3145728)
        ]));
    }

    /**
     * @Route("api/admin/apps/toggle/{id<\d+>}", name="admin_toggle_ext_app")
     * @param int $id
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function ext_app_toggle(int $id, JSONRequestParser $parser): Response {
        if (!$this->isGranted('ROLE_ADMIN')) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        $app = $this->entity_manager->getRepository(ExternalApp::class)->find($id);
        if ($app === null ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $app->setActive( (bool)$parser->get('on', true) );

        $this->entity_manager->persist($app);
        try {
            $this->entity_manager->flush();
        } catch (\Exception $e) {
            AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        $this->logger->info("Admin <info>{$this->getUser()->getName()}</info> <debug>" . ($app->getActive() ? 'activated' : 'deactivated') . "</debug> app <info>{$app->getName()}</info>");

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/apps/register/{id<-?\d+>}", name="admin_update_ext_app")
     * @param int $id
     * @param JSONRequestParser $parser
     * @param RandomGenerator $rand
     * @param MediaService $media
     * @return Response
     */
    public function ext_app_update(int $id, JSONRequestParser $parser, RandomGenerator $rand, MediaService $media): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) return AjaxResponse::error( ErrorHelper::ErrorPermissionError );

        if (!$parser->has_all(['name','owner','contact','url'], true)) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $app = $id < 0 ? new ExternalApp() : $this->entity_manager->getRepository(ExternalApp::class)->find($id);
        if ($app === null ) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $owner = null;
        if ((int)$parser->get('owner') > 0) {
            $owner = $this->entity_manager->getRepository(User::class)->find((int)$parser->get('owner'));
            if ($owner === null) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        }
        $old_app = $id< 0 ? null : clone $app;

        $app
            ->setName( $parser->get('name') )
            ->setActive( $id < 0 ? true : $app->getActive() )
            ->setContact( $parser->get('contact') )
            ->setUrl( $parser->get('url') )
            ->setOwner($owner)
            ->setTesting( (bool)$parser->get('test') )
            ->setLinkOnly( !(bool)$parser->get('flux') );

        if ($parser->get('icon') !== false) {
            if ($parser->get('icon') === null)
                $app->setImage(null)->setImageName(null)->setImageFormat(null);
            else {
                $payload = $parser->get_base64('icon');

                if (strlen( $payload ) > $this->conf->getGlobalConf()->get(MyHordesConf::CONF_AVATAR_SIZE_UPLOAD))
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                if ($media->resizeImageSimple( $payload, 16, 16, $processed_format, false ) !== MediaService::ErrorNone)
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                $app->setImage($payload)->setImageName(md5($payload))->setImageFormat( strtolower( $processed_format ) );

            }
        }

        if ($app->getLinkOnly()) $app->setSecret(null);
        elseif ( empty($app->getSecret()) || (bool)$parser->get('rekey') ) {
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

        if($old_app !== null) {
            $this->logger->info("Admin <info>{$this->getUser()->getName()}</info> updated app <info>{$app->getName()}</info> infos", [
                'name' => "{$old_app->getName()} => {$app->getName()}",
                'contact' => "{$old_app->getContact()} => {$app->getContact()}",
                'url' => "{$old_app->getUrl()} => {$app->getUrl()}",
                'owner' => (($old_app->getOwner() !== null ? $old_app->getOwner()->getName() : 'null') . " => " . ($app->getOwner() !== null ? $app->getOwner()->getName() : 'null')),
                'testing' => "{$old_app->getTesting()} => {$app->getTesting()}",
                'link_only' => "{$old_app->getLinkOnly()} => {$app->getLinkOnly()}",
            ]);
        } else {
            $this->logger->info("Admin <info>{$this->getUser()->getName()}</info> created app <info>{$app->getName()}</info> infos", [
                'name' => $app->getName(),
                'contact' => $app->getContact(),
                'url' => $app->getUrl(),
                'owner' => ($app->getOwner() !== null ? $app->getOwner()->getName() : 'null'),
                'testing' => $app->getTesting(),
                'link_only' => $app->getLinkOnly(),
            ]);
        }

        return AjaxResponse::success();
    }
}
