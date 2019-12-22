<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class WebController extends AbstractController
{

    /**
     * @Route("/")
     * @return Response
     */
    public function framework(): Response
    {
        return $this->render( 'web/framework.html.twig', [
            'ajax_landing' => $this->generateUrl('initial_landing')
        ] );
    }

    /**
     * @Route("/jx/{ajax}",requirements={"ajax"=".+"},condition="!request.isXmlHttpRequest()")
     * @param string $ajax
     * @return Response
     */
    public function loader(string $ajax): Response
    {
        return $this->render( 'web/framework.html.twig', [
            'ajax_landing' => Request::createFromGlobals()->getBasePath() . '/jx/' . $ajax
        ] );
    }

}