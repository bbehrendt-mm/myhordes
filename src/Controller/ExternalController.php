<?php
/** @noinspection PhpRouteMissingInspection */

namespace App\Controller;

use App\Annotations\GateKeeperProfile;
use App\Entity\ExternalApp;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Class ExternalController
 * @package App\Controller
 */
#[GateKeeperProfile(allow_during_attack: true, record_user_activity: false)]
class ExternalController extends CustomAbstractController {

    /**
     * @param ExternalApp $app
     * @return Response
     */
    #[Route(path: '/jx/disclaimer/{id<\d+>}', name: 'disclaimer', condition: 'request.isXmlHttpRequest()')]
    public function disclaimer(ExternalApp $app): Response {
        $user = $this->getUser();
        if (!$user || ($app->getTesting() && $app->getOwner() !== $user && !$this->isGranted('ROLE_SUB_ADMIN')))
            return $this->redirectToRoute('initial_landing');

        return $this->render('ajax/public/disclaimer.html.twig', ['appid'  => $app->getId()]);
    }

    /**
     * @return Response
     */
    #[Route(path: '/jx/json_docs', name: 'json_docs', condition: 'request.isXmlHttpRequest()')]
    public function json_documentation(): Response {
        return $this->render('ajax/public/jsonapidocs.html.twig', []);
    }

    /**
     * @return Response
     */
    #[Route(path: '/jx/xml_docs', name: 'xml_docs', condition: 'request.isXmlHttpRequest()')]
    public function xml_documentation(): Response {
        return $this->render('ajax/public/xmlapidocs.html.twig', []);
    }
}
