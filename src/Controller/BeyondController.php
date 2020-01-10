<?php

namespace App\Controller;

use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\DigRuinMarker;
use App\Entity\DigTimer;
use App\Entity\EscapeTimer;
use App\Entity\Item;
use App\Entity\TownClass;
use App\Entity\User;
use App\Entity\UserPendingValidation;
use App\Entity\Zone;
use App\Repository\DigRuinMarkerRepository;
use App\Response\AjaxResponse;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use App\Service\RandomGenerator;
use App\Structures\ItemRequest;
use DateTime;
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
    const ErrorZoneBlocked          = ErrorHelper::BaseBeyondErrors + 3;
    const ErrorZoneUnderControl     = ErrorHelper::BaseBeyondErrors + 4;
    const ErrorAlreadyWounded       = ErrorHelper::BaseBeyondErrors + 5;
    const ErrorNotDiggable          = ErrorHelper::BaseBeyondErrors + 6;

    protected $game_factory;
    protected $random_generator;
    protected $item_factory;

    /**
     * BeyondController constructor.
     * @param EntityManagerInterface $em
     * @param InventoryHandler $ih
     * @param CitizenHandler $ch
     * @param ActionHandler $ah
     * @param TranslatorInterface $translator
     * @param GameFactory $gf
     * @param RandomGenerator $rg
     * @param ItemFactory $if
     */
    public function __construct(
        EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch, ActionHandler $ah,
        TranslatorInterface $translator, GameFactory $gf, RandomGenerator $rg, ItemFactory $if)
    {
        parent::__construct($em, $ih, $ch, $ah, $translator);
        $this->game_factory = $gf;
        $this->random_generator = $rg;
        $this->item_factory = $if;
    }

    protected function deferZoneUpdate() {
        $this->game_factory->updateZone( $this->getActiveCitizen()->getZone() );
    }

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

        $zone = $this->getActiveCitizen()->getZone();
        $blocked = !$this->check_cp($zone, $cp);
        $escape = $this->get_escape_timeout( $this->getActiveCitizen() );
        $citizen_tired = $this->getActiveCitizen()->getAp() <= 0 || $this->citizen_handler->isTired( $this->getActiveCitizen());

        return parent::addDefaultTwigArgs( $section,array_merge( [
            'zone_players' => count($zone->getCitizens()),
            'zone_zombies' => max(0,$zone->getZombies()),
            'zone_cp' => $cp,
            'zone'  =>  $zone,
            'zones' =>  $zones,
            'allow_movement' => (!$blocked || $escape > 0) && !$citizen_tired,
            'pos_x'  => $zone->getX(),
            'pos_y'  => $zone->getY(),
            'map_x0' => $range_x[0],
            'map_x1' => $range_x[1],
            'map_y0' => $range_y[0],
            'map_y1' => $range_y[1],
            'actions' => $this->getItemActions(),
        ], $data) );
    }

    public function check_cp(Zone $zone, ?int &$cp = null): bool {
        $cp = 0;
        foreach ($zone->getCitizens() as $c)
            $cp += $this->citizen_handler->getCP($c);
        return $cp >= $zone->getZombies();
    }

    public function get_escape_timeout(Citizen $c): int {
        $active_timer = $this->entity_manager->getRepository(EscapeTimer::class)->findActiveByCitizen( $c );
        return $active_timer ? ($active_timer->getTime()->getTimestamp() - (new DateTime())->getTimestamp()) : -1;
    }

    public function get_dig_timeout(Citizen $c, ?bool &$active = null): int {
        $active_timer = $this->entity_manager->getRepository(DigTimer::class)->findActiveByCitizen( $c );
        if (!$active_timer) return -1;
        $active = !$active_timer->getPassive();
        return $active_timer->getTimestamp()->getTimestamp() - (new DateTime())->getTimestamp();
    }

    /**
     * @Route("jx/beyond/desert", name="beyond_dashboard")
     * @return Response
     */
    public function desert(): Response
    {
        $this->deferZoneUpdate();

        $is_on_zero = $this->getActiveCitizen()->getZone()->getX() == 0 && $this->getActiveCitizen()->getZone()->getY() == 0;

        $citizen_tired = $this->getActiveCitizen()->getAp() <= 0 || $this->citizen_handler->isTired( $this->getActiveCitizen());
        $dig_timeout = $this->get_dig_timeout( $this->getActiveCitizen(), $dig_active );

        $blocked = !$this->check_cp($this->getActiveCitizen()->getZone(), $cp);
        $escape = $this->get_escape_timeout( $this->getActiveCitizen() );

        return $this->render( 'ajax/game/beyond/desert.html.twig', $this->addDefaultTwigArgs(null, [
            'allow_enter_town' => $is_on_zero,
            'allow_floor_access' => !$is_on_zero,
            'can_escape' => !$this->citizen_handler->isWounded( $this->getActiveCitizen() ),
            'can_attack' => !$citizen_tired,
            'zone_blocked' => $blocked,
            'zone_escape' => $escape,
            'digging' => $dig_timeout >= 0 && $dig_active,
            'dig_ruin' => empty($this->entity_manager->getRepository(DigRuinMarker::class)->findByCitizen( $this->getActiveCitizen() )),
            'dig_timeout' => $dig_timeout,
            'actions' => $this->getItemActions(),
            'floor' => $this->getActiveCitizen()->getZone()->getFloor(),
        ]) );
    }

    /**
     * @Route("api/beyond/desert/exit", name="beyond_desert_exit_controller")
     * @return Response
     */
    public function desert_exit_api(): Response {
        $this->deferZoneUpdate();

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
        $this->deferZoneUpdate();

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        $px = $parser->get('x', PHP_INT_MAX);
        $py = $parser->get('y', PHP_INT_MAX);

        $cp_ok = $this->check_cp( $zone );

        if (abs($px - $zone->getX()) + abs($py - $zone->getY()) !== 1) return AjaxResponse::error( self::ErrorNotReachableFromHere );
        if (!$cp_ok && $this->get_escape_timeout( $citizen ) < 0) return AjaxResponse::error( self::ErrorZoneBlocked );

        $new_zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition( $citizen->getTown(), $px, $py );
        if (!$new_zone) return AjaxResponse::error( self::ErrorNotReachableFromHere );

        if ($citizen->getAp() < 1 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        // Moving disables the dig timer
        if ($dig_timer = $this->entity_manager->getRepository(DigTimer::class)->findActiveByCitizen($citizen)) {
            $dig_timer->setPassive(true);
            $this->entity_manager->persist( $dig_timer );
        }

        // Moving invalidates any escape timer the user may have had
        foreach ($this->entity_manager->getRepository(EscapeTimer::class)->findAllByCitizen($citizen) as $et)
            $this->entity_manager->remove( $et );

        $this->citizen_handler->setAP($citizen, true, -1);
        $zone->removeCitizen( $citizen );
        $new_zone->addCitizen( $citizen );

        try {
            // If no citizens remain in a zone, invalidate all associated escape timers
            if (!count($zone->getCitizens())) foreach ($this->entity_manager->getRepository(EscapeTimer::class)->findAllByZone($zone) as $et)
                $this->entity_manager->remove( $et );
            // If zombies can take control after leaving the zone and there are citizens remaining, install a grace escape timer
            elseif ( $cp_ok && !$this->check_cp( $zone ) )
                $zone->addEscapeTimer( (new EscapeTimer())->setTime( new DateTime('+15min') ) );
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        // If the new zone is controlled by citizens, invalidate all escape timers
        if ($this->check_cp( $new_zone )) foreach ($this->entity_manager->getRepository(EscapeTimer::class)->findAllByZone($new_zone) as $et)
            $this->entity_manager->remove( $et );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->persist($zone);
            $this->entity_manager->persist($new_zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException, [$e->getMessage()] );
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
        $this->deferZoneUpdate();
        return $this->generic_action_api( $parser, $handler);
    }

    /**
     * @Route("api/beyond/desert/item", name="beyond_desert_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function item_desert_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $this->deferZoneUpdate();
        $up_inv   = $this->getActiveCitizen()->getInventory();
        $down_inv = $this->getActiveCitizen()->getZone()->getFloor();
        return $this->generic_item_api( $up_inv, $down_inv, true, $parser, $handler);
    }

    /**
     * @Route("api/beyond/desert/escape", name="beyond_desert_escape_controller")
     * @return Response
     */
    public function escape_desert_api(): Response {
        $this->deferZoneUpdate();

        $citizen = $this->getActiveCitizen();
        if ($this->check_cp( $citizen->getZone() ) || $this->get_escape_timeout( $citizen ) > 0)
            return AjaxResponse::error( self::ErrorZoneUnderControl );

        if ($this->citizen_handler->isWounded( $citizen ))
            return AjaxResponse::error( self::ErrorAlreadyWounded );

        $this->citizen_handler->inflictWound( $citizen );

        try {
            $escape = (new EscapeTimer())
                ->setZone( $citizen->getZone() )
                ->setCitizen( $citizen )
                ->setTime( new DateTime('+1min') );
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->persist( $escape );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/attack", name="beyond_desert_attack_controller")
     * @param RandomGenerator $generator
     * @return Response
     */
    public function attack_desert_api(RandomGenerator $generator): Response {
        $this->deferZoneUpdate();

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if ($this->check_cp( $zone ) || $this->get_escape_timeout( $citizen ) > 0)
            return AjaxResponse::error( self::ErrorZoneUnderControl );

        if ($citizen->getAp() <= 0 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $this->citizen_handler->setAP( $citizen, true, -1 );
        if ($generator->chance( 0.1 ))
            $zone->setZombies( $zone->getZombies() - 1 );

        try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->persist( $zone );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/dig", name="beyond_desert_dig_controller")
     * @return Response
     */
    public function attack_dig_api(): Response {
        $this->deferZoneUpdate();

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if (!$this->check_cp( $zone ))
            return AjaxResponse::error( self::ErrorZoneBlocked );
        if ($zone->getX() === 0 && $zone->getY() === 0)
            return AjaxResponse::error( self::ErrorNotDiggable );

        try {
            $timer = $this->entity_manager->getRepository(DigTimer::class)->findActiveByCitizen( $citizen );
            if (!$timer) $timer = (new DigTimer())->setZone( $zone )->setCitizen( $citizen );
            else if ($timer->getTimestamp() > new DateTime())
                return AjaxResponse::error( self::ErrorNotDiggable );

            $timer->setPassive( false )->setTimestamp( new DateTime('-1sec') );
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->persist( $zone );
            $this->entity_manager->persist( $timer );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/beyond/desert/scavenge", name="beyond_desert_scavenge_controller")
     * @return Response
     */
    public function attack_scavenge_api(): Response {
        $this->deferZoneUpdate();

        $citizen = $this->getActiveCitizen();
        $zone = $citizen->getZone();

        if (!$this->check_cp( $zone ))
            return AjaxResponse::error( self::ErrorZoneBlocked );
        if ($zone->getX() === 0 && $zone->getY() === 0)
            return AjaxResponse::error( self::ErrorNotDiggable );

        if ($this->entity_manager->getRepository(DigRuinMarker::class)->findByCitizen( $citizen ))
            return AjaxResponse::error( self::ErrorNotDiggable );

        $dm = (new DigRuinMarker())->setCitizen( $citizen )->setZone( $zone );

        if ($zone->getRuinDigs() > 0) {
            $zone->setRuinDigs( $zone->getRuinDigs() - 1 );
            $group = $zone->getPrototype()->getDrops();
            $prototype = $group ? $this->random_generator->pickItemPrototypeFromGroup( $group ) : null;
            if ($prototype) {
                $item = $this->item_factory->createItem( $prototype );
                if ($item) {
                    $this->inventory_handler->placeItem( $citizen, $item, [ $citizen->getInventory(), $zone->getFloor() ] );
                    $this->entity_manager->persist( $item );
                    $this->entity_manager->persist( $citizen->getInventory() );
                    $this->entity_manager->persist( $zone->getFloor() );
                }
            }
        }

        try {
            $this->entity_manager->persist($dm);
            $this->entity_manager->persist($zone);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );
        }

        return AjaxResponse::success();
    }

}
