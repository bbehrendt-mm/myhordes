<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
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
class TownController extends AbstractController implements GameInterfaceController, GameProfessionInterfaceController
{
    protected $entity_manager;

    public function __construct(EntityManagerInterface $em)
    {
        $this->entity_manager = $em;
    }

    protected function getActiveCitizen(): Citizen {
        return $this->entity_manager->getRepository(Citizen::class)->findActiveByUser($this->getUser());
    }

    protected function addDefaultTwigArgs( ?string $section = null, ?array $data = null ): array {
        $data = $data ?? [];
        $data['menu_section'] = $section;
        return $data;
    }

    /**
     * @Route("jx/town/dashboard", name="town_dashboard")
     * @return Response
     */
    public function dashboard(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs(null, [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/house", name="town_house")
     * @return Response
     */
    public function house(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('house', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/well", name="town_well")
     * @return Response
     */
    public function well(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('well', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/bank", name="town_bank")
     * @return Response
     */
    public function bank(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('bank', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/cizizens", name="town_citizens")
     * @return Response
     */
    public function citizens(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('citizens', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/constructions", name="town_constructions")
     * @return Response
     */
    public function constructions(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('constructions', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("jx/town/door", name="town_door")
     * @return Response
     */
    public function door(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('door', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }
}
