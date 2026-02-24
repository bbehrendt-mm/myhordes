<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\Building;
use App\Entity\BuildingPrototype;
use App\Entity\Town;
use App\Enum\EventStages\BuildingEffectStage;
use App\Enum\EventStages\BuildingValueQuery;
use App\Response\AjaxResponse;
use App\Service\Actions\Game\InitializeTownBuildingsAction;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\EventProxyService;
use App\Service\GameProfilerService;
use App\Service\JSONRequestParser;
use App\Service\TownHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownBuildingsController extends AdminActionController
{
    /**
     * @param EventProxyService $events
     * @param Town $town
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/buildings', name: 'admin_town_buildings')]
    #[IsGranted('spy', 'town')]
    public function town_explorer_buildings(EventProxyService $events, Town $town): Response {
		$root = [];
		$dict = [];
		$inTown = [];

		foreach ($this->entity_manager->getRepository(BuildingPrototype::class)->findAll() as $building) {
			/** @var BuildingPrototype $building */
			$dict[$building->getId()] = [];
			if (!$building->getParent())
				$root[] = $building;
		}

		foreach ($this->entity_manager->getRepository(BuildingPrototype::class)->findAll() as $building) {
			/** @var BuildingPrototype $building */
			if ($building->getParent()) {
				$dict[$building->getParent()->getId()][] = $building;
			}

			$available = $this->entity_manager->getRepository(Building::class)->findOneBy(['town' => $town, 'prototype' => $building]);
			if ($available)
				$inTown[$building->getId()] = $available;
		}

        $workshopBonus = $events->queryTownParameter( $town, BuildingValueQuery::ConstructionAPRatio );
        $hpToAp = $events->queryTownParameter( $town, BuildingValueQuery::RepairAPRatio );

		return $this->render('ajax/manage/towns/explorer_buildings.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
			'tab' => "buildings",
			'dictBuildings' => $dict,
			'rootBuildings' => $root,
			'availBuldings' => $inTown,
			'workshopBonus' => $workshopBonus,
			'hpToAp' => $hpToAp
		])));
	}


    /**
     * @param Town $town
     * @param JSONRequestParser $parser The JSON request parser
     * @param TownHandler $th The town handler
     * @param GameProfilerService $gps
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/buildings/add', name: 'admin_town_add_building')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_add_building(Town $town, JSONRequestParser $parser, TownHandler $th, GameProfilerService $gps): Response
    {
        if (!$parser->has_all(['prototype_id', 'act'])) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $proto_id = $parser->get("prototype_id");
        $act = $parser->get('act');
        if(!is_array($proto_id)) {
            $proto_id = [$proto_id];
        }

        foreach ($proto_id as $pid) {
            $proto = $this->entity_manager->getRepository(BuildingPrototype::class)->find($pid);
            if (!$proto)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if($act) {
                $th->addBuilding($town, $proto);
                $gps->recordBuildingDiscovered($proto, $town, null, 'debug');
            } else {
                $th->removeBuilding($town, $proto);
            }
        }

        $this->clearTownCaches($town);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param EntityManagerInterface $em
     * @param ConfMaster $conf
     * @param InitializeTownBuildingsAction $action
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/buildings/reset', name: 'admin_town_reset_buildings')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_reset_buildings(Town $town, EntityManagerInterface $em, ConfMaster $conf, InitializeTownBuildingsAction $action): Response
    {
        foreach ($town->getBuildings() as $b)
            $em->remove( $b );
        $town->getBuildings()->clear();
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        ($action)($town, $conf->getTownConfiguration( $town ), false);

        $this->clearTownCaches($town);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The JSON request parser
     * @param EventProxyService $events
     * @return Response
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/buildings/set-ap', name: 'admin_town_set_building_ap')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_set_building_ap(Town $town, JSONRequestParser $parser, EventProxyService $events): Response
    {
        if (!$parser->has_all(['building', 'ap'])) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $building_id = $parser->get("building");

        /** @var Building $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($building_id);
        if (!$building || $building->getTown() !== $town)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $ap = intval($parser->get("ap"));

        if ($ap >= $building->getPrototypeAP()) {
            $ap = $building->getPrototypeAP();
        }

        $building->setAp($ap);

        if ($building->getAp() >= $building->getPrototypeAP())
            $events->buildingConstruction( $building, 'debug' );
        elseif ($building->getAp() <= 0) {
            $events->buildingDestruction($building, 'debug', false);
            $events->buildingDestruction($building, 'debug', true);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->persist($building);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The JSON request parser
     * @param EventProxyService $events
     * @return Response
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/buildings/set-hp', name: 'admin_town_set_building_hp')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_set_building_hp(Town $town, JSONRequestParser $parser, EventProxyService $events): Response
    {
        if (!$parser->has_all(['building', 'hp'])) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $building_id = $parser->get("building");

        /** @var Building $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($building_id);
        if (!$building)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $workshopBonus = $events->queryTownParameter( $town, BuildingValueQuery::ConstructionAPRatio );
        $hpToAp = $events->queryTownParameter( $town, BuildingValueQuery::RepairAPRatio );

        $hp = $parser->get_int("hp") * $hpToAp;

        if ($hp >= $building->getPrototype()->getHp()) {
            $hp = $building->getPrototype()->getHp();
        }

        $impervious = $building->getPrototype()->getImpervious();
        if (in_array($building->getPrototype()->getName(), ['small_arma_#00'])) $impervious = false;

        if (!$building->getComplete() || ($hp < $building->getPrototype()->getHp() && $impervious))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $building->setHp($hp);

        if ($building->getHp() <= 0) {
            $events->buildingDestruction($building, 'debug', false);
            $events->buildingDestruction($building, 'debug', true);
        } else {
			if($building->getPrototype()->getDefense() > 0) {
				$newDef = min($building->getPrototype()->getDefense(), $building->getPrototype()->getDefense() * $building->getHp() / $building->getPrototype()->getHp());
				$building->setDefense((int)floor($newDef));
			}
		}

        $this->clearTownCaches($town);
        $this->entity_manager->persist($building);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The JSON request parser
     * @param EventProxyService $events
     * @return Response
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/buildings/set-level', name: 'admin_town_set_building_level')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_set_building_level(Town $town, JSONRequestParser $parser, EventProxyService $events): Response
    {
        if (!$parser->has_all(['building', 'level']))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $building_id = $parser->get("building");

        /** @var Building $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($building_id);
        if (!$building || $building->getTown() !== $town || !$building->getComplete())
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $level = $parser->get_int("level");

        if ($level < 0 || $level > $building->getPrototype()->getMaxLevel())
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $upgrade = $level > $building->getLevel();

        $building->setLevel($level);

        if ($upgrade) {
            $events->buildingUpgrade( $building, true );
            $events->buildingUpgrade( $building, false );
        }

        $this->clearTownCaches($town);
        $this->entity_manager->persist($building);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The JSON request parser
     * @param EventProxyService $events
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/buildings/set-difficulty', name: 'admin_town_set_building_difficulty_level')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_set_building_difficulty_level(Town $town, JSONRequestParser $parser, EventProxyService $events): Response
    {
        if (!$parser->has_all(['building', 'level']))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $building_id = $parser->get("building");

        /** @var Building $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($building_id);
        if (!$building || $building->getTown() !== $town || $building->getComplete())
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$building->getPrototype()->isHasHardMode())
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $level = $parser->get_int("level");

        if ($level < -1 || $level > 2)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $building->setDifficultyLevel($level);

        $this->clearTownCaches($town);
        $this->entity_manager->persist($building);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser The JSON request parser
     * @param EventProxyService $events
     * @return Response
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/buildings/exec-nightly', name: 'admin_town_trigger_building_nightly_effect')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function town_trigger_building_nightly_effect(Town $town, JSONRequestParser $parser, EventProxyService $events): Response
    {
        if (!$parser->has_all(['building']))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $building_id = $parser->get("building");

        /** @var Building $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($building_id);
        if (!$building || $building->getTown() !== $town || !$building->getComplete())
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach (BuildingEffectStage::cases() as $stage)
            $events->buildingEffect( $building, null, $stage );

        $this->clearTownCaches($town);
        $this->entity_manager->persist($building);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }
}
