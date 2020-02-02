<?php

namespace App\Controller;

use App\Entity\Building;
use App\Entity\DailyUpgradeVote;
use App\Entity\ItemPrototype;
use App\Entity\Recipe;
use App\Entity\Zone;
use App\Response\AjaxResponse;
use App\Service\ActionHandler;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Structures\ItemRequest;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 */
class TownController extends InventoryAwareController implements TownInterfaceController
{
    const ErrorWellEmpty         = ErrorHelper::BaseTownErrors + 1;
    const ErrorWellLimitHit      = ErrorHelper::BaseTownErrors + 2;
    const ErrorWellNoWater       = ErrorHelper::BaseTownErrors + 3;
    const ErrorDoorAlreadyClosed = ErrorHelper::BaseTownErrors + 4;
    const ErrorDoorAlreadyOpen   = ErrorHelper::BaseTownErrors + 5;
    const ErrorNotEnoughRes      = ErrorHelper::BaseTownErrors + 6;


    protected function addDefaultTwigArgs( ?string $section = null, ?array $data = null ): array {
        $data = $data ?? [];

        $addons = [];
        $town = $this->getActiveCitizen()->getTown();
        foreach ($town->getBuildings() as $b) if ($b->getComplete()) {

            if ($b->getPrototype()->getMaxLevel() > 0)
                $addons['upgrade']  = ['Verbesserung des Tages', 'town_upgrades'];

            if ($b->getPrototype()->getName() === 'small_refine_#00')
                $addons['workshop'] = ['Werkstatt', 'town_workshop'];
        }

        $data['addons'] = $addons;
        return parent::addDefaultTwigArgs( $section, $data );
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
     * @Route("jx/town/upgrades", name="town_upgrades")
     * @return Response
     */
    public function addon_upgrades(): Response
    {
        $town = $this->getActiveCitizen()->getTown();
        $buildings = [];
        $max_votes = 0;
        foreach ($town->getBuildings() as $b) if ($b->getComplete()) {
            if ($b->getPrototype()->getMaxLevel() > 0)
                $buildings[] = $b;
            $max_votes = max($max_votes, $b->getDailyUpgradeVotes()->count());
        }

        if (empty($buildings)) return $this->redirect( $this->generateUrl('town_dashboard') );

        return $this->render( 'ajax/game/town/upgrades.html.twig', $this->addDefaultTwigArgs('upgrade', [
            'buildings' => $buildings,
            'max_votes' => $max_votes,
            'vote' => $this->getActiveCitizen()->getDailyUpgradeVote() ? $this->getActiveCitizen()->getDailyUpgradeVote()->getBuilding() : null,
        ]) );
    }

    /**
     * @Route("api/town/upgrades/vote", name="town_upgrades_vote_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function upgrades_votes_api(JSONRequestParser $parser): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if ($citizen->getDailyUpgradeVote())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        if (!$parser->has_all(['id'], true))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $id = (int)$parser->get('id');

        $building = $this->entity_manager->getRepository(Building::class)->find($id);
        if (!$building || $building->getTown()->getId() !== $town->getId())
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        try {
            $citizen->setDailyUpgradeVote( (new DailyUpgradeVote())->setBuilding( $building ) );
            $this->entity_manager->persist($citizen);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/town/workshop", name="town_workshop")
     * @param GameFactory $gf
     * @param InventoryHandler $iv
     * @return Response
     */
    public function addon_workshop(GameFactory $gf, InventoryHandler $iv): Response
    {
        $town = $this->getActiveCitizen()->getTown();
        $c_inv = $this->getActiveCitizen()->getInventory();
        $t_inv = $town->getBank();

        if (!$gf->getBuilding($town, 'small_refine_#00', true))
            return $this->redirect($this->generateUrl('town_dashboard'));

        $have_saw  = $iv->countSpecificItems( $c_inv, $this->entity_manager->getRepository( ItemPrototype::class )->findOneByName( 'saw_tool_#00' ) ) > 0;
        $have_manu = $gf->getBuilding($town, 'small_factory_#00', true) !== null;

        $recipes = $this->entity_manager->getRepository(Recipe::class)->findByType( Recipe::WorkshopType );
        $source_db = []; $result_db = [];
        foreach ($recipes as $recipe) {
            /** @var Recipe $recipe */
            $min_s = $min_r = PHP_INT_MAX;
            foreach ($recipe->getProvoking() as $proto)
                $min_s = min($min_s, $iv->countSpecificItems( $t_inv, $proto ));
            $source_db[ $recipe->getId() ] = $min_s === PHP_INT_MAX ? 0 : $min_s;

            foreach ($recipe->getResult()->getEntries() as $entry)
                $min_r = min($min_r, $iv->countSpecificItems( $t_inv, $entry->getPrototype() ));
            $result_db[ $recipe->getId() ] = $min_r === PHP_INT_MAX ? 0 : $min_r;
        }

        return $this->render( 'ajax/game/town/workshop.html.twig', $this->addDefaultTwigArgs('workshop', [
            'recipes' => $recipes,
            'saw' => $have_saw, 'manu' => $have_manu,
            'need_ap' => 3 - ($have_saw ? 1 : 0) - ($have_manu ? 1 : 0),
            'source' => $source_db, 'result' => $result_db,
        ]) );
    }

    /**
     * @Route("api/town/workshop/do", name="town_workshop_do_controller")
     * @param JSONRequestParser $parser
     * @param ActionHandler $ah
     * @return Response
     */
    public function workshop_do_api(JSONRequestParser $parser, ActionHandler $ah): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!$parser->has_all(['id'], true))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $id = (int)$parser->get('id');

        /** @var Recipe $recipe */
        $recipe = $this->entity_manager->getRepository(Recipe::class)->find( $id );
        if ($recipe === null || $recipe->getType() !== Recipe::WorkshopType)
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if (($error = $ah->execute_recipe( $citizen, $recipe, $remove, $message )) !== ActionHandler::ErrorNone )
            return AjaxResponse::error( $error );
        else try {
            $this->entity_manager->persist($town);
            $this->entity_manager->persist($citizen);
            foreach ($remove as $e) $this->entity_manager->remove( $e );
            $this->entity_manager->flush();
            if ($message) $this->addFlash( 'notice', $message );
            return AjaxResponse::success();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }
    }

    /**
     * @Route("jx/town/house", name="town_house")
     * @return Response
     */
    public function house(): Response
    {
        return $this->render( 'ajax/game/town/home.html.twig', $this->addDefaultTwigArgs('house', [
            'actions' => $this->getItemActions(),
            'chest' => $this->getActiveCitizen()->getHome()->getChest(),
            'chest_size' => $this->inventory_handler->getSize($this->getActiveCitizen()->getHome()->getChest()),
        ]) );
    }

    /**
     * @Route("api/town/house/item", name="town_house_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function item_house_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $up_inv   = $this->getActiveCitizen()->getInventory();
        $down_inv = $this->getActiveCitizen()->getHome()->getChest();
        return $this->generic_item_api( $up_inv, $down_inv, true, $parser, $handler);
    }

    /**
     * @Route("api/town/house/action", name="town_house_action_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function action_house_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        return $this->generic_action_api( $parser, $handler);
    }

    /**
     * @Route("jx/town/well", name="town_well")
     * @param GameFactory $gf
     * @return Response
     */
    public function well(GameFactory $gf): Response
    {
        $town = $this->getActiveCitizen()->getTown();
        $pump = $gf->getBuilding( $town, 'small_water_#00', true );

        return $this->render( 'ajax/game/town/well.html.twig', $this->addDefaultTwigArgs('well', [
            'rations_left' => $this->getActiveCitizen()->getTown()->getWell(),
            'first_take' => $this->getActiveCitizen()->getWellCounter()->getTaken() === 0,
            'allow_take' => $this->getActiveCitizen()->getWellCounter()->getTaken() < ($pump ? 2 : 1),
            'pump' => $pump,
        ]) );
    }

    /**
     * @Route("api/well/item", name="town_well_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @param ItemFactory $factory
     * @return Response
     */
    public function well_api(JSONRequestParser $parser, InventoryHandler $handler, ItemFactory $factory): Response {
        $direction = $parser->get('direction', '');

        if (in_array($direction, ['up','down'])) {
            $citizen = $this->getActiveCitizen();

            $town = $citizen->getTown();
            $wellLock = $citizen->getWellCounter();

            if ($direction == 'up') {

                if ($town->getWell() <= 0) return AjaxResponse::error(self::ErrorWellEmpty);
                if ($wellLock->getTaken() >= 2) return AjaxResponse::error(self::ErrorWellLimitHit); //ToDo: Fix the count!

                $inv_target = $citizen->getInventory();
                $inv_source = null;
                $item = $factory->createItem( 'water_#00' );

                if (($error = $handler->transferItem(
                    $citizen,
                    $item,$inv_source, $inv_target
                )) === InventoryHandler::ErrorNone) {
                    $wellLock->setTaken( $wellLock->getTaken()+1 );
                    $town->setWell( $town->getWell()-1 );
                    try {
                        $this->entity_manager->persist($item);
                        $this->entity_manager->persist($town);
                        $this->entity_manager->persist($wellLock);
                        $this->entity_manager->flush();
                    } catch (Exception $e) {
                        return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
                    }
                    return AjaxResponse::success();
                } else return AjaxResponse::error($error);
            } else {

                $items = $handler->fetchSpecificItems( $citizen->getInventory(), [new ItemRequest('water_#00')] );
                if (empty($items)) return AjaxResponse::error(self::ErrorWellNoWater);

                $inv_target = null;
                $inv_source = $citizen->getInventory();

                if (($error = $handler->transferItem(
                        $citizen,
                        $items[0],$inv_source, $inv_target
                    )) === InventoryHandler::ErrorNone) {
                    $town->setWell( $town->getWell()+1 );
                    try {
                        $this->entity_manager->remove($items[0]);
                        $this->entity_manager->persist($town);
                        $this->entity_manager->flush();
                    } catch (Exception $e) {
                        return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
                    }
                    return AjaxResponse::success();
                } else return AjaxResponse::error($error);
            }
        }

        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
    }


    /**
     * @Route("jx/town/bank", name="town_bank")
     * @return Response
     */
    public function bank(): Response
    {
        return $this->render( 'ajax/game/town/bank.html.twig', $this->addDefaultTwigArgs('bank', [
            'bank' => $this->renderInventoryAsBank( $this->getActiveCitizen()->getTown()->getBank() ),
        ]) );
    }

    /**
     * @Route("api/town/bank/item", name="town_bank_item_controller")
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @return Response
     */
    public function item_bank_api(JSONRequestParser $parser, InventoryHandler $handler): Response {
        $up_inv   = $this->getActiveCitizen()->getInventory();
        $down_inv = $this->getActiveCitizen()->getTown()->getBank();
        return $this->generic_item_api( $up_inv, $down_inv, true, $parser, $handler);
    }

    /**
     * @Route("jx/town/citizens", name="town_citizens")
     * @return Response
     */
    public function citizens(): Response
    {
        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs('citizens', [
            'town' => $this->getActiveCitizen()->getTown()
        ]) );
    }

    /**
     * @Route("api/town/constructions/build", name="town_constructions_build_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function construction_build_api(JSONRequestParser $parser): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!$parser->has_all(['id','ap'], true))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $id = (int)$parser->get('id');
        $ap = (int)$parser->get('ap');

        $building = $this->entity_manager->getRepository(Building::class)->find($id);
        if (!$building || $building->getTown()->getId() !== $town->getId() || $ap <= 0)
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $ap = max(0,min( $ap, $building->getPrototype()->getAp() - $building->getAp() ) );
        if ($citizen->getAp() < $ap || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $res = $items = [];
        if (!$building->getComplete() && $building->getPrototype()->getResources())
            foreach ($building->getPrototype()->getResources()->getEntries() as $entry)
                $res[] = new ItemRequest( $entry->getPrototype()->getName(), $entry->getChance() );
        if (!empty($res)) {
            $items = $this->inventory_handler->fetchSpecificItems($town->getBank(), $res);
            if (empty($items)) return AjaxResponse::error( self::ErrorNotEnoughRes );
        }

        $was_completed = $building->getComplete();

        $this->citizen_handler->setAP($citizen, true, -$ap);
        $building->setAp( $building->getAp() + $ap );
        $building->setComplete( $building->getComplete() || $building->getAp() >= $building->getPrototype()->getAp() );
        if (!$was_completed && $building->getComplete())
            foreach ($items as $item) {
                $town->getBank()->removeItem( $item );
                $this->entity_manager->remove( $item );
            }

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->persist($building);
            $this->entity_manager->persist($town);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/town/constructions", name="town_constructions")
     * @return Response
     */
    public function constructions(): Response
    {
        $buildings = $this->getActiveCitizen()->getTown()->getBuildings();

        $root = [];
        $dict = [];
        $items = [];

        foreach ($buildings as $building) {
            $dict[ $building->getPrototype()->getId() ] = [];
            if (!$building->getPrototype()->getParent()) $root[] = $building;
            if (!$building->getComplete() && !empty($building->getPrototype()->getResources()))
                foreach ($building->getPrototype()->getResources()->getEntries() as $ressource)
                    if (!isset($items[$ressource->getPrototype()->getId()]))
                        $items[$ressource->getPrototype()->getId()] = $this->inventory_handler->countSpecificItems( $this->getActiveCitizen()->getTown()->getBank(), $ressource->getPrototype() );
        }

        foreach ($buildings as $building)
            if ($building->getPrototype()->getParent())
                $dict[$building->getPrototype()->getParent()->getId()][] = $building;

        return $this->render( 'ajax/game/town/construction.html.twig', $this->addDefaultTwigArgs('constructions', [
            'root_cats'  => $root,
            'dictionary' => $dict,
            'bank' => $items,
        ]) );
    }

    /**
     * @Route("api/town/door/control", name="town_door_control_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function door_control_api(JSONRequestParser $parser): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!($action = $parser->get('action')) || !in_array($action, ['open','close']))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($action === 'open'  && $town->getDoor())
            return AjaxResponse::error( self::ErrorDoorAlreadyOpen );
        if ($action === 'close' && !$town->getDoor())
            return AjaxResponse::error( self::ErrorDoorAlreadyClosed );

        if ($citizen->getAp() < 1 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $this->citizen_handler->setAP($citizen, true, -1);
        $town->setDoor( $action === 'open' );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->persist($town);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/town/door/exit", name="town_door_exit_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function door_exit_api(JSONRequestParser $parser): Response {
        $citizen = $this->getActiveCitizen();
        $zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition($citizen->getTown(), 0, 0);

        if (!$zone)
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );

        $zone->addCitizen( $citizen );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/town/door", name="town_door")
     * @return Response
     */
    public function door(): Response
    {
        $zones = []; $range_x = [PHP_INT_MAX,PHP_INT_MIN]; $range_y = [PHP_INT_MAX,PHP_INT_MIN];
        foreach ($this->getActiveCitizen()->getTown()->getZones() as $zone) {
            $x = $zone->getX();
            $y = $zone->getY();

            $range_x = [ min($range_x[0], $x), max($range_x[1], $x) ];
            $range_y = [ min($range_y[0], $y), max($range_y[1], $y) ];

            if (!isset($zones[$x])) $zones[$x] = [];
            $zones[$x][$y] = $zone;

        }
        return $this->render( 'ajax/game/town/door.html.twig', $this->addDefaultTwigArgs('door', [
            'town'  =>  $this->getActiveCitizen()->getTown(),
            'zones' =>  $zones,
            'pos_x'  => $this->getActiveCitizen()->getZone() ? $this->getActiveCitizen()->getZone()->getX() : 0,
            'pos_y'  => $this->getActiveCitizen()->getZone() ? $this->getActiveCitizen()->getZone()->getY() : 0,
            'map_x0' => $range_x[0],
            'map_x1' => $range_x[1],
            'map_y0' => $range_y[0],
            'map_y1' => $range_y[1],
        ]) );
    }
}
