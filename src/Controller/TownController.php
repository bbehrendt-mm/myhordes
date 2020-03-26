<?php

namespace App\Controller;

use App\Entity\Building;
use App\Entity\Citizen;
use App\Entity\CitizenHomePrototype;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradeCosts;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\ExpeditionRoute;
use App\Entity\ItemPrototype;
use App\Entity\TownLogEntry;
use App\Entity\Zone;
use App\Response\AjaxResponse;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ErrorHelper;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\TownHandler;
use App\Structures\ItemRequest;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

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

            if ($b->getPrototype()->getName() === 'item_tagger_#00')
                $addons['watchtower'] = ['Wachturm', 'town_watchtower'];

            if ($b->getPrototype()->getName() === 'small_refine_#00')
                $addons['workshop'] = ['Werkstatt', 'town_workshop'];
        }

        $data['addons'] = $addons;
        $data['home'] = $this->getActiveCitizen()->getHome();
        return parent::addDefaultTwigArgs( $section, $data );
    }

    /**
     * @Route("jx/town/dashboard", name="town_dashboard")
     * @param TownHandler $th
     * @return Response
     */
    public function dashboard(TownHandler $th): Response
    {
        $town = $this->getActiveCitizen()->getTown();

        $has_zombie_est_today    = !empty($th->getBuilding($town, 'item_tagger_#00'));
        $has_zombie_est_tomorrow = !empty($th->getBuilding($town, 'item_tagger_#02'));

        $z_today_min = $z_today_max = $z_tomorrow_min = $z_tomorrow_max = null; $z_q = 0;
        if ($has_zombie_est_today) $z_q = $th->get_zombie_estimation_quality( $town, 0, $z_today_min, $z_today_max );
        if ($has_zombie_est_today && $has_zombie_est_tomorrow && $z_q >= 1) $th->get_zombie_estimation_quality( $town, 1, $z_tomorrow_min, $z_tomorrow_max );

        return $this->render( 'ajax/game/town/dashboard.html.twig', $this->addDefaultTwigArgs(null, [
            'town' => $town,
            'def' => $th->calculate_town_def($town),
            'zeds_today'    => [ $has_zombie_est_today, $z_today_min, $z_today_max ],
            'zeds_tomorrow' => [ $has_zombie_est_tomorrow, $z_tomorrow_min, $z_tomorrow_max ],
        ]) );
    }

    /**
     * @Route("jx/town/visit/{id}", name="town_visit", requirements={"id"="\d+"})
     * @param int $id
     * @param EntityManagerInterface $em
     * @param TownHandler $th
     * @return Response
     */
    public function visit(int $id, EntityManagerInterface $em, TownHandler $th): Response
    {
        if ($id === $this->getActiveCitizen()->getId())
            return $this->redirect($this->generateUrl('town_house'));

        /** @var Citizen $c */
        $c = $em->getRepository(Citizen::class)->find( $id );
        if (!$c || $c->getTown()->getId() !== $this->getActiveCitizen()->getTown()->getId())
            return $this->redirect($this->generateUrl('town_dashboard'));

        $home = $c->getHome();

        $th->calculate_home_def($home, $summary);
        $deco = 0;
        foreach ($home->getChest()->getItems() as $item)
            $deco += $item->getPrototype()->getDeco();

        $town = $this->getActiveCitizen()->getTown();
        return $this->render( 'ajax/game/town/home_foreign.html.twig', $this->addDefaultTwigArgs('citizens', [
            'citizen' => $c,
            'home' => $home,
            'actions' => $this->getItemActions(),
            'chest' => $home->getChest(),
            'chest_size' => $this->inventory_handler->getSize($home->getChest()),
            'has_cremato' => $th->getBuilding($town, 'item_hmeat_#00', true) !== null,

            'def' => $summary,
            'deco' => $deco,

            'log' => $this->renderLog( -1, $c, false, null, 10 )->getContent(),
            'day' => $c->getTown()->getDay()
        ]) );
    }

    /**
     * @Route("api/town/visit/{id}/log", name="town_visit_log_controller")
     * @param int $id
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_visit_api(int $id, JSONRequestParser $parser): Response {
        if ($id === $this->getActiveCitizen()->getId())
            return $this->redirect($this->generateUrl('town_house_log_controller'));

        /** @var Citizen $c */
        $c = $this->entity_manager->getRepository(Citizen::class)->find( $id );
        if (!$c || $c->getTown()->getId() !== $this->getActiveCitizen()->getTown()->getId())
            $c = null;

        return $this->renderLog((int)$parser->get('day', -1), $c, false, null, $c ===  null ? 0 : null);
    }

    /**
     * @Route("api/town/visit/{id}/dispose", name="town_visit_dispose_controller")
     * @param int $id
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @param TownHandler $th
     * @param ItemFactory $if
     * @return Response
     */
    public function dispose_visit_api(int $id, EntityManagerInterface $em, JSONRequestParser $parser, TownHandler $th, ItemFactory $if): Response {
        if ($id === $this->getActiveCitizen()->getId())
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable );

        $ac = $this->getActiveCitizen();

        /** @var Citizen $c */
        $c = $em->getRepository(Citizen::class)->find( $id );
        if (!$c || $c->getTown()->getId() !== $this->getActiveCitizen()->getTown()->getId() || $c->getAlive() || !$c->getHome()->getHoldsBody())
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable );

        $action = (int)$parser->get('action');

        if ($action < 1 || $action > 3)
            return AjaxResponse::error(ErrorHelper::ErrorActionNotAvailable );

        $spawn_items = [];

        switch ($action) {

            case 1:
                if ($ac->getAp() <= 0 || $this->citizen_handler->isTired( $ac ))
                    return AjaxResponse::error( ErrorHelper::ErrorNoAP );
                $this->citizen_handler->setAP($ac, true, -1);
                break;
            case 2:
                $items = $this->inventory_handler->fetchSpecificItems( $ac->getInventory(), [new ItemRequest('water_#00')] );
                if (!$items) return AjaxResponse::error(ErrorHelper::ErrorItemsMissing );
                $ac->getInventory()->removeItem( $items[0] );
                $em->remove( $items[0] );
                break;
            case 3:
                $town = $ac->getTown();
                if (!$th->getBuilding($town, 'item_hmeat_#00', true))
                    return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
                $spawn_items[] = [ $em->getRepository( ItemPrototype::class )->findOneByName( 'hmeat_#00' ), 2 ];
                break;
        }

        foreach ($spawn_items as $item_spec)
            for ($i = 0; $i < $item_spec[1]; $i++)
                $ac->getTown()->getBank()->addItem( $if->createItem( $item_spec[0] ) );
        $em->persist( $this->log->citizenDisposal( $ac, $c, $action, $spawn_items ) );
        $c->getHome()->setHoldsBody( false );

        try {
            $em->persist($ac);
            $em->persist($c);
            $em->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/town/house", name="town_house")
     * @param EntityManagerInterface $em
     * @param TownHandler $th
     * @return Response
     */
    public function house(EntityManagerInterface $em, TownHandler $th): Response
    {
        $town = $this->getActiveCitizen()->getTown();
        $home = $this->getActiveCitizen()->getHome();
        $home_next_level = $em->getRepository( CitizenHomePrototype::class )->findOneByLevel(
            $home->getPrototype()->getLevel() + 1
        );
        $home_next_level_requirement = null;
        if ($home_next_level && $home_next_level->getRequiredBuilding())
            $home_next_level_requirement = $th->getBuilding( $town, $home_next_level->getRequiredBuilding(), true ) ? null : $home_next_level->getRequiredBuilding();

        $upgrade_proto = [];
        $upgrade_proto_lv = [];
        $upgrade_cost = [];
        if ($home->getPrototype()->getAllowSubUpgrades()) {

            $all_protos = $em->getRepository(CitizenHomeUpgradePrototype::class)->findAll();
            foreach ($all_protos as $proto) {
                /**
                 * @var CitizenHomeUpgradePrototype $proto
                 * @var CitizenHomeUpgrade $n
                 */
                $n = $em->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype( $home, $proto );

                $upgrade_proto[$proto->getId()] = $proto;
                $upgrade_proto_lv[$proto->getId()] = $n ? $n->getLevel() : 0;
                $upgrade_cost[$proto->getId()] = $em->getRepository(CitizenHomeUpgradeCosts::class)->findOneByPrototype( $proto, $upgrade_proto_lv[$proto->getId()] + 1 );
            }
        }

        $th->calculate_home_def($home, $summary);
        $deco = 0;
        foreach ($home->getChest()->getItems() as $item)
            $deco += $item->getPrototype()->getDeco();

        return $this->render( 'ajax/game/town/home.html.twig', $this->addDefaultTwigArgs('house', [
            'home' => $home,
            'actions' => $this->getItemActions(),
            'recipes' => $this->getItemCombinations(true),
            'chest' => $home->getChest(),
            'chest_size' => $this->inventory_handler->getSize($home->getChest()),
            'next_level' => $home_next_level,
            'next_level_req' => $home_next_level_requirement,
            'upgrades' => $upgrade_proto,
            'upgrade_levels' => $upgrade_proto_lv,
            'upgrade_costs' => $upgrade_cost,

            'def' => $summary,
            'deco' => $deco,

            'log' => $this->renderLog( -1, $this->getActiveCitizen(), false, null, 10 )->getContent(),
            'day' => $this->getActiveCitizen()->getTown()->getDay()
        ]) );
    }

    /**
     * @Route("api/town/house/log", name="town_house_log_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_house_api(JSONRequestParser $parser): Response {
        return $this->renderLog((int)$parser->get('day', -1), $this->getActiveCitizen(), false, null, null);
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
     * @Route("api/town/house/recipe", name="town_house_recipe_controller")
     * @param JSONRequestParser $parser
     * @param ActionHandler $handler
     * @return Response
     */
    public function recipe_house_api(JSONRequestParser $parser, ActionHandler $handler): Response {
        return $this->generic_recipe_api( $parser, $handler);
    }

    /**
     * @Route("api/town/house/upgrade", name="town_house_upgrade_controller")
     * @param EntityManagerInterface $em
     * @param InventoryHandler $ih
     * @param CitizenHandler $ch
     * @return Response
     */
    public function upgrade_house_api(EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch): Response {
        $citizen = $this->getActiveCitizen();
        $home = $citizen->getHome();
        $next = $em->getRepository(CitizenHomePrototype::class)->findOneByLevel( $home->getPrototype()->getLevel() + 1 );
        if (!$next) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($ch->isTired( $citizen ) || $citizen->getAp() < $next->getAp()) return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $items = [];
        if ($next->getResources()) {
            $items = $ih->fetchSpecificItems( [$home->getChest(),$citizen->getInventory()], $next->getResources() );
            if (!$items)  return AjaxResponse::error( ErrorHelper::ErrorItemsMissing );
        }

        $home->setPrototype($next);
        $ch->setAP($citizen, true, -$next->getAp());
        foreach ($items as $item) {
            $item->getInventory()->removeItem($item);
            $em->remove($item);
        }
        $em->persist( $this->log->homeUpgrade( $citizen ) );
        $em->persist($home);
        $em->persist($citizen);
        $em->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/town/house/describe", name="town_house_describe_controller")
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @param Translator $t
     * @return Response
     */
    public function describe_house_api(EntityManagerInterface $em, JSONRequestParser $parser, TranslatorInterface $t): Response {
        $new_desc = $parser->get('desc');
        if ($new_desc !== null) $new_desc = mb_substr($new_desc,0,64);

        $this->getActiveCitizen()->getHome()->setDescription( $new_desc );
        try {
            $em->persist($this->getActiveCitizen()->getHome());
            $em->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        $this->addFlash( 'notice', $t->trans('Du hast deine Beschreibung geändert.', [], 'game') );
        return AjaxResponse::success();
    }

    /**
     * @Route("api/town/house/extend", name="town_house_extend_controller")
     * @param EntityManagerInterface $em
     * @param InventoryHandler $ih
     * @param CitizenHandler $ch
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function extend_house_api(EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch, JSONRequestParser $parser): Response {
        if (!$parser->has('id')) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        $id = (int)$parser->get('id');
        if (!$id) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $proto = $em->getRepository(CitizenHomeUpgradePrototype::class)->find( $id );
        if (!$proto) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $citizen = $this->getActiveCitizen();
        $home = $citizen->getHome();

        if (!$citizen->getProfession()->getHeroic())
            return AjaxResponse::error(ErrorHelper::ErrorMustBeHero);

        $current = $em->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype($home, $proto);
        $costs = $em->getRepository(CitizenHomeUpgradeCosts::class)->findOneByPrototype( $proto, $current ? $current->getLevel()+1 : 1 );

        if (!$costs) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($ch->isTired( $citizen ) || $citizen->getAp() < $costs->getAp()) return AjaxResponse::error( ErrorHelper::ErrorNoAP );
        $items = [];
        if ($costs->getResources()) {
            $items = $ih->fetchSpecificItems( [$home->getChest(),$citizen->getInventory()], $costs->getResources() );
            if (!$items)  return AjaxResponse::error( ErrorHelper::ErrorItemsMissing );
        }

        if (!$current) $current = (new CitizenHomeUpgrade())->setPrototype($proto)->setHome($home)->setLevel(1);
        else $current->setLevel( $current->getLevel()+1 );

        $ch->setAP($citizen, true, -$costs->getAp());
        foreach ($items as $item) {
            $item->getInventory()->removeItem($item);
            $em->remove($item);
        }

        $em->persist($current);
        $em->persist($citizen);
        $em->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/town/well", name="town_well")
     * @param TownHandler $th
     * @return Response
     */
    public function well(TownHandler $th): Response
    {
        $town = $this->getActiveCitizen()->getTown();
        $pump = $th->getBuilding( $town, 'small_water_#00', true );

        return $this->render( 'ajax/game/town/well.html.twig', $this->addDefaultTwigArgs('well', [
            'rations_left' => $this->getActiveCitizen()->getTown()->getWell(),
            'first_take' => $this->getActiveCitizen()->getWellCounter()->getTaken() === 0,
            'allow_take' => $this->getActiveCitizen()->getWellCounter()->getTaken() < ($pump ? 2 : 1),
            'pump' => $pump,

            'log' => $this->renderLog( -1, null, false, TownLogEntry::TypeWell, 10 )->getContent(),
            'day' => $this->getActiveCitizen()->getTown()->getDay()
        ]) );
    }

    /**
     * @Route("api/town/well/log", name="town_well_log_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_well_api(JSONRequestParser $parser): Response {
        return $this->renderLog((int)$parser->get('day', -1), null, false, TownLogEntry::TypeWell, null);
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
                    $this->entity_manager->persist( $this->log->wellLog( $citizen, $wellLock->getTaken() >= 1 ) );
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
                        $this->entity_manager->persist( $this->log->wellAdd( $citizen, $items[0], 1) );
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
            'log' => $this->renderLog( -1, null, false, TownLogEntry::TypeBank, 10 )->getContent(),
            'day' => $this->getActiveCitizen()->getTown()->getDay()
        ]) );
    }

    /**
     * @Route("api/town/bank/log", name="town_bank_log_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_bank_api(JSONRequestParser $parser): Response {
        return $this->renderLog((int)$parser->get('day', -1), null, false, TownLogEntry::TypeBank, null);
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
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function citizens(EntityManagerInterface $em): Response
    {
        $hidden = [];
        foreach ($this->getActiveCitizen()->getTown()->getCitizens() as $c)
            $hidden[$c->getId()] = (bool)($em->getRepository(CitizenHomeUpgrade::class)->findOneByPrototype($c->getHome(),
                $em->getRepository(CitizenHomeUpgradePrototype::class)->findOneByName('curtain')
            ));

        return $this->render( 'ajax/game/town/citizen.html.twig', $this->addDefaultTwigArgs('citizens', [
            'citizens' => $this->getActiveCitizen()->getTown()->getCitizens(),
            'me' => $this->getActiveCitizen(),
            'hidden' => $hidden,
        ]) );
    }

    /**
     * @Route("api/town/constructions/build", name="town_constructions_build_controller")
     * @param JSONRequestParser $parser
     * @param TownHandler $th
     * @return Response
     */
    public function construction_build_api(JSONRequestParser $parser, TownHandler $th): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!$parser->has_all(['id','ap'], true))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
        $id = (int)$parser->get('id');
        $ap = (int)$parser->get('ap');

        /** @var Building|null $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($id);
        if (!$building || $building->getTown()->getId() !== $town->getId() || $ap <= 0)
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $ap = max(0,min( $ap, $building->getPrototype()->getAp() - $building->getAp() ) );
        if (($citizen->getAp() + $citizen->getBp()) < $ap || $this->citizen_handler->isTired( $citizen ))
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
        if ($th->getBuilding($town, 'item_rp_book2_#00', true))
            $this->entity_manager->persist( $this->log->constructionsInvestAP( $citizen, $building->getPrototype(), $ap ) );

        $this->citizen_handler->deductAPBP( $citizen, $ap );
        $building->setAp( $building->getAp() + $ap );
        $building->setComplete( $building->getComplete() || $building->getAp() >= $building->getPrototype()->getAp() );

        if (!$was_completed && $building->getComplete()) {
            foreach ($items as $item) {
                $town->getBank()->removeItem( $item );
                $this->entity_manager->remove( $item );
            }
            $this->entity_manager->persist( $this->log->constructionsBuildingComplete( $citizen, $building->getPrototype() ) );
            $th->triggerBuildingCompletion( $town, $building );
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

            'log' => $this->renderLog( -1, null, false, TownLogEntry::TypeConstruction, 10 )->getContent(),
            'day' => $this->getActiveCitizen()->getTown()->getDay()
        ]) );
    }

    /**
     * @Route("api/town/constructions/log", name="town_constructions_log_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_constructions_api(JSONRequestParser $parser): Response {
        return $this->renderLog((int)$parser->get('day', -1), null, false, TownLogEntry::TypeConstruction, null);
    }

    /**
     * @Route("api/town/door/control", name="town_door_control_controller")
     * @param JSONRequestParser $parser
     * @param TownHandler $th
     * @return Response
     */
    public function door_control_api(JSONRequestParser $parser, TownHandler $th): Response {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!($action = $parser->get('action')) || !in_array($action, ['open','close']))
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($action === 'open'  && $town->getDoor())
            return AjaxResponse::error( self::ErrorDoorAlreadyOpen );
        if ($action === 'open'  && $this->door_is_locked($th))
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );
        if ($action === 'close' && !$town->getDoor())
            return AjaxResponse::error( self::ErrorDoorAlreadyClosed );

        if ($citizen->getAp() < 1 || $this->citizen_handler->isTired( $citizen ))
            return AjaxResponse::error( ErrorHelper::ErrorNoAP );

        $this->citizen_handler->setAP($citizen, true, -1);
        $town->setDoor( $action === 'open' );

        $this->entity_manager->persist( $this->log->doorControl( $citizen, $action === 'open' ) );

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
        if (!$this->getActiveCitizen()->getTown()->getDoor())
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $citizen = $this->getActiveCitizen();
        $zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition($citizen->getTown(), 0, 0);

        if (!$zone)
            return AjaxResponse::error( ErrorHelper::ErrorInternalError );

        $this->entity_manager->persist( $this->log->doorPass( $citizen, false ) );
        $zone->addCitizen( $citizen );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        return AjaxResponse::success();
    }

    private function door_is_locked(TownHandler $th): bool {
        $town = $this->getActiveCitizen()->getTown();
        if ( !$town->getDoor() && (($s = $this->time_keeper->secondsUntilNextAttack(null, true)) <= 1800) ) {
            if ($th->getBuilding( $town, 'small_door_closed_#02', true )) {
                if ($s <= 60) return true;
            } elseif ($th->getBuilding( $town, 'small_door_closed_#01', true )) {
                if ($s <= 1800) return true;
            } elseif ($th->getBuilding( $town, 'small_door_closed_#00', true )) {
                if ($s <= 1200) return true;
            }
        }
        return false;
    }

    /**
     * @Route("jx/town/door", name="town_door")
     * @param TownHandler $th
     * @return Response
     */
    public function door(TownHandler $th): Response
    {
        $door_locked = $this->door_is_locked($th);
        return $this->render( 'ajax/game/town/door.html.twig', $this->addDefaultTwigArgs('door', array_merge([
            'town'  =>  $this->getActiveCitizen()->getTown(),
            'door_locked' => $door_locked,
            'log' => $this->renderLog( -1, null, false, TownLogEntry::TypeDoor, 10 )->getContent(),
            'day' => $this->getActiveCitizen()->getTown()->getDay()
        ], $this->get_map_blob())) );
    }

    /**
     * @Route("api/town/door/log", name="town_door_log_controller")
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_door_api(JSONRequestParser $parser): Response {
        return $this->renderLog((int)$parser->get('day', -1), null, false, TownLogEntry::TypeDoor, null);
    }

    /**
     * @Route("api/town/planner/submit", name="town_planner_route_submit_controller")
     * @param JSONRequestParser $parser
     * @param TranslatorInterface $trans
     * @return Response
     */
    public function planner_submit_api(JSONRequestParser $parser, TranslatorInterface $trans): Response {
        $citizen = $this->getActiveCitizen();

        $name = $parser->get('name', '');
        if (mb_strlen( $name ) > 32 || mb_strlen( $name ) < 3)
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        $data = $parser->get('data', []);
        if (!$data || !is_array($data) || count($data) > 32 || count($data) < 2)
            return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        if ($citizen->getExpeditionRoutes()->count() >= 12)
            return AjaxResponse::error( ErrorHelper::ErrorActionNotAvailable );

        $last = null; $ap = 0;
        foreach ($data as $entry)
            if (!is_array($entry) && count($entry) !== 2) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
            else {
                list($x,$y) = $entry;
                if (!is_int($x) || !is_int($y)) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                if (!$this->entity_manager->getRepository(Zone::class)->findOneByPosition($citizen->getTown(), $x, $y))
                    return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

                if ($last !== null) {
                    if ($last[0] !== $x && $last[1] !== $y) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                    if ($last[0] === $x && $last[1] === $y) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );
                    $ap += (abs($last[0] - $x) + abs($last[1] - $y));
                }
                $last = [$x,$y];
            }

        $is_pro_route = $data[0] !== [0,0] || $data[count($data)-1] !== [0,0];
        if ($is_pro_route && !$citizen->getProfession()->getHeroic())
            return AjaxResponse::error( ErrorHelper::ErrorMustBeHero );

        $citizen->addExpeditionRoute(
            (new ExpeditionRoute())
                ->setLabel($name)
                ->setOwner($citizen)
                ->setLength($ap)
                ->setData( $data )
        );

        try {
            $this->entity_manager->persist($citizen);
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error( ErrorHelper::ErrorDatabaseException );
        }

        $this->addFlash( 'notice', $trans->trans('Deine Route wurde gespeichert.', [], 'game') );
        return AjaxResponse::success();
    }

    /**
     * @Route("jx/town/planner", name="town_planner")
     * @return Response
     */
    public function planner(): Response
    {
        return $this->render( 'ajax/game/town/planner.html.twig', $this->addDefaultTwigArgs('door', array_merge([
            'town'  =>  $this->getActiveCitizen()->getTown(),
            'allow_extended' => $this->getActiveCitizen()->getProfession()->getHeroic()
        ], $this->get_map_blob())) );
    }
}
