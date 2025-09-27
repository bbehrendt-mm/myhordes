<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\ActionEventLog;
use App\Entity\BlackboardEdit;
use App\Entity\BuildingPrototype;
use App\Entity\CitizenProfession;
use App\Entity\CitizenStatus;
use App\Entity\EventActivationMarker;
use App\Entity\ItemPrototype;
use App\Entity\RuinExplorerStats;
use App\Entity\Town;
use App\Entity\TownRankingProxy;
use App\Entity\User;
use App\Entity\ZombieEstimation;
use App\Entity\Zone;
use App\Enum\Configuration\MyHordesSetting;
use App\Enum\Configuration\TownSetting;
use App\Response\AjaxResponse;
use App\Service\Actions\Game\GenerateTownNameAction;
use App\Service\CrowService;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\Maps\MapMaker;
use App\Service\NightlyHandler;
use App\Service\RandomGenerator;
use App\Service\TownHandler;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownDashboardController extends AdminActionController
{
    /**
     * @param Town $town
     * @param TownHandler $townHandler
     * @param KernelInterface $kernel
     * @return Response
     * @throws Exception
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/dash', name: 'admin_town_dashboard')]
    #[IsGranted('spy', 'town')]
    public function town_explorer_dash(Town $town, TownHandler $townHandler, KernelInterface $kernel): Response {
		return $this->render('ajax/manage/towns/explorer_dash.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
			'itemPrototypes' => $this->getOrderedItemPrototypes($this->getUser()->getAdminLang() ?? $this->getUser()->getLanguage()),
			'tab' => "dash",
			'events' => $this->conf->getAllEvents(),
			'current_event' => $this->conf->getCurrentEvents($town),
			'langs' => array_merge($this->generatedLangsCodes, ['multi']),
			'map_public_json' => json_encode($townHandler->get_public_map_blob($town, null, 'door-planner', 'day', "admin/{$town->getId()}", true)),
            'debug' => $kernel->getEnvironment() === 'dev' || $kernel->getEnvironment() === 'local' || $this->conf->getGlobalConf()->get(MyHordesSetting::StagingSettingsEnabled)
		])));
	}

    /**
     * @param Town $town
     * @param string $action The action to perform
     * @param ItemFactory $itemFactory
     * @param RandomGenerator $random
     * @param NightlyHandler $night
     * @param GameFactory $gameFactory
     * @param CrowService $crowService
     * @param KernelInterface $kernel
     * @param JSONRequestParser $parser
     * @param TownHandler $townHandler
     * @param GameProfilerService $gps
     * @param MapMaker $mapMaker
     * @param GenerateTownNameAction $townNameAction
     * @return Response
     * @throws Exception
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/do/{action}', name: 'admin_town_manage')]
    #[isGranted('edit','town')]
    #[AdminLogProfile(enabled: true)]
    public function town_manager(Town $town, string $action, ItemFactory $itemFactory, RandomGenerator $random,
                                 NightlyHandler $night, GameFactory $gameFactory, CrowService $crowService,
                                 KernelInterface $kernel, JSONRequestParser $parser, TownHandler $townHandler,
                                 GameProfilerService $gps, MapMaker $mapMaker, GenerateTownNameAction $townNameAction)
    : Response
    {
        if ((str_starts_with($action, 'dbg_') || in_array($action, ['ex_inf'])) &&
            !($kernel->getEnvironment() === 'dev' || $kernel->getEnvironment() === 'local' || $this->conf->getGlobalConf()->get(MyHordesSetting::StagingSettingsEnabled))
        )
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (in_array($action, [
                'set_name', 'dice_name'
            ]) && !$this->isGranted('edit', $town))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if (in_array($action, [
                'advance', 'dbg_fill_town', 'set_town_base_def', 'set_town_temp_def', 'toggle_lockdown',
                'toggle_broken_door', 'dbg_enable_stranger', 'dbg_fill_bank', 'dgb_empty_bank', 'dbg_unlock_bank',
                'dbg_hydrate', 'dbg_set_well', 'dbg_unlock_buildings', 'dbg_map_progress', 'dbg_map_zombie_set',
                'dbg_adv_days', 'dbg_set_attack', 'dbg_toggle_chaos', 'dbg_toggle_devas', 'ex_del', 'ex_co+', 'ex_co-',
                'ex_ref', 'ex_inf',
            ]) && !$this->isGranted('cheat', $town))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if (in_array($action, [
                'pw_change', 'dbg_disengage', 'dbg_engage', 'dropall',
            ]) && !$this->isGranted('administrate', $town))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        if (in_array($action, [
                'release', 'quarantine', 'nullify'
            ]) && !$this->isGranted('sudo', $town))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $this->logger->invoke("[town_manager] Admin <info>{$this->getUser()->getName()}</info> did the action <info>$action</info> in the town <info>{$town->getName()}</info> (id: {$town->getId()})");
        $this->clearTownCaches($town);

        $param = $parser->get('param');

        switch ($action) {
            case 'release':
                if ($town->getAttackFails() >= 3)
                    foreach ($town->getCitizens() as $citizen)
                        if ($citizen->getAlive())
                            $this->entity_manager->persist(
                                $crowService->createPM_townQuarantine($citizen->getUser(), $town->getName(), false)
                            );
                $town->setAttackFails(0);
                $this->entity_manager->persist($town);
                break;
            case 'quarantine':
                if ($town->getAttackFails() < 3)
                    foreach ($town->getCitizens() as $citizen)
                        if ($citizen->getAlive())
                            $this->entity_manager->persist(
                                $crowService->createPM_townQuarantine($citizen->getUser(), $town->getName(), true)
                            );
                $town->setAttackFails(4);
                $this->entity_manager->persist($town);
                break;
            case 'advance':
                if ($night->advance_day($town, $this->conf->getCurrentEvents($town))) {
                    foreach ($night->get_cleanup_container() as $c) $this->entity_manager->remove($c);
                    $town->setAttackFails(0);
                    $this->entity_manager->persist($town);
                }
                break;
            case 'pw_change':
                if (!$town->isOpen()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                $town->setPassword( empty(trim($param)) ? null : $param );
                break;
            case 'nullify':
                foreach ($town->getCitizens() as $citizen)
                    $this->entity_manager->persist(
                        $crowService->createPM_townNegated($citizen->getUser(), $town->getName(), false)
                    );
                $gameFactory->nullifyTown($town, true);
                break;
            case 'clear_bb':
                $town->setWordsOfHeroes("");
                $this->entity_manager->persist((new BlackboardEdit())->setText("")->setTime(new \DateTime())->setTown($town)->setUser($this->getUser()));
                $this->entity_manager->persist($town);
                break;
            case 'set_name': case 'dice_name':
                $old_name = $town->getName();
                $schema = null;
                $new_name = $action === 'dice_name'
                    ? ($townNameAction)($town->getLanguage(), $schema)
                    : trim($param ?? '');
                if (empty($new_name)) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                $town->setName( $new_name )->setNameSchema( $schema );
                $town->getRankingEntry()->setName( $new_name );
                $town->getForum()?->setTitle( $new_name );
                $this->entity_manager->persist($town);
                $this->entity_manager->persist($town->getRankingEntry());
                foreach ($town->getCitizens() as $citizen)
                    $this->entity_manager->persist($this->crow_service->createPM_moderation( $citizen->getUser(), CrowService::ModerationActionDomainRanking, CrowService::ModerationActionTargetGameName, CrowService::ModerationActionEdit, $town, $old_name ));
                break;
            case 'toggle_lockdown':
                $town->setLockdown(!$town->getLockdown());
                if($town->getLockdown()) {
                    $town->setDoor(false);
                }
                $this->entity_manager->persist($town);
                break;
            case 'toggle_broken_door':
                $town->setBrokenDoor(!$town->getBrokenDoor());
                if($town->getBrokenDoor()) {
                    $town->setDoor(true);
                }
                $this->entity_manager->persist($town);
                break;
            case 'dbg_disengage':
                foreach ($town->getCitizens() as $citizen)
                    if ($citizen->getAlive() && $citizen->getActive())
                        $this->entity_manager->persist($citizen->setActive(false));
                break;

            case 'dbg_engage':
                foreach ($town->getCitizens() as $citizen)
                    if ($citizen->getAlive() && !$citizen->getActive()) {
                        if ($citizen->getUser()->getActiveCitizen())
                            $this->entity_manager->persist($citizen->getUser()->getActiveCitizen()->setActive(false));
                        $this->entity_manager->persist($citizen->setActive(true));
                    }
                break;

            case 'dbg_fill_town':
                $missing = $town->getPopulation() - $town->getCitizenCount();
                if ($missing <= 0) break;

                $users = []; $backup = [];
                for ($i = 1; $i <= 80; $i++) if (count($users) < $missing) {
                    $user_name = 'user_' . str_pad($i, 3, '0', STR_PAD_LEFT);

                    /** @var User $selected_user */
                    $selected_user = $this->entity_manager->getRepository(User::class)->findOneBy(['name' => $user_name]);
                    if ($selected_user === null) continue;
                    if ($selected_user->getActiveCitizen()) $backup[] = $selected_user;
                    else $users[] = $selected_user;
                }

                $disabled_profs = $this->conf->getTownConfiguration($town)->get(TownSetting::DisabledJobs);
                $professions = array_filter($this->entity_manager->getRepository( CitizenProfession::class )->findSelectable(),
                    fn(CitizenProfession $p) => !in_array($p->getName(),$disabled_profs)
                );

                while ($town->getPopulation() > ($town->getCitizenCount() + count($users)) && !empty($backup)) {
                    /** @var User $selected_user */
                    $selected_user = $backup[0]; $backup = array_slice($backup, 1);
                    $this->entity_manager->persist($selected_user->getActiveCitizen()->setActive(false));
                    $users[] = $selected_user;
                }

                $this->entity_manager->flush();

                $null = null;
                foreach ($users as $selected_user) {
                    $citizen = $gameFactory->createCitizen($town, $selected_user, $error, $null, true);
                    if ($citizen === null) continue;
                    $this->entity_manager->persist($town);
                    $this->entity_manager->persist($citizen);
                    $this->entity_manager->flush();

                    $pro = $random->pick($professions);
                    $this->citizen_handler->applyProfession($citizen, $pro);
                    $this->entity_manager->persist($citizen);
                    $this->entity_manager->persist($town);

                    if ($citizen->getProfession()->getName() !== 'none')
                        $gps->recordCitizenProfessionSelected( $citizen );

                    $this->entity_manager->flush();
                }

                break;

            case 'dbg_fill_bank':
                $bank = $town->getBank();
                foreach ($this->entity_manager->getRepository(ItemPrototype::class)->findAll() as $repo)
                    $this->inventory_handler->forceMoveItem( $bank, ($itemFactory->createItem( $repo ))->setCount(500) );

                $this->entity_manager->persist( $bank );
                break;

            case 'dbg_empty_bank':
                $bank = $town->getBank();
                foreach ($bank->getItems() as $item)
                    $this->inventory_handler->forceRemoveItem($item, $item->getCount());

                $this->entity_manager->persist( $bank );
                break;

            case 'dbg_unlock_bank':
                foreach ($town->getCitizens() as $citizen) {
                    $bank_lock = $this->entity_manager->getRepository(ActionEventLog::class)->findBy(['citizen' => $citizen, 'type' => [ActionEventLog::ActionEventTypeBankTaken, ActionEventLog::ActionEventTypeBankLock]]);
                    foreach ($bank_lock as $lock) $this->entity_manager->remove($lock);
                }
                break;

            case 'dbg_hydrate':
                $thirst1 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName('thirst1');
                $thirst2 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneByName('thirst2');
                foreach ($town->getCitizens() as $citizen) {
                    $this->citizen_handler->removeStatus( $citizen, $thirst1 );
                    $this->citizen_handler->removeStatus( $citizen, $thirst2 );
                    $this->entity_manager->persist($citizen);
                }
                break;

            case 'dbg_set_well':
                if (!is_numeric($param)) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                $town->setWell( max(0,$param));
                $this->entity_manager->persist($town);
                break;

            case 'dbg_unlock_buildings':
                do {
                    $possible = array_filter( $this->entity_manager->getRepository(BuildingPrototype::class)->findProspectivePrototypes( $town ), function(BuildingPrototype $p) use ($town) {
                        $bp = $this->conf->getTownConfiguration( $town )->getBuildingRarity( $p );
                        return $bp === null || $bp < 5;
                    } );
                    $found = !empty($possible);
                    foreach ($possible as $proto) {
                        $townHandler->addBuilding($town, $proto);
                        $gps->recordBuildingDiscovered( $proto, $town, null, 'debug' );
                    }
                } while ($found);
                $this->entity_manager->persist( $town );
                break;

            case 'dbg_map_progress':
                if (empty($param)) $d = null;
                else {
                    if (!is_numeric($param) || (int)$param <= 0) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                    $d = (int)$param;
                }
                $mapMaker->dailyZombieSpawn( $town, 1, MapMaker::RespawnModeAuto, $d );
                $this->entity_manager->persist( $town );
                break;

            case 'dbg_map_zombie_set':
                $param_base = explode(',',$param);
                if (count($param_base) !== 2) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                if (trim($param_base[1]) === 'today') $zeds = -1;
                elseif (trim($param_base[1]) === 'initial') $zeds = -2;
                else {
                    if (!is_numeric(trim($param_base[1]))) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                    $zeds = (int)trim($param_base[1]);
                    if ($zeds < 0) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                }

                if ($param_base[0] === 'all') {
                    foreach ($town->getZones() as $zone) if (!$zone->isTownZone())
                        $zone
                            ->setZombies( $zeds === -1 ? $zone->getInitialZombies() : ( $zeds === -2 ? $zone->getStartZombies() : $zeds ) )
                            ->setInitialZombies( $zeds === -2 ? $zone->getStartZombies() : $zone->getInitialZombies() );

                } else {
                    $param_vals = explode(':',$param_base[0]);
                    if (count($param_vals) === 1) $param_vals[] = $param_vals[0];
                    elseif (count($param_vals) > 2) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $pair = explode( '/', $param_vals[0] );
                    if (count($pair) !== 2 || !is_numeric(trim($pair[0])) || !is_numeric(trim($pair[1]))) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $from_x = (int)trim($pair[0]);
                    $from_y = (int)trim($pair[1]);

                    $pair = explode( '/', $param_vals[1] );
                    if (count($pair) !== 2 || !is_numeric(trim($pair[0])) || !is_numeric(trim($pair[1]))) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $to_x = (int)trim($pair[0]);
                    $to_y = (int)trim($pair[1]);

                    for ($x = min($from_x,$to_x); $x <= max($from_x,$to_x); $x++)
                        for ($y = min($from_y,$to_y); $y <= max($from_y,$to_y); $y++)
                            if (($zone = $this->entity_manager->getRepository(Zone::class)->findOneByPosition($town,$x,$y)) && !$zone->isTownZone())
                                $zone
                                    ->setZombies( $zeds === -1 ? $zone->getInitialZombies() : ( $zeds === -2 ? $zone->getStartZombies() : $zeds ) )
                                    ->setInitialZombies( $zeds === -2 ? $zone->getStartZombies() : $zone->getInitialZombies() );                }


                $this->entity_manager->persist( $town );
                break;

            case 'dbg_adv_days':
                $days = (int)$param;
                if ($days <= 0) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                for ($i = 0; $i < $days; $i++)
                    if ($night->advance_day($town, $this->conf->getCurrentEvents($town))) {
                        foreach ($night->get_cleanup_container() as $c) $this->entity_manager->remove($c);
                        $town->setAttackFails(0);
                        $this->entity_manager->persist($town);
                        foreach ($town->getCitizens() as $c)
                            if ($c->getAlive()) $this->citizen_handler->removeStatus($c, 'thirst2');
                        $this->entity_manager->flush();
                    } else break;

                break;

            case 'dbg_set_attack':
                if (empty($param)) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                $list = explode(':', $param);
                if (count($list) === 1) $list = [ $town->getDay(), (int)$list[0] ];
                else $list = [ (int)$list[0], (int)$list[1] ];

                if ($list[0] < $town->getDay() || $list[1] <= 0) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest, [$list[0],$town->getDay(),$list[1]]);
                $est = $this->entity_manager->getRepository(ZombieEstimation::class)->findOneByTown($town,$list[0]);
                if ($est === null) {
                    $off_min = mt_rand( 10, 24 );
                    $off_max = 34 - $off_min;
                    $town->addZombieEstimation(
                        $est = (new ZombieEstimation())
                            ->setDay( $list[0] )
                            ->setZombies( $list[1] )
                            ->setOffsetMin( $off_min )
                            ->setOffsetMax( $off_max )
                    );
                } else $est->setZombies($list[1]);

                $this->entity_manager->persist($est);
                break;

            case 'dbg_toggle_chaos':
                $on = $param === '1';
                if (($town->getChaos() === $on) || ($town->getDevastated() && !$on))
                    return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                $town->setChaos($on);
                if ($on) foreach ($town->getCitizens() as $target_citizen)
                    $target_citizen->setBanished(false);
                break;

            case 'dbg_toggle_devas':
                $on = $param === '1';
                if ($town->getDevastated() === $on) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                if ($on)
                    $townHandler->devastateTown($town);
                else $town->setDevastated(false);
                break;

            case 'ex_del': case 'ex_co+': case 'ex_co-':case 'ex_ref':case 'ex_inf':
                /** @var RuinExplorerStats $session */
                $session = $this->entity_manager->getRepository(RuinExplorerStats::class)->find($param);
                if (!$session || $session->getCitizen()->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                if ($action !== 'ex_del' && !$session->getActive()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                switch ($action) {
                    case 'ex_del':
                        $session->getCitizen()->removeExplorerStat( $session );
                        $this->entity_manager->remove( $session );
                        break;
                    case 'ex_co+':
                        $session->setTimeout(new \DateTime())->setActive(false);
                        $this->entity_manager->persist($session);
                        break;
                    case 'ex_co-':
                        $session->setTimeout(new \DateTime());
                        $this->entity_manager->persist($session);
                        break;
                    case 'ex_ref':
                        $session->setTimeout(clone $session->getTimeout()->modify('+1min'));
                        $this->entity_manager->persist($session);
                        break;
                    case 'ex_inf':
                        $session->setTimeout(clone $session->getTimeout()->modify('+24hours'));
                        $this->entity_manager->persist($session);
                        break;
                    default: break;
                }
                break;

            case 'dbg_enable_stranger':
                $gameFactory->enableStranger( $town );
                break;

            case 'dropall':
                foreach ($town->getCitizens() as $citizen) {
                    if (!$citizen->getAlive()) continue;
                    foreach ($citizen->getInventory()->getItems() as $item)
                        if (!$item->getEssential())
                            $this->inventory_handler->forceMoveItem( ($citizen->getZone()?->isTownZone() ? $town->getBank() : $citizen->getZone()?->getFloor()) ?? $town->getBank(), $item );
                    foreach ($citizen->getHome()->getChest()->getItems() as $item)
                        $this->inventory_handler->forceMoveItem( $town->getBank(), $item );
                }
                break;
            case 'admin_regenerate_ruins':

                break;
            case 'set_town_base_def':
                $town->setBaseDefense($param);
                break;
            case 'set_town_temp_def':
                $town->setTempDefenseBonus($param);
                break;

            default:
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException, ['e' => $e->getMessage()]);
        }

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @param TownHandler $townHandler
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/set_event', name: 'admin_town_set_event')]
    #[isGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function admin_town_set_event(Town $town, JSONRequestParser $parser, TownHandler $townHandler): Response {
        $eventName = $parser->get('param');

        $town->setManagedEvents($eventName !== "");

        if($eventName !== "" && $eventName !== null){
            $this->logger->invoke("[admin_town_set_event] Admin <info>{$this->getUser()->getName()}</info> enabled the event <info>$eventName</info> in the town <info>{$town->getName()}</info> (id: {$town->getId()})");
            $townHandler->updateCurrentEvents($town, [$this->conf->getEvent($eventName)]);
        } else {
            $this->logger->invoke("[admin_town_set_event] Admin <info>{$this->getUser()->getName()}</info> disabled the events in the town <info>{$town->getName()}</info> (id: {$town->getId()})");
            $currentEvents = $this->conf->getCurrentEvents($town, $markers);
            foreach ($markers as $marker) {
                /** @var EventActivationMarker $marker */
                $marker->setActive(false);
                $this->entity_manager->persist($marker);
            }
        }

        $this->clearTownCaches($town);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/set_lang', name: 'admin_town_set_lang')]
    #[isGranted('edit', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function admin_town_set_lang(Town $town, JSONRequestParser $parser): Response {
        $newLang = $parser->get('param');
        if (!in_array( $newLang, array_merge($this->generatedLangsCodes, [ 'multi' ]) ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $town->setLanguage( $newLang );
        $town->getRankingEntry()->setLanguage( $newLang );

        $this->clearTownCaches($town);
        $this->entity_manager->persist($town);
        $this->entity_manager->persist($town->getRankingEntry());
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/zone_infos', name: 'get_zone_infos')]
    #[IsGranted('spy', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function get_zone_infos(Town $town, JSONRequestParser  $parser): Response {
        $zone_id = $parser->get('zone_id', -1);
		/** @var Zone $zone */
        $zone = $this->entity_manager->getRepository(Zone::class)->find($zone_id);

        if(!$zone || $zone->getTown() !== $town)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $view = $this->renderView("ajax/game/inventory.html.twig", [
            'size' => 0,
            'items' => $zone->getFloor()->getItems()
        ]);

        return AjaxResponse::success(true, [
            'view' => $view,
			'zone_coords' => ["x" => $zone->getX(), "y" => $zone->getY()],
            'zone_digs' => $zone->getDigs(),
            'ruin_digs' => $zone->getPrototype() !== null ? $zone->getRuinDigs() : 0,
            'ruin_bury' => $zone->getBuryCount(),
            'camp_levl' => $zone->getImprovementLevel(),
            'ruin_camp' => $zone->getPrototype()?->getCampingLevel(),
        ]);
    }

    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/set_zone_attribs', name: 'set_zone_attribs')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function set_zone_attribs(Town $town, JSONRequestParser  $parser): Response{
        $zone_id = $parser->get('zone_id', -1);
        $zone = $this->entity_manager->getRepository(Zone::class)->find($zone_id);

        if(!$zone || $zone->getTown() !== $town)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $target = $parser->get("target");
        $value = $parser->get_num('value', 0);

        switch ($target) {
            case 'zone':
                $zone->setDigs( max(0, $value) );
                break;
            case 'ruin':
                if (!$zone->getPrototype())
                    return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                $zone->setRuinDigs( max(0, $value) );
                break;
            case 'bury':
                if (!$zone->getPrototype())
                    return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                $zone->setBuryCount( max(0, $value) );
                break;
            case 'camp':
                $zone->setImprovementLevel( max(0, $value) );
                break;
            default: return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $this->clearTownCaches($town);
        $this->entity_manager->persist($zone);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @param TownRankingProxy $town_proxy
     * @param JSONRequestParser $parser
     * @param GameFactory $gameFactory
     * @param GenerateTownNameAction $townNameAction
     * @return Response
     */
    #[Route(path: 'api/manage/town/proxy/{id<\d+>}/relang', name: 'admin_town_town_lang_control', requirements: ['act' => '\d+'])]
    #[IsGranted('ROLE_CROW')]
    #[AdminLogProfile(enabled: true)]
    public function switch_town_lang(TownRankingProxy $town_proxy, JSONRequestParser $parser, GameFactory $gameFactory, GenerateTownNameAction $townNameAction): Response
    {
        $lang = $parser->get('lang');
        $rename = $parser->get( 'rename' );

        if ($lang !== ($town_proxy->getLanguage() ?? '') && !in_array( $lang, array_merge($this->generatedLangsCodes, [ 'multi' ]) ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($lang !== ($town_proxy->getLanguage() ?? '')) {
            $town_proxy->setLanguage( $lang );
            $town_proxy->getTown()?->setLanguage($lang);
        }

        if ($rename) {
            $old_name = $town_proxy->getName( );
            $schema = null;
            $name = ($townNameAction)( $lang, $schema );
            $town_proxy->setName( $name );
            $town_proxy->getTown()?->setName($name)?->setNameSchema($schema);
            $town_proxy->getTown()?->getForum()?->setTitle( $name );

            foreach ($town_proxy->getCitizens() as $citizen)
                $this->entity_manager->persist($this->crow_service->createPM_moderation( $citizen->getUser(), CrowService::ModerationActionDomainRanking, CrowService::ModerationActionTargetGameName, CrowService::ModerationActionEdit, $town_proxy, $old_name ));
        }

		if ($town_proxy->getTown() !== null)
        	$this->clearTownCaches($town_proxy->getTown());
        $this->entity_manager->persist($town_proxy);
        if ($town_proxy->getTown()) $this->entity_manager->persist($town_proxy->getTown());
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }
}
