<?php

namespace App\Controller;

use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\UserPendingValidation;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\MemcachedStore;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 */
class GhostController extends AbstractController
{

    /**
     * @Route("jx/ghost/welcome", name="ghost_welcome")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function welcome(EntityManagerInterface $em): Response
    {


        $this->denyAccessUnlessGranted('ROLE_USER');
        return $this->render( 'ajax/ghost/intro.html.twig', [
            'townClasses' => $em->getRepository(TownClass::class)->findAll()
        ] );
    }

}
