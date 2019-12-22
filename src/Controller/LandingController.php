<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingController extends AbstractController
{

    /**
     * @Route("jx/landing", name="initial_landing",condition="request.isXmlHttpRequest()")
     * @return Response
     */
    public function main_landing(): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user)
            return $this->redirect($this->generateUrl('public_welcome'));
        elseif (!$user->getValidated())
            return $this->redirect($this->generateUrl('public_validate'));
        else
            return $this->redirect($this->generateUrl('ghost_welcome'));
    }


}
