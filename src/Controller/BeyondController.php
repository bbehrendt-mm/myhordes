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

    const ErrorNoReturnFromHere     = ErrorHelper::BaseBeyondErrors + 1;
    const ErrorNotReachableFromHere = ErrorHelper::BaseBeyondErrors + 2;

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
            'zone_players' => count($this->getActiveCitizen()->getZone()->getCitizens()),
            'zone_zombies' => $this->getActiveCitizen()->getZone()->getZombies(),
            'zone'  =>  $this->getActiveCitizen()->getZone(),
            'zones' =>  $zones,
            'allow_movement' => $this->getActiveCitizen()->getAp() >= 1 && !$this->citizen_handler->isTired( $this->getActiveCitizen() ),
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
     * @Route("api/beyond/desert/exit", name="beyond_desert_exit_controller")
     * @return Response
     */
    public function desert_exit_api(): Response {
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

    /**
     * @Route("api/beyond/desert/move", name="beyond_desert_move_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function desert_move_api(JSONRequestParser $parser): Response {
        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        $px = $parser->get('x', PHP_INT_MAX);
        $py = $parser->get('y', PHP_INT_MAX);


        if (abs($px - $zone->getX()) + abs($py - $zone->getY()) !== 1) return AjaxResponse::error( self::ErrorNotReachableFromHere );

        $new_zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition( $citizen->getTown(), $px, $py );
        if (!$new_zone) return AjaxResponse::error( self::ErrorNotReachableFromHere );

        // ToDo: Check zone control points

        if ($citizen->getAp() < 1 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $this->citizen_handler->setAP($citizen, true, -1);
        $zone->removeCitizen( $citizen );
        $new_zone->addCitizen( $citizen );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->persist($zone);
            $this->entity_manager->persist($new_zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/action", name="beyond_desert_action_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function action_desert_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        return $this->generic_action_api( $parser, $handler);
    }

    /**
     * @Route("api/beyond/desert/item", name="beyond_desert_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function item_desert_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $up_inv   = $this->getActiveCitizen()->getInventory();
        $down_inv = $this->getActiveCitizen()->getZone()->getFloor();
        return $this->generic_item_api( $up_inv, $down_inv, true, $parser, $handler);
    }

}
