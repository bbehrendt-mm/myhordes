<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Response\AjaxResponse;
use App\Service\GameFactory;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 */
class GameController extends AbstractController implements GameInterfaceController
{
    protected $entity_manager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entity_manager = $em;
    }

    protected function getActiveCitizen(): Citizen {
        return $this->entity_manager->getRepository(Citizen::class)->findActiveByUser($this->getUser());
    }

    /**
     * @Route("jx/game/landing", name="game_landing")
     * @return Response
     */
    public function landing(): Response
    {
        if ($this->getActiveCitizen()->getProfession()->getName() === CitizenProfession::DEFAULT)
            return $this->redirect($this->generateUrl('game_jobs'));
        else return $this->redirect($this->generateUrl('town_dashboard'));
    }

    /**
     * @Route("jx/game/jobcenter", name="game_jobs")
     * @return Response
     */
    public function job_select(): Response
    {
        if ($this->getActiveCitizen()->getProfession()->getName() !== CitizenProfession::DEFAULT)
            return $this->redirect($this->generateUrl('game_landing'));

        return $this->render( 'ajax/game/jobs.html.twig', [
            'professions' => $this->entity_manager->getRepository(CitizenProfession::class)->findSelectable()
        ] );
    }

    /**
     * @Route("api/game/job", name="api_jobcenter")
     * @param JSONRequestParser $parser
     * @param Locksmith $ls
     * @return Response
     */
    public function job_select_api(JSONRequestParser $parser, Locksmith $ls): Response {

        $citizen = $this->getActiveCitizen();
        if ($citizen->getProfession()->getName() !== CitizenProfession::DEFAULT)
            return AjaxResponse::error('already_selected');

        if (!$parser->has('job')) return AjaxResponse::error('invalid_job');
        $job_id = (int)$parser->get('job', -1);
        if ($job_id <= 0) return AjaxResponse::error('invalid_job');

        $new_profession = $this->entity_manager->getRepository(CitizenProfession::class)->find( $job_id );
        if (!$new_profession) return AjaxResponse::error('invalid_job');

        try {
            $citizen->setProfession( $new_profession );
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error('db_error');
        }

        return AjaxResponse::success();
    }

}
