<?php
/** @noinspection PhpRouteMissingInspection */

namespace App\Controller;

use App\Annotations\GateKeeperProfile;
use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\CitizenRole;
use App\Entity\ExpeditionRoute;
use App\Entity\ExternalApp;
use App\Entity\GazetteEntryTemplate;
use App\Entity\GazetteLogEntry;
use App\Entity\ItemPrototype;
use App\Entity\Town;
use App\Entity\User;
use App\Entity\Zone;
use App\Entity\ZoneTag;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\DeathHandler;
use App\Service\GameFactory;
use App\Service\GazetteService;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\LogTemplateHandler;
use App\Service\PictoHandler;
use App\Service\RandomGenerator;
use App\Service\TimeKeeperService;
use App\Service\TownHandler;
use App\Service\UserHandler;
use App\Service\ZoneHandler;
use App\Structures\TownConf;
use App\Structures\TownDefenseSummary;
use DateTime;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ExternalController
 * @package App\Controller
 * @GateKeeperProfile(allow_during_attack=true, record_user_activity=false)
 */
class ExternalController extends InventoryAwareController {
    /**
     * @var Request
     */
    private                          $request;
    private                          $SURLLobj;
    private array                    $filters         = [];
    private array                    $langue          = ['en', 'fr', 'de', 'es'];
    private array                    $fields          = [];
    private Town                     $town;
    private User                     $user;
    private int                      $xTown           = 0;
    private int                      $yTown           = 0;
    private int                      $mapIdUser       = 0;
    protected                        $game_factory;
    protected ZoneHandler            $zone_handler;
    protected                        $item_factory;
    protected DeathHandler           $death_handler;
    protected EntityManagerInterface $entity_manager;
    protected                        $available_langs = ['en', 'fr', 'de', 'es'];
    protected GazetteService         $gazette_service;

    /**
     * BeyondController constructor.
     * @param EntityManagerInterface $em
     * @param InventoryHandler $ih
     * @param CitizenHandler $ch
     * @param ActionHandler $ah
     * @param TimeKeeperService $tk
     * @param DeathHandler $dh
     * @param PictoHandler $ph
     * @param TranslatorInterface $translator
     * @param GameFactory $gf
     * @param RandomGenerator $rg
     * @param ItemFactory $if
     * @param LogTemplateHandler $lh
     * @param ConfMaster $conf
     * @param ZoneHandler $zh
     * @param UserHandler $uh
     * @param CrowService $armbrust
     * @param Packages $a
     * @param TownHandler $th
     */

    public function __construct(EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch,
                                ActionHandler $ah, TimeKeeperService $tk, DeathHandler $dh,
                                PictoHandler $ph, TranslatorInterface $translator, GameFactory $gf,
                                RandomGenerator $rg, ItemFactory $if, LogTemplateHandler $lh,
                                ConfMaster $conf, ZoneHandler $zh, UserHandler $uh,
                                CrowService $armbrust, Packages $a, TownHandler $th, GazetteService $gs) {
        parent::__construct($em, $ih, $ch, $ah, $dh, $ph, $translator, $lh, $tk, $rg, $conf, $zh, $uh, $armbrust, $th, $a);
        $this->game_factory = $gf;
        $this->item_factory = $if;
        $this->zone_handler = $zh;
        $this->entity_manager = $em;
        $this->gazette_service = $gs;
    }

    /**
     * @Route("/api/x/json/{type}", name="ext_json", methods={"GET", "POST"})
     * @param string $type
     * @return Response
     */
    public function api_json($type = ''): Response {

        $data = [];

        $APP_KEY = $this->getRequestParam('appkey');
        if ($APP_KEY === false) {
            $data = ["error" => "invalid_appkey"];
            $type = 'internalerror';
        }

        /** @var ExternalApp $app */
        $app = $this->entity_manager->getRepository(ExternalApp::class)->findOneBy(['secret' => $APP_KEY]);

        if (!$app) {
            $data = ["error" => "invalid_appkey"];
            $type = 'internalerror';
        }

        switch ($type) {
            case 'internalerror':
                if (empty($data)) {
                    $data = [
                        "error"             => "server_error",
                        "error_description" => "UnknownAction(default)"
                    ];
                }
                break;
            case '':
                $data = [
                    "error"             => "server_error",
                    "error_description" => "UnknownAction(default)"
                ];
                break;
            case 'status':
                $data = [
                    "attack"   => $this->time_keeper->isDuringAttack(),
                    "maintain" => is_file($this->getParameter('kernel.project_dir') . "/public/maintenance/.active")
                ];
                break;
            case 'items':
                $data = $this->getItemAPI();
                break;
            case 'debug':
                $data = $this->getDebugdata();
                break;
            case "user":
            case "me":
                $data = $this->getUserAPI($type);
                break;
            case "map":
                $data = $this->getMapAPI();
                break;
        }

        if (!empty($data)) {
            return $this->json($data);
        }

        return $this->json($data);
    }

    /**
     * @Route("/jx/disclaimer/{id}", name="disclaimer", condition="request.isXmlHttpRequest()")
     * @param int $id
     * @return Response
     */
    public function disclaimer(int $id): Response {
        /** @var ExternalApp $app */
        $app = $this->entity_manager->getRepository(ExternalApp::class)
                                    ->find($id);
        $user = $this->getUser();
        if (!$app || !$user || ($app->getTesting() && $app->getOwner() !== $user)) {
            return $this->redirect($this->generateUrl('initial_landing'));
        }

        $key = $user->getExternalId();

        return $this->render('ajax/public/disclaimer.html.twig', [
                                                                   'ex'  => $app,
                                                                   'key' => $key
                                                               ]
        );
    }

    /**
     * @Route("/jx/docs", name="docs", condition="request.isXmlHttpRequest()")
     * @return Response
     */
    public function documentation(): Response {
        return $this->render('ajax/public/apidocs.html.twig', []);
    }

    private function SURLL_parser(): array {
        $parsed = [];
        while (count($this->SURLLobj) > 0) {
            $surll_item = array_shift($this->SURLLobj);
            if ($surll_item === "(") {
                continue;
            } elseif ($surll_item === ")") {
                return $parsed;
            } elseif ($surll_item[0] === ".") {
                $last_surll_item = array_pop($parsed);
                if (is_string($last_surll_item)) {
                    $name = $last_surll_item;
                    $last_surll_item = [];
                    $last_surll_item[$name] = [];
                } else {
                    $name = array_keys($last_surll_item)[0];
                }
                $last_surll_item[$name][substr($surll_item, 1)] = $this->SURLL_parser();
                $parsed[] = $last_surll_item;
            } else {
                $parsed[] = $surll_item;
            }
        }
        return $parsed;
    }

    private function SURLL_preparser($surll_str): array {
        preg_match_all('/\.[a-z0-9\-]+|[a-z0-9\-]+|\(|\)/i', $surll_str, $surll_arr);
        $this->SURLLobj = $surll_arr[0];
        return $this->SURLL_parser();
    }

    private function getItemAPI(): array {
        $fields = $this->getRequestParam('fields');
        $filter = $this->getRequestParam('filters');
        $langue = $this->getRequestParam('languages');

        if ($fields != false) {
            $this->fields = $this->SURLL_preparser($fields);
        } else {
            $this->fields = ['img', 'name'];
        }
        if ($filter != false) {
            $this->filters = $this->SURLL_preparser($filter);
        }
        if ($langue != false) {
            $this->langue = $this->SURLL_preparser($langue);
        }

        return $this->getItemsData();
    }

    private function getMapAPI(): array {

        $retourUserKey = $this->getUserKey();
        if (!empty($retourUserKey)) {
            return $retourUserKey;
        }

        $map_id = intval($this->getRequestParam('mapId'));
        if ($map_id != false || $map_id > 0) {

            $this->filters = [$map_id];

            $fields = $this->getRequestParam('fields');
            $lang = $this->getRequestParam('languages');

            if ($fields != false && $map_id == $this->mapIdUser) {
                $this->fields = $this->SURLL_preparser($fields);
            } else {
                $this->fields = ['date', 'days', 'season', 'id', 'hei', 'wid', 'bonusPts', 'conspiracy', 'custom','city'];
            }
            if ($lang != false) {
                $this->langue = $this->SURLL_preparser($lang);
            } else {
                $lang = $this->getRequestLanguage($this->request, $this->user);
                if ($lang != 'all') {
                    $this->langue = [$lang];
                }
            }
            return $this->getMapData();
        } else {
            return ["error" => "invalid_mapid"];
        }
    }

    private function getUserAPI(string $type): array {

        $retourUserKey = $this->getUserKey();
        if (!empty($retourUserKey)) {
            return $retourUserKey;
        }

        if ($type === "me") {
            $filters = [$this->user->getId()];
        } else {
            $user_id = intval($this->getRequestParam('id'));
            if ($user_id != false || $user_id > 0) {
                $filters = [$user_id];
            } else {
                return ["error" => "invalid_userid"];
            }
        }
        $fields = $this->getRequestParam('fields');
        $lang = $this->getRequestParam('languages');

        if ($fields != false) {
            $fields_user = $this->SURLL_preparser($fields);
        } else {
            $fields_user = ['id', 'name', 'avatar', 'isGhost'];
            if($type === "me") {
                array_push($fields_user, 'twinoidID');
            }
        }
        if ($lang != false) {
            $this->langue = $this->SURLL_preparser($lang);
        } else {
            $lang = $this->getRequestLanguage($this->request, $this->user);
            if ($lang != 'all') {
                $this->langue = [$lang];
            }
        }

        return $this->getUserData($filters, $fields_user);
    }


    private function getArrayItem(Collection $collections, array $fields): array {
        $data = [];
        foreach ($collections as $collection) {
            if ($collection->getHidden()) {
                continue;
            }
            $str = "{$collection->getPrototype()->getId()}-" . intval($collection->getBroken());

            if (isset($data[$str])) {
                if (in_array("count", $fields)) {
                    $data[$str]['count'] += $collection->getCount();
                }
            } else {
                $item = $this->getItemData($collection->getPrototype(), $fields);
                if (in_array("count", $fields)) {
                    $item["count"] = $collection->getCount();
                }
                if (in_array("broken", $fields)) {
                    $item["broken"] = $collection->getBroken();
                }
                $data[$str] = $item;
            }
        }

        usort($data, fn($a, $b) => $a['id'] <=> $b['id'] ?? $a['broken'] <=> $b['broken']);

        return $data;

    }

    private function getBankData(array $fields = []): array {
        if (empty($fields)) {
            $fields = ['id', 'count', 'broken'];
        }

        /* For the all item in Bank */
        $data = $this->getArrayItem($this->town->getBank()->getItems(), $fields);

        return $data;
    }

    private function getBuildingData(Zone $zone, bool $zoneOfUser, array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['type', 'dig', 'name', 'desc'];
        }

        foreach ($fields as $field) {
            switch ($field) {
                case 'type':
                    $data[$field] = ($zone->getBuryCount() > 0) ? -1 : $zone->getPrototype()->getId();
                    break;
                case 'name':
                    $data[$field] = ($zone->getBuryCount() > 0) ? $this->getTranslate('Verschüttete Ruine', 'game') :
                        $this->getTranslate($zone->getPrototype()->getLabel(), 'game');
                    break;
                case 'desc':
                    $data[$field] = ($zone->getBuryCount() > 0) ?
                        $this->getTranslate('Die Zone ist vollständig mit verrottender Vegetation, Sand und allem möglichen Schrott bedeckt. Du bist dir sicher, dass es hier etwas zu finden gibt, aber zunächst musst du diesen gesamten Sektor aufräumen um ihn vernünftig durchsuchen zu können.',
                                            'game') : $this->getTranslate($zone->getPrototype()->getDescription(), 'game');
                    break;
                case 'dig':
                    if ($zone->getBuryCount() > 0) {
                        $data[$field] = $zone->getBuryCount();
                    }
                    break;
                case 'camped':
                    if ($zoneOfUser) {
                        $data[$field] = $zone->getBlueprint() == Zone::BlueprintFound;
                    }
                    break;
                case 'dried':
                    if ($zoneOfUser) {
                        $data[$field] = $zone->getRuinDigs() <= 0;
                    }
                    break;
            }
        }


        return $data;
    }

    private function getCadaversData(array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['id', 'survial', 'avatar', 'name'];
        }

        foreach ($this->town->getCitizens() as $citizen) {
            if (!$citizen->getAlive()) {
                $data[] = $this->getCadaversInformation($citizen, $fields);
            }
        }


        return $data;
    }

    private function getChantiersData(string $typeBuildings, array $fields = []): array {
        $data = [];
        $complete = true;

        if ($typeBuildings == 'chantiers') {
            $complete = false;
        }

        if (empty($fields)) {
            $fields = ['id', 'icon', 'name', 'desc', 'pa', 'breakable', 'def', 'temporary'];
        }

        foreach ($this->town->getBuildings() as $building) {
            if ($building->getComplete() === $complete) {
                $data_building = [];
                foreach ($fields as $field) {
                    if (is_array($field)) {
                        foreach ($field as $ProtoFieldName => $ProtoFieldValue) {
                            if ($ProtoFieldName == "resources") {
                                $data_building[$ProtoFieldName] = $this->getResources($building->getPrototype(), $ProtoFieldValue['fields'] ?? []);
                            }
                        }
                    } else {
                        switch ($field) {
                            case "id":
                                $data_building[$field] = $building->getPrototype()->getId();
                                break;
                            case "icon":
                                $data_building[$field] =
                                    $this->getIconPath($this->asset->getUrl("build/images/building/{$building->getPrototype()->getIcon()}.gif"));
                                break;
                            case "name":
                                $data_building[$field] = $this->getTranslate($building->getPrototype()->getLabel(), 'buildings');
                                break;
                            case "desc":
                                $data_building[$field] = $this->getTranslate($building->getPrototype()->getDescription(), 'buildings');
                                break;
                            case "pa":
                                $data_building[$field] = $building->getPrototype()->getAp();
                                break;
                            case "life":
                                $data_building[$field] = $building->getHp();
                                break;
                            case "maxLife":
                                $data_building[$field] = $building->getPrototype()->getHp();
                                break;
                            case "votes":
                                $data_building[$field] = $building->getBuildingVotes()->count();
                                break;
                            case "breakable":
                                $data_building[$field] = !$building->getPrototype()->getImpervious();
                                break;
                            case "def":
                                $data_building[$field] = $building->getDefense();
                                break;
                            case "hasUpgrade":
                                $data_building[$field] = !is_null($building->getPrototype()->getUpgradeTexts());
                                break;
                            case "rarity":
                                $data_building[$field] = $building->getPrototype()->getBlueprint();
                                break;
                            case "temporary":
                                $data_building[$field] = $building->getPrototype()->getTemp();
                                break;
                            case "parent":
                                $data_building[$field] =
                                    (!is_null($building->getPrototype()->getParent())) ? $building->getPrototype()->getParent()->getId() : 0;
                                break;
                            case "resources":
                                $data_building[$field] = $this->getResources($building->getPrototype());
                                break;
                            case "actions":
                                $data_building[$field] = $building->getPrototype()->getAp() - $building->getAp();
                                break;
                            case "hasLevels":
                                $data_building[$field] = $building->getLevel();
                                break;
                        }
                    }
                }
                $data[] = $data_building;
            }
        }

        return $data;
    }

    private function getCitizensData(array $fields = []): array {
        $data = [];
        if (empty($fields)) {
            $fields = ['id', 'name', 'isGhost', 'homeMessage', 'avatar', 'job', 'x', 'y'];
        }

        /* search if 'map' in $fiels */
        if (in_array('map', $fields)) {
            $keyMap = array_search("map", $fields);
            unset($fields[$keyMap]);
        }

        foreach ($this->town->getCitizens() as $citizen) {
            if ($citizen->getAlive()) {
                $data[] = $this->getUserData([$citizen->getUser()->getId()], $fields);
            }
        }
        return $data;
    }

    private function getCityData(array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['name', 'water', 'x', 'y', 'door', 'chaos', 'hard', 'devast'];
        }

        foreach ($fields as $field) {
            if (is_array($field)) {
                foreach ($field as $ProtoFieldName => $ProtoFieldValue) {
                    switch ($ProtoFieldName) {
                        case "chantiers":
                        case "buildings":
                            $data[$ProtoFieldName] = $this->getChantiersData($ProtoFieldName, $ProtoFieldValue['fields'] ?? []);
                            break;
                        case "news":
                            $data[$ProtoFieldName] = $this->getNewsData($ProtoFieldValue['fields'] ?? []);
                            break;
                        case "defense":
                            $data[$ProtoFieldName] = $this->getDefenseData($ProtoFieldValue['fields'] ?? []);
                            break;
                        case "upgrades":
                            $data[$ProtoFieldName] = $this->getUpgradesData($ProtoFieldValue['fields'] ?? []);
                            break;
                        case "estimations":
                        case "estimationsNext":
                            $data[$ProtoFieldName] = $this->getEstimationData($ProtoFieldName, $ProtoFieldValue['fields'] ?? []);
                            break;
                        case "bank":
                            $data[$ProtoFieldName] = $this->getBankData($ProtoFieldValue['fields'] ?? []);
                            break;
                    }
                }
            } else {
                switch ($field) {
                    case "name":
                        $data[$field] = $this->town->getName();
                        break;
                    case "water":
                        $data[$field] = $this->town->getWell();
                        break;
                    case "x":
                    case "y":
                        $method = $field . 'Town';
                        $data[$field] = $this->$method;
                        break;
                    case "door":
                        $data[$field] = $this->town->getDoor();
                        break;
                    case "chaos":
                        $data[$field] = $this->town->getChaos();
                        break;
                    case "hard":
                        $data[$field] = $this->town->getType()->getName() === 'panda';
                        break;
                    case "devast":
                        $data[$field] = $this->town->getDevastated();
                        break;
                    case "chantiers":
                    case "buildings":
                        $data[$field] = $this->getChantiersData($field);
                        break;
                    case "news":
                        $data[$field] = $this->getNewsData();
                        break;
                    case "defense":
                        $data[$field] = $this->getDefenseData();
                        break;
                    case "upgrades":
                        $data[$field] = $this->getUpgradesData();
                        break;
                    case "estimations":
                    case "estimationsNext":
                        $data[$field] = $this->getEstimationData($field);
                        break;
                    case "bank":
                        $data[$field] = $this->getBankData();
                        break;
                }
            }
        }

        return $data;
    }

    private function getDebugdata(): array {
        $town_id = intval($this->getRequestParam('tid'));
        if ($town_id != false || $town_id > 0) {
            $town = $this->entity_manager->getRepository(Town::class)->findOneBy(['id' => $town_id]);
            if ($town) {
                $towns = [$town];
            } else {
                $towns = $this->entity_manager->getRepository(Town::class)->findOpenTown();
            }
        } else {
            $towns = $this->entity_manager->getRepository(Town::class)->findOpenTown();
        }
        $data = [];
        /** @var Town $town */
        foreach ($towns as $town) {
            $x_min = $x_max = $y_min = $y_max = 0;
            /** @var Zone $zone */
            foreach ($town->getZones() as $zone) {
                $x_min = min($zone->getX(), $x_min);
                $x_max = max($zone->getX(), $x_max);
                $y_min = min($zone->getY(), $y_min);
                $y_max = max($zone->getY(), $y_max);
            }
            $town_data = [
                'id'       => $town->getId(),
                'name'     => $town->getName(),
                'day'      => $town->getDay(),
                'height'   => abs($y_min) + abs($y_max) + 1,
                'width'    => abs($x_min) + abs($x_max) + 1,
                'type'     => ['', 'RNE', 'RE', 'PANDE'][$town->getType()->getId()],
                'language' => $town->getLanguage(),
                'zone'     => []
            ];
            /** @var Zone $zone */
            foreach ($town->getZones() as $zone) {
                $zone_data = [
                    'x'                    => $zone->getX() - $x_min,
                    'y'                    => $y_max - $zone->getY(),
                    'km'                   => $this->zone_handler->getZoneKm($zone),
                    'remaining_excavation' => $zone->getDigs(),
                    'zombies'              => $zone->getZombies(),
                    'is_town'              => $zone->getDistance() < 1,
                    'items'                => [],
                    'building'             => false
                ];
                $item_buffer = [];
                foreach ($zone->getFloor()
                              ->getItems() as $item) {
                    $item_uid = implode('_', [
                                               $item->getPrototype()->getIcon(),
                                               $item->getBroken()
                                           ]
                    );
                    if (!isset($item_buffer[$item_uid])) {
                        $item_buffer[$item_uid] = [
                            'uid'    => $item->getPrototype()->getIcon(),
                            'broken' => $item->getBroken(),
                            'count'  => $item->getCount()
                        ];
                    } else {
                        $item_buffer[$item_uid]['count'] += $item->getCount();
                    }
                }
                foreach ($item_buffer as $item) {
                    $zone_data['items'][] = $item;
                }


                if ($zone->getPrototype()) {
                    $zone_data['building'] = [
                        'name'                 => $this->translator->trans($zone->getPrototype()->getLabel(), [], "game", $town->getLanguage()),
                        'type'                 => $zone->getPrototype()->getId(),
                        'sandpile'             => $zone->getBuryCount(),
                        'remaining_blueprint'  => $zone->getBlueprint(),
                        'remaining_excavation' => $zone->getRuinDigs()
                    ];
                }
                $town_data['zone'][] = $zone_data;
            }
            $data[] = $town_data;
        }
        return $data;
    }

    private function getDefenseData(array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields =
                ["total", "base", "buildings", "upgrades", "items", "itemsMul", "citizenHomes", "citizenGuardians", "watchmen", "souls", "temp",
                 "cadavers", "guardiansInfos", "bonus"
                ];
        }

        $def = new TownDefenseSummary();
        $this->town_handler->calculate_town_def($this->town, $def);

        $item_factor_def = 1;

        $building_Defensive_Focus = $this->town_handler->getBuilding($this->town, 'item_meca_parts_#00');
        if ($building_Defensive_Focus) {
            $item_factor_def += (1 + $building_Defensive_Focus->getLevel()) * 0.5;
        }

        $itemQty =
            $this->inventory_handler->countSpecificItems($this->town->getBank(), $this->inventory_handler->resolveItemProperties('defence'), false,
                                                         false, false);

        $guardian_bonus = $this->town_handler->getBuilding($this->town, 'small_watchmen_#00', true) ? 15 : 5;


        foreach ($fields as $field) {
            if (is_array($field)) {
                if (key($field) == "guardiansInfos") {
                    $data['guardiansInfos']['gardians'] = $def->guardian_defense / $guardian_bonus;
                    $data['guardiansInfos']['def'] = $guardian_bonus;
                }
            } else {
                switch ($field) {
                    case "total":
                        $data[$field] = $def->sum();
                        break;
                    case "base":
                        $data[$field] = $def->base_defense;
                        break;
                    case "buildings":
                        $data[$field] = $def->building_def_base;
                        break;
                    case "upgrades":
                        $data[$field] = $def->building_def_vote;
                        break;
                    case "items":
                        $data[$field] = $itemQty;
                        break;
                    case "itemsMul":
                        $data[$field] = $item_factor_def;
                        break;
                    case "citizenHomes":
                        $data[$field] = $def->house_defense;
                        break;
                    case "citizenGuardians":
                        $data[$field] = $def->guardian_defense;
                        break;
                    case "watchmen":
                        $data[$field] = $def->nightwatch_defense;
                        break;
                    case "souls":
                        if ($def->soul_defense > 0) {
                            $data[$field] = $def->soul_defense;
                        }
                        break;
                    case "temp":
                        $data[$field] = $def->temp_defense;
                        break;
                    case "cadavers":
                        if ($def->cemetery > 0) {
                            $data[$field] = $def->cemetery;
                        }
                        break;
                    case "bonus":
                        if ($def->overall_scale > 1) {
                            $data[$field] = 1 - $def->overall_scale;
                        }
                        break;
                    case "guardiansInfos":
                        $data['guardiansInfos']['gardians'] = $def->guardian_defense / $guardian_bonus;
                        $data['guardiansInfos']['def'] = $guardian_bonus;
                        break;

                }
            }
        }


        return $data;
    }

    private function getDetailsData(Zone $zone, bool $zoneOfUser, bool $buildUpgradedMap, array $fields = []): array {
        $data = [];
        if (empty($fields)) {
            $fields = ['z', 'dried'];
        }

        foreach ($fields as $field) {
            switch ($field) {
                case 'z':
                    if ($zoneOfUser || ($buildUpgradedMap && $zone->getDiscoveryStatus() == Zone::DiscoveryStateCurrent)) {
                        $data[$field] = $zone->getZombies();
                    }
                    break;
                case 'h':
                    if (!$this->town->getChaos()) {
                        $cp = 0;
                        foreach ($zone->getCitizens() as $citizen) {
                            $cp += $this->citizen_handler->getCP($citizen);
                        }
                        $data[$field] = $cp;
                    }
                    break;
                case "dried":
                    if ($zoneOfUser) {
                        $data[$field] = $zone->getDigs() <= 0;
                    }
            }
        }

        return $data;


    }

    private function getEstimationData(string $typeEstimation, array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['days', 'min', 'max', 'maxed'];
        }

        $wtt = $this->conf->getTownConfiguration($this->town)->get(TownConf::CONF_MODIFIER_WT_THRESHOLD, 33);
        $estimTown = $this->town_handler->get_zombie_estimation($this->town);


        if (!isset($estimTown) || empty($estimTown)) {
            return $data;
        }

        $estim = [];

        switch ($typeEstimation) {
            case "estimations":
                $estim = $estimTown[0];
                if ($wtt / 100 >= $estim->getEstimation()) {
                    return $data;
                }
                break;
            case "estimationsNext":
                if (!isset($estimTown[1])) {
                    return $data;
                } else {
                    $estim = $estimTown[1];
                }
                break;
        }

        foreach ($fields as $field) {
            switch ($field) {
                case 'days':
                    $data[$field] = $this->town->getDay();
                    break;
                case 'min':
                case 'max':
                    $method = 'get' . ucfirst($field);
                    $data[$field] = $estim->$method();
                    break;
                case 'maxed':
                    $data[$field] = $estim->getEstimation() >= 1;
                    break;

            }
        }


        return $data;
    }

    private function getExpeditionsData(array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['name', 'author', 'points'];
        }

        $expeditions = $this->entity_manager->getRepository(ExpeditionRoute::class)->findByTown($this->town);
        /**
         * @var ExpeditionRoute $expedition
         */
        foreach ($expeditions as $expedition) {
            $data_exp = [];
            foreach ($fields as $field) {
                if (is_array($field)) {
                    switch (key($field)) {
                        case "author":
                            $data_exp['author'] = $this->getAuthorInformation($expedition);
                            break;
                        case "points":
                            $data_exp['points'] = $this->getPointsExpedition($expedition);
                            break;
                    }
                } else {
                    switch ($field) {
                        case "name":
                            $data_exp[$field] = $expedition->getLabel();
                            break;
                        case "author":
                            $data_exp[$field] = $this->getAuthorInformation($expedition);
                            break;
                        case "length":
                            $data_exp[$field] = $expedition->getLength();
                            break;
                        case "points":
                            $data_exp[$field] = $this->getPointsExpedition($expedition);
                            break;
                    }
                }
            }
            $data[] = $data_exp;
        }


        return $data;
    }

    private function getItemData(ItemPrototype $item, array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['id', 'uid', 'img', 'name'];
        }

        foreach ($fields as $field) {
            switch ($field) {
                case 'id':
                    $data[$field] = $item->getId();
                    break;
                case 'img':
                    $data[$field] = $this->getIconPath($this->asset->getUrl("build/images/item/item_{$item->getIcon()}.gif"));
                    break;
                case 'uid':
                    $data[$field] = $item->getIcon();
                    break;
                case 'heavy':
                    $data[$field] = $item->getHeavy();
                    break;
                case 'deco':
                    $data[$field] = $item->getDeco();
                    break;
                case 'guard':
                    $data[$field] = $item->getWatchpoint();
                    break;
                case 'name':
                    $data[$field] = $this->getTranslate($item->getLabel(), 'items');
                    break;
                case 'desc':
                    $data[$field] = $this->getTranslate($item->getDescription(), 'items');
                    break;
                case 'cat':
                    $data[$field] = $this->getTranslate($item->getCategory()->getLabel(), 'items');
                    break;
            }
        }

        return $data;
    }

    private function getItemsData(): array {
        $data = [];
        if (!empty($this->filters)) {
            $filters = [];
            foreach ($this->filters as $key => $val) {
                if (is_string($val)) {
                    $filters[] = $val;
                }
            }
            $items = $this->entity_manager->getRepository(ItemPrototype::class)->findBy(['icon' => $filters]);
        } else {
            $items = $this->entity_manager->getRepository(ItemPrototype::class)->findAll();
        }
        /** @var ItemPrototype $ItemProto */
        foreach ($items as $ItemProto) {
            $icon = $ItemProto->getIcon();

            $data[$icon] = $this->getItemData($ItemProto, $this->fields);
        }
        return $data;
    }

    private function getJobData(Citizen $citizen, array $fields = []): array {

        if (empty($fields)) {
            $fields = ['uid', 'name'];
        }

        $data = [];

        $citizenProfession = $citizen->getProfession();

        foreach ($fields as $field) {
            switch ($field) {
                case 'id':
                    $data[$field] = $citizenProfession->getId();
                    break;
                case 'uid':
                    $data[$field] = $citizenProfession->getIcon();
                    break;
                case 'name':
                    $data[$field] = $this->getTranslate($citizenProfession->getLabel(), 'game');
                    break;
                case 'desc':
                    $data[$field] = $this->getTranslate($citizenProfession->getDescription(), 'game');
                    break;
            }
        }
        return $data;
    }

    private function getListCityUpgradesData(BuildingPrototype $buildingPrototype, int $level, array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['name', 'level', 'buildingId'];
        }

        foreach ($fields as $field) {
            switch ($field) {
                case "name":
                    $data[$field] = $this->getTranslate($buildingPrototype->getLabel(), 'buildings');
                    break;
                case "level":
                    $data[$field] = $level;
                    break;
                case "update":
                    $data[$field] = $this->getTranslate($buildingPrototype->getUpgradeTexts()[$level - 1], 'buildings');
                    break;
                case "buildingId":
                    $data[$field] = $buildingPrototype->getId();
                    break;
            }
        }

        return $data;
    }

    private function getMapData(array $fields = [], array $filters = []): array {

        if (empty($fields)) {
            $fields = $this->fields;
        }

        if (empty($filters)) {
            $filters = $this->filters;
        }

        if (!isset($this->town)) {
            $return = $this->getTownInformation($filters[0]);
            if (!empty($return)) {
                return $return;
            }
        }

        $data = [];
        foreach ($fields as $field) {
            if (is_array($field)) {
                foreach ($field as $ProtoFieldName => $ProtoFieldValue) {
                    switch ($ProtoFieldName) {
                        case "cadavers":
                            $data[$ProtoFieldName] = $this->getCadaversData($ProtoFieldValue['fields'] ?? []);
                            break;
                        case "citizens":
                            $data[$ProtoFieldName] = $this->getCitizensData($ProtoFieldValue['fields'] ?? []);
                            break;
                        case "city":
                            $data[$ProtoFieldName] = $this->getCityData($ProtoFieldValue['fields'] ?? []);
                            break;
                        case "expeditions":
                            $data[$ProtoFieldName] = $this->getExpeditionsData($ProtoFieldValue['fields'] ?? []);
                            break;
                        case "zones":
                            $data[$ProtoFieldName] = $this->getZonesData($ProtoFieldValue['fields'] ?? []);
                            break;
                    }
                }
            } else {
                $this->town->getMapSize($map_x,$map_y);
                switch ($field) {
                    case "id":
                        $data[$field] = $this->town->getId();
                        break;
                    case "date":
                        $now = new DateTime();
                        $data[$field] = $now->format('Y-m-d H:i:s');
                        break;
                    case "wid":
                        $data[$field] = $map_x;
                        break;
                    case "hei":
                        $data[$field] = $map_y;
                        break;
                    case "conspiracy":
                        $data[$field] = $this->town->getInsurrectionProgress() >= 100;
                        break;
                    case "days":
                        $data[$field] = $this->town->getDay();
                        break;
                    case "season":
                        $data[$field] = (!is_null($this->town->getSeason())) ? ($this->town->getSeason()->getNumber() ?: $this->town->getSeason()->getSubNumber()) : 0;
                        break;
                    case "phase":
                        if ($this->town->getSeason() === null)
                            $data[$field] = 'alpha';
                        elseif ($this->town->getSeason()->getNumber() === 0 && $this->town->getSeason()->getSubNumber() <= 14)
                            $data[$field] = 'import';
                        elseif ($this->town->getSeason()->getNumber() === 0 && $this->town->getSeason()->getSubNumber() >= 14)
                            $data[$field] = 'beta';
                        else
                            $data[$field] = 'native';
                        break;
                    case "source":
                        if ($this->town->getSeason()->getNumber() === 0 && $this->town->getSeason()->getSubNumber() <= 14)
                            switch ($this->town->getLanguage()) {
                                case 'de': $data[$field] = 'www.dieverdammten.de'; break;
                                case 'en': $data[$field] = 'www.die2nite.com'; break;
                                case 'es': $data[$field] = 'www.zombinoia.com'; break;
                                case 'fr': $data[$field] = 'www.hordes.fr'; break;
                                default: $data[$field] = ''; break;
                            }
                        else $data[$field] = 'www.myhordes.eu'; break;
                    case "bonusPts":
                        $data[$field] = 0;
                        break;
                    case "guide":
                        $latest_guide = $this->entity_manager->getRepository(Citizen::class)
                                                             ->findLastOneByRoleAndTown($this->entity_manager->getRepository(CitizenRole::class)
                                                                                                             ->findOneBy(['name' => 'guide']),
                                                                                        $this->town);
                        if ($latest_guide && $latest_guide->getAlive()) {
                            $data[$field] = $latest_guide->getUser()->getId();
                        }
                        break;
                    case "shaman":
                        $latest_shaman = $this->entity_manager->getRepository(Citizen::class)
                                                             ->findLastOneByRoleAndTown($this->entity_manager->getRepository(CitizenRole::class)
                                                                                                             ->findOneBy(['name' => 'shaman']),
                                                                                        $this->town);
                        if ($latest_shaman && $latest_shaman->getAlive()) {
                            $data[$field] = $latest_shaman->getUser()->getId();
                        }
                        break;
                    case "custom":
                        $data[$field] = $this->town->getType()->getName() === 'custom';
                        break;
                    case "city":
                        $data[$field] = $this->getCityData();
                        break;
                    case "citizens":
                        $data[$field] = $this->getCitizensData();
                        break;
                    case "expeditions":
                        $data[$field] = $this->getExpeditionsData();
                        break;

                }
            }
        }
        return $data;
    }

    private function getNewsData(array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['z', 'def', 'content'];
        }

        if ($this->town->getDay() == 1) {
            return $data;
        } else {
            $gazette = $this->gazette_service->renderGazette($this->town, null, false, count($this->langue) == 1 ? $this->langue[0] : null);
            if (!is_null($gazette)) {
                foreach ($fields as $field) {
                    switch ($field) {
                        case "z":
                            $data[$field] = $gazette['attack'];
                            break;
                        case "def":
                            $data[$field] = $gazette['defense'];
                            break;
                        case "content":
                            /**
                             * @var GazetteLogEntry $entry
                             */
                            if(count($this->langue) == 1) {
                                $data[$field] = $gazette['text'];
                            } else {
                                $data[$field] = [];
                                foreach ($this->langue as $lang) {
                                    $gazette = $this->gazette_service->renderGazette($this->town, null, false, $lang);
                                    $data[$field][$lang] = $gazette['text'];
                                }
                            }
                            break;
                        case "regenDir":
                            /* if Searchtower build small_gather_#02 */
                            $buildSearchtower = ($this->town_handler->getBuilding($this->town, 'small_gather_#02', true)) ? true : false;
                            if($buildSearchtower){
                                $regenDir = 'invalid direction';
                                switch ($gazette['windDirection']){
                                    case Zone::DirectionNorthWest:
                                        $regenDir = $this->getTranslate('Nordwesten','game');
                                        break;
                                    case Zone::DirectionNorth:
                                        $regenDir = $this->getTranslate('Norden','game');
                                        break;
                                    case Zone::DirectionNorthEast:
                                        $regenDir = $this->getTranslate('Nordosten','game');
                                        break;
                                    case Zone::DirectionWest:
                                        $regenDir = $this->getTranslate('Westen','game');
                                        break;
                                    case Zone::DirectionEast:
                                        $regenDir = $this->getTranslate('Osten','game');
                                        break;
                                    case Zone::DirectionSouthWest:
                                        $regenDir = $this->getTranslate('Südwesten','game');
                                        break;
                                    case Zone::DirectionSouth:
                                        $regenDir = $this->getTranslate('Süden','game');
                                        break;
                                    case Zone::DirectionSouthEast:
                                        $regenDir = $this->getTranslate('Südosten','game');
                                        break;
                                }

                                $data[$field] = $regenDir;
                            }
                            break;
                        case "water":
                            $data[$field] = $gazette['waterlost'];
                            break;
                    }
                }

            }
        }


        return $data;
    }

    private function getPlayedMapData(User $user, array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['id', 'mapId', 'survival', 'name', 'mapName', 'season', 'score', 'origin'];
        }

        $mainAccount = null;
        foreach ($user->getTwinoidImports() as $twinoidImport) {
            if ($twinoidImport->getMain()) {
                switch ($twinoidImport->getScope()) {
                    case "www.hordes.fr":
                        $mainAccount = 'fr';
                        break;
                    case "www.die2nite.com":
                        $mainAccount = 'en';
                        break;
                    case "www.dieverdammten.de":
                        $mainAccount = 'de';
                        break;
                    case "www.zombinoia.com":
                        $mainAccount = 'es';
                        break;
                }
            }
        }

        foreach ($this->user->getPastLifes() as $pastLife) {
            if ($pastLife->getCitizen() && $pastLife->getCitizen()->getAlive()) {
                continue;
            }

            // This does not work; getCadaversInformation requires a Citizen, not a CitizenRankingProxy
            // Commenting it out until it is fixed to prevent crashes
            /*if($pastLife->getCitizen() != null){
                $data_town = $this->getCadaversInformation($pastLife->getUser()->getCitizens(),$fields);
                if(in_array('origin',$fields)){
                    $codeOrigin = '';
                    if($pastLife->getTown()->getImported()){
                        $codeOrigin = $mainAccount . "-" . ($pastLife->getTown()->getSeason()) ? ($pastLife->getTown()->getSeason()->getNumber() === 0) ? $pastLife->getTown()->getSeason()->getSubNumber() : $pastLife->getTown()->getSeason()->getNumber() : 0;
                    }
                    $data_town['origin'] = $codeOrigin;
                }

                $data[] = $data_town;
            }*/

        }

        return $data;
    }

    private function getResources(BuildingPrototype $buildingPrototype, array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['amount', 'rsc'];
        }

        if (!is_null($buildingPrototype->getResources())) {
            foreach ($buildingPrototype->getResources()->getEntries() as $resource) {
                $data_resources = [];
                foreach ($fields as $field) {
                    if (is_array($field)) {
                        foreach ($field as $ProtoFieldName => $ProtoFieldValue) {
                            if ($ProtoFieldName == 'rsc') {
                                $data_resources[$ProtoFieldName] = $this->getItemData($resource->getPrototype(), $ProtoFieldValue['fields'] ?? []);
                            }
                        }
                    } else {
                        switch ($field) {
                            case 'amount':
                                $data_resources[$field] = $resource->getChance();
                                break;
                            case 'rsc':
                                $data_resources[$field] = $this->getItemData($resource->getPrototype());
                        }
                    }
                }

                $data[] = $data_resources;
            }
        }


        return $data;
    }

    private function getUpgradesData(array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['total', 'list'];
        }

        foreach ($this->town->getBuildings() as $building) {
            if ($building->getComplete() && $building->getPrototype()->getMaxLevel() > 0 && $building->getLevel() > 0) {
                foreach ($fields as $field) {
                    if (is_array($field)) {
                        foreach ($field as $ProtoFieldName => $ProtoFieldValue) {
                            if ($ProtoFieldName == "list") {
                                $data[$ProtoFieldName][] =
                                    $this->getListCityUpgradesData($building->getPrototype(), $building->getLevel(), $ProtoFieldValue['fields'] ?? []);
                            }
                        }
                    } else {
                        switch ($field) {
                            case "total":
                                $data[$field] = $data[$field] ?? 0 + $building->getLevel();
                                break;
                            case "list":
                                $data[$field][] = $this->getListCityUpgradesData($building->getPrototype(), $building->getLevel());
                        }
                    }
                }

            }

        }


        return $data;
    }

    private function getUserData(array $filters, array $fields = []): array {

        if (empty($fields)) {
            $fields = ['id', 'name', 'isGhost', 'avatar'];
        }

        /** @var User $user */
        $user = $this->entity_manager->getRepository(User::class)->findOneBy(['id' => $filters[0]]);
        if (!$user) {
            return ["error" => "UnknownUser"];
        }
        $current_citizen = $user->getActiveCitizen();
        $user_data = [];

        foreach ($fields as $field) {
            switch ($field) {
                case "id":
                    $user_data[$field] = $user->getId();
                    break;
                case "twinoidID":
                    $user_data[$field] = $user->getTwinoidID();
                    break;
                case "name":
                    $user_data[$field] = $user->getName();
                    break;
                case "avatar":
                    $has_avatar = $user->getAvatar();
                    if ($has_avatar) {
                        $user_data[$field] = $this->generateUrl('app_web_avatar', ['uid' => $user->getId(), 'name' => $has_avatar->getFilename(),
                                                                                   'ext' => $has_avatar->getFormat()
                        ], UrlGenerator::ABSOLUTE_URL);
                    } else {
                        $user_data[$field] = false;
                    }
                    break;
                case "isGhost":
                    $user_data[$field] = ($current_citizen === null);
                    break;
                case "playedMaps":
                    $user_data[$field] = $this->getPlayedMapData($user);
                    break;
            }
            if ($current_citizen) {

                if (!isset($this->town) && $this->user->getId() == $user->getId()) {
                    $return = $this->getTownInformation($current_citizen->getTown()->getId());
                    if (!empty($return)) {
                        return $return;
                    }
                }

                switch ($field) {
                    case "homeMessage":
                        $user_data[$field] = $current_citizen->getHome()->getDescription();
                        break;
                    case "hero":
                        $user_data[$field] = $current_citizen->getProfession()->getHeroic();
                        break;
                    case "dead":
                        $user_data[$field] = !$current_citizen->getAlive();
                        break;
                    case "out":
                        $user_data[$field] = $current_citizen->getZone() ? true : false;
                        break;
                    case "ban":
                        $user_data[$field] = $current_citizen->getBanished();
                        break;
                    case "baseDef":
                        $user_data[$field] = $current_citizen->getHome()->getPrototype()->getDefense();
                        break;
                    case "x":
                    case "y":
                        $zone = $current_citizen->getTown()->getChaos() ? null : $current_citizen->getZone();
                        $method = 'get' . ucfirst($field);
                        if ($field == "x") {
                            $offset = $this->xTown;
                            $sens = 1;
                        } else {
                            $offset = $this->yTown;
                            $sens = -1;
                        }
                        $user_data[$field] = $zone ? $offset + $zone->$method() * $sens : $offset;
                        break;
                    case "mapId":
                        $user_data[$field] = $current_citizen->getTown()->getId();
                        break;
                    case "map":
                        $user_data[$field] = $this->getMapData(['date', 'days', 'season', 'id', 'hei', 'wid', 'bonusPts', 'conspiracy', 'custom'],
                                                               [$current_citizen->getTown()->getId()]);
                        break;
                    case "job":
                        $user_data[$field] = $this->getJobData($current_citizen);
                        break;
                }


            }

            if (is_array($field)) {
                foreach ($field as $fieldName => $fieldValues) {
                    if ($current_citizen) {
                        switch ($fieldName) {
                            case "map":
                                $fields_map = ['date', 'days', 'season', 'id', 'hei', 'wid', 'bonusPts', 'conspiracy', 'custom'];
                                if ($user->getId() == $this->user->getId() && !empty($fieldValues['fields'])) {
                                    $fields_map = $fieldValues['fields'];
                                }
                                $user_data[$fieldName] = $this->getMapData($fields_map, [$current_citizen->getTown()->getId()]);
                                break;
                            case "job":
                                $user_data[$fieldName] =
                                    $this->getJobData($current_citizen, (!empty($fieldValues['fields'])) ? $fieldValues['fields'] : []);
                                break;
                        }
                    }
                    if ($fieldName === "playedMaps") {
                        $user_data[$fieldName] = $this->getPlayedMapData($user, $fieldValues['fields']);;
                    }

                }

            }
        }

        return $user_data;
    }

    private function getZonesData(array $fields = []): array {
        $data = [];

        if (empty($fields)) {
            $fields = ['x', 'y', 'nvt', 'tag', 'danger'];
        }

        if (!in_array("x", $fields)) {
            array_push($fields, "x");
        }
        if (!in_array("y", $fields)) {
            array_push($fields, "y");
        }

        /* recup current user active */
        $current_user = $this->user->getActiveCitizen();
        if ($current_user) {
            $zoneUser = $this->town->getChaos() ? null : $current_user->getZone();
        } else {
            $zoneUser = null;
        }

        $buildUpgradedMap = ($this->town_handler->getBuilding($this->town, 'item_electro_#00', true)) ? true : false;


        foreach ($this->town->getZones() as $zone) {
            if ($zone->getDiscoveryStatus() == Zone::DiscoveryStateNone) {
                continue;
            }
            $data_zone = [];
            $danger = 0;
            if ($zone->getZombies() > 0 && $zone->getZombies() <= 2) {
                $danger = 1;
            } elseif ($zone->getZombies() > 2 && $zone->getZombies() <= 5) {
                $danger = 2;
            } elseif ($zone->getZombies() > 5) {
                $danger = 3;
            }

            $zoneOfUser = false;

            if (!is_null($zoneUser) && $zone->getX() == $zoneUser->getX() && $zone->getY() == $zoneUser->getY()) {
                $zoneOfUser = true;
            }

            foreach ($fields as $field) {
                if (is_array($field)) {
                    foreach ($field as $fieldName => $fieldValues)
                        switch ($fieldName) {
                            case "details":
                                $data_zone[$fieldName] = $this->getDetailsData($zone, $zoneOfUser, $buildUpgradedMap, $fieldValues['fields']);
                                break;
                            case "items":
                                if ($zoneOfUser && !$this->town->getChaos()) {
                                    $data_zone[$fieldName] = $this->getArrayItem($zone->getFloor()->getItems(), $fieldValues['fields']);
                                }
                                break;
                            case "building":
                                if ($zone->getPrototype() != null) {
                                    $data_zone[$fieldName] = $this->getBuildingData($zone, $zoneOfUser, $fieldValues['fields']);
                                }
                                break;

                        }
                } else {
                    switch ($field) {
                        case "x":
                            $data_zone[$field] = $this->xTown + $zone->getX();
                            break;
                        case "y":
                            $data_zone[$field] = $this->yTown - $zone->getY();
                            break;
                        case "nvt":
                            $data_zone[$field] = intval($zone->getDiscoveryStatus() != Zone::DiscoveryStateCurrent);
                            break;
                        case "tag":
                            if ($zone->getTag() !== null && $zone->getTag()->getRef() !== ZoneTag::TagNone) {
                                $data_zone[$field] = $zone->getTag()->getRef();
                            }
                            break;
                        case "danger":
                            if ($zone->getDiscoveryStatus() == Zone::DiscoveryStateCurrent) {
                                $data_zone[$field] = $danger;
                            }
                            break;
                        case "details":
                            $data_zone[$field] = $this->getDetailsData($zone, $zoneOfUser, $buildUpgradedMap);
                            break;
                        case "items":
                            if ($zoneOfUser && !$this->town->getChaos()) {
                                $data_zone[$field] = $this->getArrayItem($zone->getFloor()->getItems(), ['id', 'count', 'broken']);
                            }
                            break;
                        case "building":
                            if ($zone->getPrototype() != null) {
                                $data_zone[$field] = $this->getBuildingData($zone, $zoneOfUser);
                            }
                            break;
                    }
                }
            }

            $data[] = $data_zone;
        }

        usort($data, function ($a, $b) {
            $retour = 0;
            $retour += ($a['y'] == $b['y']) ? 0 : (($a['y'] < $b['y']) ? -2 : 2);
            $retour += ($a['x'] == $b['x']) ? 0 : (($a['x'] < $b['x']) ? -1 : 1);
            return $retour;
        });


        return $data;
    }


    private function getAuthorInformation(ExpeditionRoute $expedition): array {
        $data = [];
        $data['id'] = $expedition->getOwner()->getUser()->getId();
        $data['name'] = $expedition->getOwner()->getName();

        $has_avatar = $expedition->getOwner()->getUser()->getAvatar();
        if ($has_avatar) {
            $data['avatar'] = $this->generateUrl('app_web_avatar', ['uid'  => $expedition->getOwner()->getUser()->getId(),
                                                                    'name' => $has_avatar->getFilename(),
                                                                    'ext'  => $has_avatar->getFormat()
            ], UrlGenerator::ABSOLUTE_URL);
        } else {
            $data['avatar'] = false;
        }

        return $data;
    }

    private function getCadaversInformation(Citizen $citizen, array $fields): array {
        $data = [];

        if ($citizen->getDisposed() != null) {
            $type = "unknown";
            switch ($citizen->getDisposed()) {
                case Citizen::Thrown:
                    $type = 'garbage';
                    break;
                case Citizen::Watered:
                    $type = 'water';
                    break;
                case Citizen::Cooked:
                    $type = "cook";
                    break;
                case Citizen::Ghoul:
                    $type = "ghoul";
                    break;
            }
        }

        foreach ($fields as $field) {
            if (is_array($field)) {
                if (key($field) == "cleanup" && $citizen->getDisposed() != null) {
                    $data['cleanup']['user'] =
                        $citizen->getDisposedBy()->count() > 0 ? $citizen->getDisposedBy()[0]->getName() : '';
                    $data['cleanup']['type'] = $type;
                }

            } else {
                switch ($field) {
                    case "id":
                        $data[$field] = $citizen->getUser()->getId();
                        break;
                    case "mapId":
                        $data[$field] = $citizen->getTown()->getId();
                        break;
                    case "survival":
                        $data[$field] = $citizen->getSurvivedDays();
                        break;
                    case "day":
                        $data[$field] = $citizen->getTown()->getDay();
                        break;
                    case "avatar":
                        $has_avatar = $citizen->getUser()->getAvatar();
                        if ($has_avatar) {
                            $data[$field] = $this->generateUrl('app_web_avatar', ['uid'  => $citizen->getUser()->getId(),
                                                                                  'name' => $has_avatar->getFilename(),
                                                                                  'ext'  => $has_avatar->getFormat()
                            ], UrlGenerator::ABSOLUTE_URL);
                        } else {
                            $data[$field] = false;
                        }
                        break;
                    case "name":
                        $data[$field] = $citizen->getName();
                        break;
                    case "mapName":
                        $data[$field] = $citizen->getTown()->getName();
                        break;
                    case "season":
                        $data[$field] = ($citizen->getTown()->getSeason()) ?
                            ($citizen->getTown()->getSeason()->getNumber() === 0) ? $citizen->getTown()->getSeason()->getSubNumber() :
                                $citizen->getTown()->getSeason()->getNumber() : 0;
                        break;
                    case "dtype":
                        $data[$field] = $citizen->getCauseOfDeath()->getRef();
                        break;
                    case "v1":
                        $data[$field] = 0;
                        break;
                    case "score":
                        /**
                         * @var CitizenRankingProxy $pastLifeThisTown
                         */
                        $pastLifeThisTown =
                            $this->entity_manager->getRepository(CitizenRankingProxy::class)->findOneBy(['user' => $citizen->getUser(),
                                                                                                         'town' => $this->town
                                                                                                        ]);
                        if ($pastLifeThisTown) {
                            $data[$field] = $pastLifeThisTown->getPoints();
                        }
                        break;
                    case "msg":
                        $data[$field] = $citizen->getLastWords();
                        break;
                    case "comment":
                        $data[$field] = $citizen->getComment();
                        break;
                    case "cleanUp":
                        if ($citizen->getDisposed() != null) {
                            $data['cleanup']['user'] =
                                $citizen->getDisposedBy()->count() > 0 ? $citizen->getDisposedBy()[0]->getName() : '';
                            $data['cleanup']['type'] = $type;
                        }
                        break;
                }
            }

        }
        return $data;
    }

    private function getPointsExpedition(ExpeditionRoute $expeditionRoute): array {
        $data = [];
        foreach ($expeditionRoute->getData() as $point) {
            $data['x'][] = $this->xTown + $point[0];
            $data['y'][] = $this->yTown - $point[1];
        }
        return $data;
    }

    private function getTownInformation(int $mapId): array {
        /** @var Town $town */
        $town = $this->entity_manager->getRepository(Town::class)->findOneBy(['id' => $mapId]);

        if (!$town) {
            return ["error" => "UnknownMap"];
        } else {
            $this->town = $town;
            $offset = $town->getMapOffset();
            $this->xTown = $offset['x'];
            $this->yTown = $offset['y'];
            return [];
        }
    }

    private function getTranslate(string $id, string $domain, array $parameters = []) {
        $data = [];
        foreach ($this->langue as $lang) {
            if (!is_string($lang) || strlen($lang) != 2) {
                continue;
            }
            try {
                $translate = $this->translator->trans($id, $parameters, $domain, $lang);

            } catch (Exception $e) {
                $translate = "null";
            }

            if (count($this->langue) == 1) {
                $data = $translate;
            } else {
                $data[$lang] = $translate;
            }

        }
        return $data;
    }

    private function getRequestParam($param) {
        $request = Request::createFromGlobals();
        $this->request = $request;

        $val = $request->request->get($param);
        if (trim($val) === '') {
            $val = $request->query->get($param);
        }

        if (trim($val) === '') {
            return false;
        } else {
            return $val;
        }
    }

    private function getUserKey(): array {
        $user_key = $this->getRequestParam('userkey');
        $user = null;
        if ($user_key === false) {
            return ["error" => "invalid_userkey"];
        } else {
            $user = $this->entity_manager->getRepository(User::class)->findOneBy(['externalId' => $user_key]);
            if (!$user) {
                return ["error" => "invalid_userkey"];
            } else {
                $this->user = $user;

                $current_user = $this->user->getActiveCitizen();
                if ($current_user) {
                    $this->mapIdUser = $current_user->getTown()->getId() ?? 0;
                }
            }
        }
        return [];
    }

    protected function getRequestLanguage(Request $request, ?User $user = null): string {
        $language =
            $request->query->get('lang') ??
            $request->request->get('lang') ??
            ($user ? $user->getLanguage() : null) ??
            'de';

        $language = explode('_', $language)[0];

        if ($language !== 'all' && !in_array($language, $this->available_langs)) {
            $language = 'de';
        }

        return $language;
    }

    protected function getIconPath(string $fullPath): string {
        $list = explode('/build/images/', $fullPath, 2);
        return count($list) === 2 ? $list[1] : $fullPath;
    }

    protected function isSecureRequest(): bool {
        $request = Request::createFromGlobals();

        // Try POST data
        $app_key = trim($request->query->get('appkey'));

        // Symfony 5 has a bug on treating request data.
        // If POST didn't work, access GET data.
        if ($app_key == '') {
            $app_key = trim($request->request->get('appkey'));
        }

        if ($app_key == '') {
            return false;
        }

        // Get the app.
        /** @var ExternalApp $app */
        $app = $this->entity_manager->getRepository(ExternalApp::class)->findOneBy(['secret' => $app_key]);

        if ($app === null) {
            return false;
        }
        return true;
    }
}

?>
