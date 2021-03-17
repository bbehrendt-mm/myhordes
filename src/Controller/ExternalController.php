<?php
namespace App\Controller;

use App\Annotations\GateKeeperProfile;
use App\Entity\ExternalApp;
use App\Entity\Item;
use App\Entity\ItemPrototype;
use App\Entity\Town;
use App\Entity\User;
use App\Entity\Zone;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\CrowService;
use App\Service\DeathHandler;
use App\Service\GameFactory;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\LogTemplateHandler;
use App\Service\PictoHandler;
use App\Service\RandomGenerator;
use App\Service\TimeKeeperService;
use App\Service\TownHandler;
use App\Service\UserHandler;
use App\Service\ZoneHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class ExternalController
 * @package App\Controller
 * @GateKeeperProfile(allow_during_attack=true, record_user_activity=false)
 */
class ExternalController extends InventoryAwareController {
    protected $game_factory;
    protected ZoneHandler $zone_handler;
    protected $item_factory;
    protected DeathHandler $death_handler;
    protected EntityManagerInterface $entity_manager;
    protected Packages $asset;
    protected $available_langs = ['en', 'fr', 'de', 'es'];

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
    
    public function __construct( EntityManagerInterface $em, InventoryHandler $ih, CitizenHandler $ch,
    ActionHandler $ah, TimeKeeperService $tk, DeathHandler $dh,
    PictoHandler $ph, TranslatorInterface $translator, GameFactory $gf,
    RandomGenerator $rg, ItemFactory $if, LogTemplateHandler $lh,
    ConfMaster $conf, ZoneHandler $zh, UserHandler $uh,
    CrowService $armbrust, Packages $a, TownHandler $th) {
        parent::__construct($em, $ih, $ch, $ah, $dh, $ph, $translator, $lh, $tk, $rg, $conf, $zh, $uh, $armbrust, $th);
        $this->game_factory = $gf;
        $this->item_factory = $if;
        $this->zone_handler = $zh;
        $this->entity_manager = $em;
        $this->asset = $a;
    }
    
    /**
    * @var Request
    */
    private $request;
    
    /**
    * @var SURLLobj
    */
    private $SURLLobj;

    protected function getRequestLanguage(Request $request, ?User $user = null): string {
        $language =
            $request->query->get('lang') ??
            $request->request->get('lang') ??
            ( $user ? $user->getLanguage() : null ) ??
            'de';

        $language = explode('_', $language)[0];

        if($language !== 'all' && !in_array($language, $this->available_langs))
            $language = 'de';

        return $language;
    }

    protected function getIconPath(string $fullPath): string {
        $list = explode('/build/images/', $fullPath, 2);
        return count($list) === 2 ? $list[1] : $fullPath;
    }

    /**
    * @Route("/jx/disclaimer/{id}", name="disclaimer", condition="request.isXmlHttpRequest()")
    * @param int $id
    * @return Response
    */
    public function disclaimer(int $id): Response {
        /** @var ExternalApp $app */
        $app = $this->entity_manager->getRepository(ExternalApp::class)->find($id);
        $user = $this->getUser();
        if(!$app || !$user || ($app->getTesting() && $app->getOwner() !== $user) )
            return $this->redirect($this->generateUrl('initial_landing'));

        $key = $user->getExternalId();
        
        return $this->render('ajax/public/disclaimer.html.twig', [
            'ex' => $app,
            'key' => $key
            ]
        );
    }
    
    /**
    * @Route("/jx/docs", name="docs", condition="request.isXmlHttpRequest()")
    * @return Response
    */
    public function documentation(Request $request): Response {
        return $this->render('ajax/public/apidocs.html.twig', []);
    }
    
    
    /**
    * @Route("/api/x/json/{type}", methods={"GET", "POST"})
    * @return Response
    */
    public function api_json($type = ''): Response {

        $data = [];

        $APP_KEY = $this->getRequestParam('appkey');
        if($APP_KEY===false) {
            $data = ["error" => "invalid_appkey"];
            $type = 'internalerror';
        }
        
        /** @var ExternalApp $app */
        $app = $this->entity_manager->getRepository(ExternalApp::class)->findOneBy(['secret' => $APP_KEY]);
        
        if(!$app) {
            $data = ["error" => "invalid_appkey"];
            $type = 'internalerror';
        }

        switch($type) {
            case 'internalerror':
                if(empty($data)) {
                    $data = [
                        "error" => "server_error",
                        "error_description" => "UnknownAction(default)"
                    ];
                }
                break;
            case '':
                $data = [
                    "error" => "server_error",
                    "error_description" => "UnknownAction(default)"
                ];
                break;
            case 'status':
                $data = [
                    "attack" => $this->time_keeper->isDuringAttack(),
                    "maintain" => is_file($this->getParameter('kernel.project_dir')."/public/maintenance/.active")
                ];
                break;
            case 'items':
                $SURLL_request = ['items' => [
                    'languages' => ['de', 'en', 'es', 'fr'],
                    'fields' => [
                        'img',
                        'name'
                        ]
                    ]
                ];
                        
                $fields = $this->getRequestParam('fields');
                $filter = $this->getRequestParam('filters');
                $langue = $this->getRequestParam('languages');

                if($fields!=false) {
                    $SURLL_request['items']['fields'] = $this->SURLL_preparser($fields);
                }
                if($filter!=false) {
                    $SURLL_request['items']['filters'] = $this->SURLL_preparser($filter);
                }
                if($langue!=false) {
                    $SURLL_request['items']['languages'] = $this->SURLL_preparser($langue);
                }
                        
                $data = $this->getItemsData($SURLL_request);
                break;
            case 'debug':
                $data = $this->getDebugdata();
                break;
            case "user":
            case "me":
                $user_key = $this->getRequestParam('userkey');
                $user = null;
                if($user_key===false) {
                    return $this->json(["error" => "invalid_userkey"]);
                } else {
                    $user = $this->entity_manager->getRepository(User::class)->findOneBy(['externalId' => $user_key]);
                    if (!$user) {
                        return $this->json(["error" => "invalid_userkey"]);
                    }
                }

                $SURLL_request = [
                    'user' => [
                        'languages' => ['de', 'en', 'es', 'fr'],
                        'fields' => [
                            'id',
                            'isGhost'
                        ]
                    ]
                ];

                if($type === "me") {
                    $SURLL_request['user']["filters"] = $user->getId();
                } else {
                    $user_id = intval($this->getRequestParam('id'));
                    if($user_id!=false||$user_id>0) {
                        $SURLL_request['user']["filters"] = $user_id;
                    } else {
                        return $this->json(["error" => "invalid_userid"]);
                    }
                }
                $fields = $this->getRequestParam('fields');
                $lang = $this->getRequestParam('languages');

                if($fields!=false) {
                    $SURLL_request['user']['fields'] = $this->SURLL_preparser($fields);
                }
                if($lang!=false) {
                    $SURLL_request['user']['languages'] = $this->SURLL_preparser($lang);
                }

                $data = $this->getUserData($SURLL_request, $user->getId());
                break;
            case "map":
                $user_key = $this->getRequestParam('userkey');
                $user = null;
                if($user_key===false) {
                    return $this->json(["error" => "invalid_userkey"]);
                } else {
                    $user = $this->entity_manager->getRepository(User::class)->findOneBy(['externalId' => $user_key]);
                    if (!$user) {
                        return $this->json(["error" => "invalid_userkey"]);
                    }
                }

                $map_id = intval($this->getRequestParam('mapId'));
                if($map_id!=false||$map_id>0) {
                    $SURLL_request = [
                        'map' => [
                            'filters' => $map_id,
                            'languages' => ['de', 'en', 'es', 'fr'],
                            'fields' => ['date', 'days', 'season', 'id', 'hei', 'wid', 'bonusPts', 'conspiracy', 'custom']
                        ]
                    ];

                    $fields = $this->getRequestParam('fields');
                    $lang = $this->getRequestParam('languages');

                    if($fields!=false) {
                        $SURLL_request['map']['fields'] = $this->SURLL_preparser($fields);
                    }
                    if($lang!=false) {
                        $SURLL_request['map']['languages'] = $this->SURLL_preparser($lang);
                    }

                    $data = $this->getMapData($SURLL_request, $user->getId());
                } else {
                    return $this->json(["error" => "invalid_mapid"]);
                }
                break;
        }

        if (!empty($data))
            return $this->json($data);

        return $this->json( $data );
    }
            
    private function getRequestParam($param) {
        $request = Request::createFromGlobals();
        $this->request = $request;
        
        $val = $request->request->get($param);
        if(trim($val)==='') {
            $val = $request->query->get($param);
        }

        if(trim($val)==='') {
            return false;
        } else {
            return $val;
        }
    }
    
    private function SURLL_preparser($surll_str): array {
        preg_match_all('/\.[a-z0-9\-]+|[a-z0-9\-]+|\(|\)/i', $surll_str, $surll_arr);
        $this->SURLLobj = $surll_arr[0];
        return $this->SURLL_parser();
    }
    
    private function SURLL_parser(): array {
        $parsed = [];
        while(count($this->SURLLobj)>0) {
            $surll_item = array_shift($this->SURLLobj);
            if($surll_item==="(") {
                continue;
            } else if($surll_item===")") {
                return $parsed;
            } else if($surll_item[0]===".") {
                $last_surll_item = array_pop($parsed);
                $name = "";
                if(is_string($last_surll_item)) {
                    $name = $last_surll_item;
                    $last_surll_item = [];
                    $last_surll_item[$name] = [];
                } else {
                    $name = array_keys($last_surll_item)[0];
                }
                $last_surll_item[$name][substr($surll_item, 1)] = $this->SURLL_parser($this->SURLLobj);
                $parsed[] = $last_surll_item;
            } else {
                $parsed[] = $surll_item;
            }
        }
        return $parsed;
    }
    
    private function getItemVal(ItemPrototype $item, $key) {
        switch($key) {
            case "name":
                return $item->getLabel();
            case "desc":
                return $item->getDescription();
            case "cat":
                return $item->getCategory()->getLabel();
            default:
                return false;
        }
    }
                    
    private function getItemsData($SURLL_request): array {
        $data = [];
        if(isset($SURLL_request['items']['filters'])&&is_array($SURLL_request['items']['filters'])) {
            $filters = [];
            foreach($SURLL_request['items']['filters'] as $key => $val) {
                if(is_string($val)) {
                    $filters[] = $val;
                }
            }
            $items = $this->entity_manager->getRepository(ItemPrototype::class)->findBy(['icon' => $filters]);
        } else {
            $items = $this->entity_manager->getRepository(ItemPrototype::class)->findAll();
        }
        /** @var ItemPrototype $ItemProto */
        foreach ( $items as $ItemProto ) {
            $icon = $ItemProto->getIcon();
            $item = [];
            foreach($SURLL_request['items']['fields'] as $field) {
                switch($field) {
                    case 'hid':
                        $item['hid']= $ItemProto->getId();
                        break;
                    case 'img':
                        $item['img']= $icon;
                        break;
                    case 'uid':
                        $item['uid']= $icon;
                        break;
                    case 'heavy':
                        $item['heavy']= $ItemProto->getHeavy();
                        break;
                    case 'deco':
                        $item['deco']= $ItemProto->getDeco();
                        break;
                    case 'guard':
                        $item['guard']= $ItemProto->getWatchpoint();
                        break;
                }
            }
            foreach($SURLL_request['items']['languages'] as $lang) {
                if(!is_string($lang)||strlen($lang)!=2) continue;
                foreach($SURLL_request['items']['fields'] as $field) {
                    $field_val = $this->getItemVal($ItemProto, $field);
                    if($field_val!=false) {
                        if(!isset($item[$field])) $item[$field]= [];
                        $item[$field][$lang]= $this->translator->trans($field_val, [], 'items', $lang);
                    }
                }
            }
            $data[$icon] = $item;
        }
        return $data;
    }
    
    private function getUserData($SURLL_request, $originalUserID): array {
        /** @var User $user */
        $user= $this->entity_manager->getRepository(User::class)->findOneBy(['id' => $SURLL_request['user']['filters']]);
        if (!$user) {
            return ["error" => "UnknownUser"];
        }
        $current_citizen= $user->getActiveCitizen();
        $user_data = [];
        foreach($SURLL_request['user']['fields'] as $field) {
            switch(true) {
                case $field==="id":
                    $user_data['id']= $user->getId();
                break;
                case $field==="name":
                    $user_data['name']= $user->getName();
                break;
                case $field==="avatar":
                    $has_avatar = $user->getAvatar();
                    if($has_avatar) {
                        $user_data['avatar']= $this->generateUrl('app_web_avatar', ['uid' => $user->getId(), 'name' => $has_avatar->getFilename(), 'ext' => $has_avatar->getFormat()], UrlGeneratorInterface::ABSOLUTE_URL);
                    } else $user_data['avatar'] = false;
                    break;
                case $field==="isGhost":
                    $user_data['isGhost']= ($current_citizen === null);
                break;
                case ($current_citizen && $field==="homeMessage"):
                    $user_data['homeMessage']= $current_citizen->getHome()->getDescription();
                    break;
                case ($current_citizen && $field==="hero"):
                    $user_data['hero'] = $current_citizen->getProfession()->getHeroic();
                break;
                case ($current_citizen && $field==="dead"):
                    $user_data['dead']= !$current_citizen->getAlive();
                break;
                case ($current_citizen && $field==="job"):
                    $user_data['job']= $current_citizen->getProfession()->getName();
                break;
                case ($current_citizen && $field==="out"):
                    $user_data['out']= $current_citizen->getZone() ? true : false;
                break;
                case ($current_citizen && $field==="baseDef"):
                    $user_data['baseDef']= $current_citizen->getHome()->getAdditionalDefense();
                break;
                case ($current_citizen && $field==="ban"):
                    $user_data['ban']= $current_citizen->getBanished();
                break;
                case ($current_citizen && $field==="x"):
                    $zone = $current_citizen->getTown()->getChaos() ? null : $current_citizen->getZone();
                    $user_data['x']= $zone ? $zone->getX() : 0;
                break;
                case ($current_citizen && $field==="y"):
                    $zone = $current_citizen->getTown()->getChaos() ? null : $current_citizen->getZone();
                    $user_data['y']= $zone ? $zone->getY() : 0;
                break;
                case ($current_citizen && $field==="mapId"):
                    $user_data['mapId']= $current_citizen->getTown()->getId();
                    break;
                case ($current_citizen && $field==="map"):
                    $user_data['map']= $this->getMapData(['map' => [
                        'filters' => $current_citizen->getTown()->getId(),
                        'languages' => ['de', 'en', 'es', 'fr'],
                        'fields' => ['date', 'days', 'season', 'id', 'hei', 'wid', 'bonusPts', 'conspiracy', 'custom']
                        ]], $originalUserID);
                    break;
                case ($user->getId()===$originalUserID && $field==="playedMaps"):
                    $user_data['playedMaps']= "playedMaps";
                    break;
                default:
                    if(is_array($field)) {
                        foreach($field as $ProtoFieldName => $ProtoFieldValue) {
                            switch(true) {
                                case ($current_citizen && $ProtoFieldName==="map"):
                                    if(!isset($ProtoFieldValue['languages'])) $field['map']['languages'] = ['fr','en','de','es'];
                                    if(!isset($ProtoFieldValue['fields'])) $field['map']['fields']= ['date', 'days', 'season', 'id', 'hei', 'wid', 'bonusPts', 'conspiracy', 'custom'];
                                    $field['map']['filters']= $current_citizen->getTown()->getId();
                                    $user_data['map'] = $this->getMapData($field, $originalUserID);
                                break;
                            }
                        }
                    }
                    break;
            }
        }
        return $user_data;
    }
            
    private function getMapData($SURLL_request, $originalUserID): array {
        /** @var Town $town */
        $town= $this->entity_manager->getRepository(Town::class)->findOneBy(['id' => $SURLL_request['map']['filters']]);
        if (!$town) {
            return ["error" => "UnknownMap"];
        }
        
        $x_min = $x_max = $y_min = $y_max = 0;
        foreach ( $town->getZones() as $zone ) {
            /** @var Zone $zone */
            $x_min = min($zone->getX(), $x_min);
            $x_max = max($zone->getX(), $x_max);
            $y_min = min($zone->getY(), $y_min);
            $y_max = max($zone->getY(), $y_max);
        }
        
        $data = [];
        foreach($SURLL_request['map']['fields'] as $field) {
            if(is_array($field)) {
                foreach($field as $ProtoFieldName => $ProtoFieldValue) {
                    switch($ProtoFieldName) {
                        case "city":
                            foreach ($ProtoFieldValue["fields"] as $innerFieldName) {
                                if(is_array($innerFieldName))continue;
                                $data["city"][$innerFieldName] = [];
                                switch($innerFieldName){
                                    case "bank":
                                        // The town bank
                                        foreach($town->getBank()->getItems() as $bankItem) {
                                            /** @var Item $bankItem */
                                            $str = "{$bankItem->getPrototype()->getId()}-" . intval($bankItem->getBroken());
                                            if (!isset($data["city"]["bank"][$str])) {
                                                $cat = $bankItem->getPrototype()->getCategory();
                                                while ($cat->getParent()) $cat = $cat->getParent();

                                                $item = [
                                                    'deco' => $bankItem->getPrototype()->getDeco(),
                                                    'count' => $bankItem->getCount(),
                                                    'id' => $bankItem->getPrototype()->getId(),
                                                    'cat' => $cat->getName(),
                                                    'img' => $this->getIconPath($this->asset->getUrl("build/images/item/item_{$bankItem->getPrototype()->getIcon()}.gif")),
                                                    'broken' => $bankItem->getBroken(),
                                                    'heavy' => $bankItem->getPrototype()->getHeavy()
                                                ];
                                                if (!is_array($SURLL_request['map']['languages']) && $field['map']['languages'] !== 'all') {
                                                    $item['name'] = $this->translator->trans($bankItem->getPrototype()->getLabel(), [], 'items');
                                                } else if (is_array($SURLL_request['map']['languages'])){
                                                    foreach ($SURLL_request['map']['languages'] as $lang) {
                                                        $item["name-$lang"] = $this->translator->trans($bankItem->getPrototype()->getLabel(), [], 'items', $lang);
                                                    }
                                                } else {
                                                    foreach ($this->available_langs as $lang) {
                                                        $item["name-$lang"] = $this->translator->trans($bankItem->getPrototype()->getLabel(), [], 'items', $lang);
                                                    }
                                                }
                                                $data["city"]["bank"][$str] = $item;

                                            } else $data["city"]["bank"][$str]['count'] += $bankItem->getCount();
                                        }
                                        usort( $data['city']['bank'],
                                            fn($a,$b) => $a['id'] <=> $b['id'] ?? $b['broken'] <=> $a['broken']);
                                        break;
                                    case "door":
                                        $data['city']['door']= $town->getDoor();
                                        break;
                                    case "hard":
                                        $data['city']['hard']= $town->getType()->getName() == 'panda';
                                        break;
                                    case "water":
                                        $data['city']['water']= $town->getWell();
                                        break;
                                    case "name":
                                        $data['city']['name']= $town->getName();
                                        break;
                                    case "chaos":
                                        $data['town']['chaos']= $town->getChaos();
                                        break;
                                    case "devast":
                                        $data['town']['devast']= $town->getDevastated();
                                        break;
                                    case "estimations":
                                        $estim = $this->town_handler->get_zombie_estimation($town);
                                        $data['city']['estimations'] = [
                                            'day' => $town->getDay(),
                                            'max' => $estim[0]->getMax(),
                                            'min' => $estim[0]->getMin(),
                                            'maxed' => $estim[0]->getEstimation() >= 1
                                        ];
                                        break;
                                    case "estimationsNext":
                                        $estim = $this->town_handler->get_zombie_estimation($town);
                                        if(isset($estim[1]))
                                            $data['city']['estimationsNext'] = [
                                                'day' => $town->getDay() + 1,
                                                'max' => $estim[1]->getMax(),
                                                'min' => $estim[1]->getMin(),
                                                'maxed' => $estim[1]->getEstimation() >= 1
                                            ];
                                        else
//                                                    $data['city']['estimationsNext'] = [];
                                            break;
                                }
                            }
                            break;
                    }
                }
            } else {
                switch ($field) {
                    case "id":
                        $data['id'] = $town->getId();
                        break;
                    case "date":
                        $now = new \DateTime();
                        $data['date'] = $now->format('Y-m-d H:m:s');
                        break;
                    case "wid":
                        $data['wid'] = abs($x_min) + abs($x_max) + 1;
                        break;
                    case "hei":
                        $data['hei'] = abs($y_min) + abs($y_max) + 1;
                        break;
                    case "conspiracy":
                        $data['conspiracy'] = $town->getInsurrectionProgress() >= 100;
                        break;
                    case "days":
                        $data['days'] = $town->getDay();
                        break;
                }
            }
        }
        return $data;
    }
            
    private function getDebugdata(): array {
        $town_id = intval($this->getRequestParam('tid'));
        if($town_id!=false||$town_id>0) {
            $town = $this->entity_manager->getRepository(Town::class)->findOneBy(['id' => $town_id]);
            if($town) {
                $towns = [ $town ];
            } else {
                $towns = $this->entity_manager->getRepository(Town::class)->findOpenTown();
            }
        } else {
            $towns = $this->entity_manager->getRepository(Town::class)->findOpenTown();
        }
        $data = [];
        /** @var Town $town */
        foreach($towns as $town) {
            $x_min = $x_max = $y_min = $y_max = 0;
            /** @var Zone $zone */
            foreach ( $town->getZones() as $zone ) {
                $x_min = min($zone->getX(), $x_min);
                $x_max = max($zone->getX(), $x_max);
                $y_min = min($zone->getY(), $y_min);
                $y_max = max($zone->getY(), $y_max);
            }
            $town_data = [
                'id' => $town->getId(),
                'name' => $town->getName(),
                'day' => $town->getDay(),
                'height' => abs($y_min)+abs($y_max)+1,
                'width' => abs($x_min)+abs($x_max)+1,
                'type' => ['','RNE','RE','PANDE'][$town->getType()->getId()],
                'language' => $town->getLanguage(),
                'zone' => []
            ];
            /** @var Zone $zone */
            foreach ( $town->getZones() as $zone ) {
                $zone_data = [
                    'x' => $zone->getX()-$x_min,
                    'y' => $y_max-$zone->getY(),
                    'km' => $this->zone_handler->getZoneKm($zone),
                    'remaining_excavation' => $zone->getDigs(),
                    'zombies' => $zone->getZombies(),
                    'is_town' => $zone->getDistance()<1,
                    'items' => [],
                    'building' => false
                ];
                $item_buffer = [];
                foreach ($zone->getFloor()->getItems() as $item) {
                    $item_uid = implode('_', [
                        $item->getPrototype()->getIcon(),
                        $item->getBroken()
                        ]
                    );
                    if(!isset($item_buffer[$item_uid])) {
                        $item_buffer[$item_uid] = [
                            'uid' => $item->getPrototype()->getIcon(),
                            'broken' => $item->getBroken(),
                            'count' => $item->getCount()
                        ];
                    } else {
                        $item_buffer[$item_uid]['count'] += $item->getCount();
                    }
                }
                foreach ($item_buffer as $item) {
                    $zone_data['items'][]= $item;
                }


                if($zone->getPrototype()) {
                    $zone_data['building'] = [
                        'name' => $this->translator->trans($zone->getPrototype()->getLabel(), [], "game", $town->getLanguage()),
                        'type' => $zone->getPrototype()->getId(),
                        'sandpile' => $zone->getBuryCount(),
                        'remaining_blueprint' => $zone->getBlueprint(),
                        'remaining_excavation' => $zone->getRuinDigs()
                    ];
                }
                $town_data['zone'][] = $zone_data;
            }
            $data[]= $town_data;
        }
        return $data;
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

        if($app_key == '') {
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