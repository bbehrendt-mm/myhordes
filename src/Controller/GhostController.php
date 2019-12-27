<?php

namespace App\Controller;

use App\Entity\Town;
use App\Response\AjaxResponse;
use App\Service\GameFactory;
use App\Service\JSONRequestParser;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 */
class GhostController extends AbstractController implements GhostInterfaceController
{
    /**
     * @Route("jx/ghost/welcome", name="ghost_welcome")
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function welcome(EntityManagerInterface $em): Response
    {
        return $this->render( 'ajax/ghost/intro.html.twig', [
            'townClasses' => $em->getRepository(TownClass::class)->findAll()
        ] );
    }

    /**
     * @Route("api/ghost/join", name="api_join")
     * @param JSONRequestParser $parser
     * @param GameFactory $factory
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function join_api(JSONRequestParser $parser, GameFactory $factory, EntityManagerInterface $em) {
        if (!$parser->has('town')) return AjaxResponse::error('no_town');
        $town_id = (int)$parser->get('town', -1);
        if ($town_id <= 0) return AjaxResponse::error('no_town');

        $town = $em->getRepository(Town::class)->find( $town_id );
        $user = $this->getUser();

        if (!$town || !$user) return AjaxResponse::error('no_town');

        $citizen = $factory->createCitizen($town, $user, $error);
        if (!$citizen) switch ($error) {
            case GameFactory::ErrorTownClosed: return AjaxResponse::error('town_closed');
            case GameFactory::ErrorUserAlreadyInTown: return AjaxResponse::error('in_town');
            case GameFactory::ErrorUserAlreadyInGame: return AjaxResponse::error('no_ghost');
            default: return AjaxResponse::error();
        }

        try {
            $em->persist($town);
            $em->persist($citizen);
            $em->flush();
        } catch (Exception $e) {
            return AjaxResponse::error('db_error');
        }

        return AjaxResponse::success();
    }

}
