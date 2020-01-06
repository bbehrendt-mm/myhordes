<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\Item;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\UserPendingValidation;
use App\Entity\Zone;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use App\Structures\ItemRequest;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
class BeyondController extends InventoryAwareController implements BeyondInterfaceController
{

    const ErrorNoReturnFromHere = ErrorHelper::BaseBeyondErrors + 1;

    protected function addDefaultTwigArgs( ?string $section = null, ?array $data = null ): array {
        $zones = []; $range_x = [PHP_INT_MAX,PHP_INT_MIN]; $range_y = [PHP_INT_MAX,PHP_INT_MIN];
        foreach ($this->getActiveCitizen()->getTown()->getZones() as $zone) {
            $x = $zone->getX();
            $y = $zone->getY();

            $range_x = [ min($range_x[0], $x), max($range_x[1], $x) ];
            $range_y = [ min($range_y[0], $y), max($range_y[1], $y) ];

            if (!isset($zones[$x])) $zones[$x] = [];
            $zones[$x][$y] = $zone;

        }

        return parent::addDefaultTwigArgs( $section,array_merge( [
            'zone'  =>  $this->getActiveCitizen()->getZone(),
            'zones' =>  $zones,
            'pos_x'  => $this->getActiveCitizen()->getZone()->getX(),
            'pos_y'  => $this->getActiveCitizen()->getZone()->getY(),
            'map_x0' => $range_x[0],
            'map_x1' => $range_x[1],
            'map_y0' => $range_y[0],
            'map_y1' => $range_y[1],
            'actions' => $this->getItemActions(),
        ], $data) );
    }

    /**
     * @Route("jx/beyond/desert", name="beyond_dashboard")
     * @return Response
     */
    public function desert(): Response
    {
        $is_on_zero = $this->getActiveCitizen()->getZone()->getX() == 0 && $this->getActiveCitizen()->getZone()->getY() == 0;

        return $this->render( 'ajax/game/beyond/desert.html.twig', $this->addDefaultTwigArgs(null, [
            'allow_enter_town' => $is_on_zero,
            'allow_floor_access' => !$is_on_zero,
            'actions' => $this->getItemActions(),
            'floor' => $this->getActiveCitizen()->getZone()->getFloor(),
        ]) );
    }

    /**
     * @Route("api/beyond/desert/exit", name="beyond_door_exit_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function door_exit_api(JSONRequestParser $parser): Response {
        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if ($zone->getX() != 0 || $zone->getY() != 0)
            return AjaxResponse::error( self::ErrorNoReturnFromHere );

        $citizen->setZone( null );
        $zone->removeCitizen( $citizen );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->persist($zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

}
