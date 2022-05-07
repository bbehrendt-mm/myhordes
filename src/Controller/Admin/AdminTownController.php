<?php

namespace App\Controller\Admin;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Entity\ActionEventLog;
use App\Entity\BlackboardEdit;
use App\Entity\Building;
use App\Entity\BuildingPrototype;
use App\Entity\Citizen;
use App\Entity\CitizenEscortSettings;
use App\Entity\CitizenHome;
use App\Entity\CitizenHomePrototype;
use App\Entity\CitizenHomeUpgrade;
use App\Entity\CitizenHomeUpgradeCosts;
use App\Entity\CitizenHomeUpgradePrototype;
use App\Entity\CitizenProfession;
use App\Entity\CitizenRankingProxy;
use App\Entity\CitizenRole;
use App\Entity\CitizenStatus;
use App\Entity\CitizenVote;
use App\Entity\CitizenWatch;
use App\Entity\Complaint;
use App\Entity\ComplaintReason;
use App\Entity\CouncilEntry;
use App\Entity\EventActivationMarker;
use App\Entity\ExpeditionRoute;
use App\Entity\HeroicActionPrototype;
use App\Entity\Inventory;
use App\Entity\Item;
use App\Entity\ItemPrototype;
use App\Entity\Picto;
use App\Entity\PictoPrototype;
use App\Entity\RuinExplorerStats;
use App\Entity\SpecialActionPrototype;
use App\Entity\Town;
use App\Entity\TownRankingProxy;
use App\Entity\User;
use App\Entity\ZombieEstimation;
use App\Entity\Zone;
use App\Enum\ItemPoisonType;
use App\Response\AjaxResponse;
use App\Service\CrowService;
use App\Service\CitizenHandler;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\GazetteService;
use App\Service\InventoryHandler;
use App\Service\ItemFactory;
use App\Service\JSONRequestParser;
use App\Service\NightlyHandler;
use App\Service\RandomGenerator;
use App\Service\TownHandler;
use App\Service\ZoneHandler;
use App\Structures\BankItem;
use App\Structures\EventConf;
use App\Structures\TownConf;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * @Route("/",condition="request.isXmlHttpRequest()")
 * @GateKeeperProfile(allow_during_attack=true)
 */
class AdminTownController extends AdminActionController
{
    /**
     * @Route("jx/admin/town/list", name="admin_town_list")
     * @return Response
     */
    public function town_list(): Response
    {
        return $this->render('ajax/admin/towns/list.html.twig', $this->addDefaultTwigArgs('towns', [
            'towns' => $this->entity_manager->getRepository(Town::class)->findAll(),
            'citizen_stats' => $this->entity_manager->getRepository(Citizen::class)->getStatByLang()
        ]));
    }

    /**
     * @Route("jx/admin/town/list/old/{page}", name="admin_old_town_list", requirements={"page"="\d+"})
     * @param int $page The page we're viewing
     * @return Response
     */
    public function old_town_list($page = 1): Response
    {
        if ($page <= 0) $page = 1;

        // build the query for the doctrine paginator
        $query = $this->entity_manager->getRepository(TownRankingProxy::class)->createQueryBuilder('t')
            ->andWhere('t.end IS NOT NULL')
            ->orWhere('t.imported = 1')
            ->orderBy('t.id', 'ASC')
            ->getQuery();

        // Get the paginator
        $paginator = new Paginator($query);

        $pageSize = 20;
        $totalItems = count($paginator);
        $pagesCount = ceil($totalItems / $pageSize);

        return $this->render('ajax/admin/towns/old_towns_list.html.twig', $this->addDefaultTwigArgs('old_towns', [
            'towns' => $paginator
                ->getQuery()
                ->setFirstResult($pageSize * ($page - 1)) // set the offset
                ->setMaxResults($pageSize)
                ->getResult(),
            'currentPage' => $page,
            'pagesCount' => $pagesCount
        ]));
    }

    /**
     * @Route("api/admin/raventimes/log", name="admin_newspaper_log_controller")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function log_newspaper_api(JSONRequestParser $parser): Response {
        $town_id = $parser->get('town', -1);
        $town = $this->entity_manager->getRepository(Town::class)->find($town_id);
        return $this->renderLog((int)$parser->get('day', -1), $town, false, null, null);
    }

    protected function renderInventoryAsBank( Inventory $inventory ) {
        $qb = $this->entity_manager->createQueryBuilder();
        $qb
            ->select('i.id', 'c.label as l1', 'cr.label as l2', 'SUM(i.count) as n')->from('App:Item','i')
            ->where('i.inventory = :inv')->setParameter('inv', $inventory)
            ->groupBy('i.prototype', 'i.broken', 'i.poison')
            ->leftJoin('App:ItemPrototype', 'p', Join::WITH, 'i.prototype = p.id')
            ->leftJoin('App:ItemCategory', 'c', Join::WITH, 'p.category = c.id')
            ->leftJoin('App:ItemCategory', 'cr', Join::WITH, 'c.parent = cr.id')
            ->addOrderBy('c.ordering','ASC')
            ->addOrderBy('p.icon', 'DESC')
            ->addOrderBy('i.id', 'ASC');

        $data = $qb->getQuery()->getResult(AbstractQuery::HYDRATE_ARRAY);

        $final = [];
        $cache = [];

        foreach ($data as $entry) {
            $label = $entry['l2'] ?? $entry['l1'] ?? 'Sonstiges';
            if (!isset($final[$label])) $final[$label] = [];
            $final[$label][] = [ $entry['id'], $entry['n'] ];
            $cache[] = $entry['id'];
        }

        $item_list = $this->entity_manager->getRepository(Item::class)->findAllByIds($cache);
        foreach ( $final as $label => &$entries )
            $entries = array_map(function( array $entry ) use (&$item_list): BankItem { return new BankItem( $item_list[$entry[0]], $entry[1] ); }, $entries);

        return $final;
    }

    /**
     * @Route("jx/admin/town/{id<\d+>}/{tab?}", name="admin_town_explorer")
     * @param int $id
     * @param string|null $tab The tab we want to display
     * @param GazetteService $gazetteService
     * @return Response
     */
    public function town_explorer(int $id, ?string $tab, GazetteService $gazetteService): Response
    {
        /** @var Town $town */
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if ($town === null) $this->redirect($this->generateUrl('admin_town_list'));

        $explorables = [];

        foreach ($town->getZones() as $zone)
            /** @var Zone $zone */
            if ($zone->getPrototype() && $zone->getPrototype()->getExplorable()) {
                $explorables[$zone->getId()] = ['rz' => [], 'z' => $zone, 'x' => $zone->getExplorerStats(), 'ax' => $zone->activeExplorerStats()];
                if ($zone->activeExplorerStats()) $explorables[$zone->getId()]['axt'] = max(0, $zone->activeExplorerStats()->getTimeout()->getTimestamp() - time());
                $rz = $zone->getRuinZones();
                foreach ($rz as $r)
                    $explorables[$zone->getId()]['rz'][] = $r;
            }

        $pictoProtos = $this->entity_manager->getRepository(PictoPrototype::class)->findAll();
        usort($pictoProtos, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        $itemPrototypes = $this->entity_manager->getRepository(ItemPrototype::class)->findAll();
        usort($itemPrototypes, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'items'), $this->translator->trans($b->getLabel(), [], 'items'));
        });

        $citizenStati = $this->entity_manager->getRepository(CitizenStatus::class)->findAll();
        usort($citizenStati, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        $citizenRoles = $this->entity_manager->getRepository(CitizenRole::class)->findAll();

        usort($citizenRoles, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        $disabled_profs = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_DISABLED_JOBS, []);
        $professions = array_filter($this->entity_manager->getRepository( CitizenProfession::class )->findSelectable(),
            fn(CitizenProfession $p) => !in_array($p->getName(),$disabled_profs)
        );

        $complaints = [];
        $votes = [];
        $roles = [];

        /** @var CitizenRole $votableRole */
        foreach ($this->entity_manager->getRepository(CitizenRole::class)->findVotable() as $votableRole) {
            $votes[$votableRole->getId()] = [];
            $roles[$votableRole->getId()] = $votableRole;
        }

        foreach ($town->getCitizens() as $citizen) {
            $comp = $this->entity_manager->getRepository(Complaint::class)->findBy(['culprit' => $citizen]);
            if (count($comp) > 0)
                $complaints[$citizen->getUser()->getName()] = $comp;

            foreach ($roles as $roleId => $role) {
                /** @var CitizenVote $vote */
                $vote = $this->entity_manager->getRepository(CitizenVote::class)->findOneByCitizenAndRole($citizen, $role);
                if ($vote) {
                    if(isset($votes[$roleId][$vote->getVotedCitizen()->getUser()->getName()])) {
                        $votes[$roleId][$vote->getVotedCitizen()->getUser()->getName()][] = $vote->getAutor();
                    } else {
                        $votes[$roleId][$vote->getVotedCitizen()->getUser()->getName()] = [
                            $vote->getAutor()
                        ];
                    }
                }
            }
        }


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

        $all_complaints = array_map( fn(ActionEventLog $a) => [
            'on' => $a->getType() === ActionEventLog::ActionEventComplaintIssued,
            'from' => $a->getCitizen(),
            'to' => $this->entity_manager->getRepository(Citizen::class)->find($a->getOpt1()),
            'reason' => $this->entity_manager->getRepository(ComplaintReason::class)->find($a->getOpt2()),
            'time' => $a->getTimestamp()
        ], $this->entity_manager->getRepository(ActionEventLog::class)->findBy([
                              'type' => [ActionEventLog::ActionEventComplaintIssued,ActionEventLog::ActionEventComplaintRedacted],
                              'citizen' => $town->getCitizens()->getValues(),
                          ], ['timestamp' => 'DESC']));

        $langs = [];
        $langs_alive = [];
        foreach ($town->getCitizens() as $citizen) {
            $lang = $citizen->getUser()->getLanguage() ?? 'multi';
            if (!isset($langs[$lang]))
                $langs[$lang] = $langs_alive[$lang] = 0;
            $langs[$lang]++;
            if ($citizen->getActive()) $langs_alive[$lang]++;
        }

        return $this->render('ajax/admin/towns/explorer.html.twig', $this->addDefaultTwigArgs(null, array_merge([
            'town' => $town,
            'conf' => $this->conf->getTownConfiguration($town),
            'explorables' => $explorables,
            'log' => $this->renderLog(-1, $town, false)->getContent(),
            'day' => $town->getDay(),
            'bank' => $this->renderInventoryAsBank($town->getBank()),
            'itemPrototypes' => $itemPrototypes,
            'pictoPrototypes' => $pictoProtos,
            'citizenStati' => $citizenStati,
            'citizenRoles' => $citizenRoles,
            'citizenProfessions' => $professions,
            'tab' => $tab,
            'complaints' => $complaints,
            'all_complaints' => $all_complaints,
            'dictBuildings' => $dict,
            'rootBuildings' => $root,
            'availBuldings' => $inTown,
            'votes' => $votes,
            'gazette' => $gazetteService->renderGazette( $town, $town->getDay(), true),
            'council' => array_map( fn(CouncilEntry $c) => [$gazetteService->parseCouncilLog( $c ), $c->getCitizen()], array_filter( $this->entity_manager->getRepository(CouncilEntry::class)->findBy(['town' => $town, 'day' => $town->getDay()], ['ord' => 'ASC']),
                fn(CouncilEntry $c) => ($c->getTemplate() && $c->getTemplate()->getText() !== null)
            )),
            'blackboards' => $this->entity_manager->getRepository(BlackboardEdit::class)->findBy([ 'town' => $town ], ['time' => 'DESC'], 100),
            'events' => $this->conf->getAllEvents(),
            'current_event' => $this->conf->getCurrentEvents($town),
            'citizen_langs' => $langs,
            'citizen_langs_alive' => $langs_alive,
            'langs' => ['de','en','fr','es','multi']
        ], $this->get_map_blob($town))));
    }

    /**
     * @Route("jx/admin/town/{id<\d+>}/gazette/{day<\d+>}", name="admin_town_explorer_gazette")
     * @param int $id
     * @param int $day
     * @param GazetteService $gazetteService
     * @return Response
     */
    public function api_explore_gazette(int $id, int $day, GazetteService $gazetteService): Response {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if ($town === null || $day > $town->getDay()) return AjaxResponse::error( ErrorHelper::ErrorInvalidRequest );

        return $this->render('ajax/game/gazette_widget.html.twig', [
            'gazette' => $gazetteService->renderGazette( $town, $day, true ),
            'council' => array_map( fn(CouncilEntry $c) => [$gazetteService->parseCouncilLog( $c ), $c->getCitizen()], array_filter( $this->entity_manager->getRepository(CouncilEntry::class)->findBy(['town' => $town, 'day' => $day], ['ord' => 'ASC']),
                fn(CouncilEntry $c) => ($c->getTemplate() && $c->getTemplate()->getText() !== null)
            ))
        ]);
    }

    public function get_map_blob(Town $town): array
    {
        $zones = [];
        $range_x = [PHP_INT_MAX, PHP_INT_MIN];
        $range_y = [PHP_INT_MAX, PHP_INT_MIN];
        $zones_classes = [];

        $soul_zones_ids = array_map(function (Zone $z) {
            return $z->getId();
        }, $this->zone_handler->getSoulZones($town));

        foreach ($town->getZones() as $zone) {
            $x = $zone->getX();
            $y = $zone->getY();

            $range_x = [min($range_x[0], $x), max($range_x[1], $x)];
            $range_y = [min($range_y[0], $y), max($range_y[1], $y)];

            if (!isset($zones[$x])) $zones[$x] = [];
            $zones[$x][$y] = $zone;

            if (!isset($zones_attributes[$x])) $zones_attributes[$x] = [];
            $zones_classes[$x][$y] = $this->zone_handler->getZoneClasses(
                $town,
                $zone,
                null,
                in_array($zone->getId(), $soul_zones_ids),
                true
            );
        }

        return [
            'map_data' => [
                'zones' => $zones,
                'zones_classes' => $zones_classes,
                'town_devast' => $town->getDevastated(),
                'routes' => $this->entity_manager->getRepository(ExpeditionRoute::class)->findByTown($town),
                'pos_x' => 0,
                'pos_y' => 0,
                'map_x0' => $range_x[0],
                'map_x1' => $range_x[1],
                'map_y0' => $range_y[0],
                'map_y1' => $range_y[1],
            ]
        ];
    }

    /**
     * @Route("jx/admin/town/old/{id<\d+>}/{tab?}", name="admin_old_town_explorer")
     * @param int $id
     * @param string|null $tab the tab we want to display
     * @return Response
     */
    public function old_town_explorer(int $id, ?string $tab): Response
    {
        $town = $this->entity_manager->getRepository(TownRankingProxy::class)->find($id);
        if ($town === null) $this->redirect($this->generateUrl('admin_old_town_list'));

        $pictoProtos = $this->entity_manager->getRepository(PictoPrototype::class)->findAll();
        usort($pictoProtos, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        return $this->render('ajax/admin/towns/old_town_explorer.html.twig', $this->addDefaultTwigArgs('old_explorer', [
            'town' => $town,
            'day' => $town->getDays(),
            'pictoPrototypes' => $pictoProtos,
            'tab' => $tab
        ]));
    }

    /**
     * @Route("api/admin/town/{id}/do/{action}", name="admin_town_manage", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @param int $id The ID of the town
     * @param string $action The action to perform
     */
    public function town_manager(int $id, string $action, ItemFactory $itemFactory, RandomGenerator $random, NightlyHandler $night, GameFactory $gameFactory, CrowService $crowService, KernelInterface $kernel, JSONRequestParser $parser, TownHandler $townHandler, GameProfilerService $gps): Response
    {
        /** @var Town $town */
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ((str_starts_with($action, 'dbg_') || in_array($action, ['ex_inf'])) && $kernel->getEnvironment() !== 'dev')
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (in_array($action, [
                'release', 'quarantine', 'advance', 'nullify', 'pw_change',
                'ex_del', 'ex_co+', 'ex_co-', 'ex_ref', 'ex_inf', 'dice_name',
                'dbg_fill_town', 'dbg_fill_bank', 'dgb_empty_bank', 'dbg_unlock_bank', 'dbg_hydrate', 'dbg_disengage', 'dbg_engage',
                'dbg_set_well', 'dbg_unlock_buildings', 'dbg_map_progress', 'dbg_map_zombie_set', 'dbg_adv_days',
                'dbg_set_attack', 'dbg_toggle_chaos', 'dbg_toggle_devas', 'dbg_enable_stranger'
            ]) && !$this->isGranted('ROLE_ADMIN'))
            return AjaxResponse::error(ErrorHelper::ErrorPermissionError);

        $this->logger->invoke("[town_manager] Admin <info>{$this->getUser()->getName()}</info> did the action <info>$action</info> in the town <info>{$town->getName()}</info> (id: {$town->getId()})");

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
            case 'dice_name':
                $old_name = $town->getName();
                $new_name = $gameFactory->createTownName( $town->getLanguage() );
                $town->setName( $new_name );
                $town->getRankingEntry()->setName( $new_name );
                $this->entity_manager->persist($town);
                $this->entity_manager->persist($town->getRankingEntry());
                foreach ($town->getCitizens() as $citizen)
                    $this->entity_manager->persist($this->crow_service->createPM_moderation( $citizen->getUser(), CrowService::ModerationActionDomainRanking, CrowService::ModerationActionTargetGameName, CrowService::ModerationActionEdit, $town, $old_name ));

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

                $disabled_profs = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_DISABLED_JOBS, []);
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

                    $gps->recordCitizenJoined($citizen, 'debug');
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
                    $possible = array_filter( $this->entity_manager->getRepository(BuildingPrototype::class)->findProspectivePrototypes( $town ), fn(BuildingPrototype $p) => $p->getBlueprint() === null || $p->getBlueprint() < 5 );
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
                $this->zone_handler->dailyZombieSpawn( $town, 1, ZoneHandler::RespawnModeAuto, $d );
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

            case 'dbg_enable_stranger':
                $gameFactory->enableStranger( $town );
                break;

            default:
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        try {
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/set_event", name="admin_town_set_event", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @param int $id The ID of the town
     * @param JSONRequestParser $parser
     * @param TownHandler $townHandler
     * @return Response
     */
    public function admin_town_set_event(int $id, JSONRequestParser $parser, TownHandler $townHandler): Response {
        /** @var Town $town */
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

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

        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/set_lang", name="admin_town_set_lang", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @param int $id The ID of the town
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function admin_town_set_lang(int $id, JSONRequestParser $parser): Response {
        /** @var Town $town */
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $newLang = $parser->get('param');
        if (!in_array( $newLang, [ 'de', 'es', 'en', 'fr', 'multi' ] ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $town->setLanguage( $newLang );
        $town->getRankingEntry()->setLanguage( $newLang );

        $this->entity_manager->persist($town);
        $this->entity_manager->persist($town->getRankingEntry());
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/new", name="admin_new_town")
     * @AdminLogProfile(enabled=true)
     * @param JSONRequestParser $parser
     * @param GameFactory $gameFactory
     * @param TownHandler $townHandler
     * @param GameProfilerService $gps
     * @return Response
     */
    public function add_default_town( JSONRequestParser $parser, GameFactory $gameFactory, TownHandler $townHandler, GameProfilerService $gps): Response {

        $town_name = $parser->get('name', null) ?: null;
        $town_type = $parser->get('type', '');
        $town_lang = $parser->get('lang', 'de');

        if (!in_array($town_lang, ['de','en','es','fr','multi']))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $this->logger->invoke("[add_default_town] Admin <info>{$this->getUser()->getName()}</info> created a <info>$town_lang</info> town (custom name: '<info>$town_name</info>'), which is of type <info>$town_type</info>");

        $town = $gameFactory->createTown($town_name, $town_lang, null, $town_type);
        if (!$town) {
            $this->logger->invoke("Town creation failed!");
            return AjaxResponse::error(ErrorHelper::ErrorInternalError);
        }

        try {
            $this->entity_manager->persist( $town );
            $this->entity_manager->flush();
            $gps->recordTownCreated( $town, $this->getUser(), 'manual' );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }

        $current_events = $this->conf->getCurrentEvents();
        $current_event_names = array_map(fn(EventConf $e) => $e->name(), array_filter($current_events, fn(EventConf $e) => $e->active()));
        if (!empty($current_event_names)) {
            if (!$townHandler->updateCurrentEvents($town, $current_events)) {
                $this->entity_manager->clear();
            } else {
                $this->entity_manager->persist($town);
                $this->entity_manager->flush();
            }
        }

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/item", name="admin_town_item", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Add or remove an item from the bank
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @param ItemFactory $itemFactory
     * @return Response
     */
    public function town_item_action(int $id, JSONRequestParser $parser, InventoryHandler $handler, ItemFactory $itemFactory): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $item_id = $parser->get('item');
        $change = $parser->get('change');
        $qty = $parser->get('qty', 1);
        if($qty <= 0)
            $qty = 1;

        $item = $this->entity_manager->getRepository(Item::class)->find($item_id);

        if ($change == 'add') {
            for($i = 0 ; $i < $qty ; $i++)
                $handler->forceMoveItem($town->getBank(), $itemFactory->createItem($item->getPrototype()->getName()));
        } else {
            $handler->forceRemoveItem($item, $qty);
        }

        $this->entity_manager->persist($town->getBank());
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/teleport", name="admin_teleport_citizen", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Add or remove an item from the bank
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @param ZoneHandler $handler
     * @param TownHandler $townHandler
     * @return Response
     */
    public function teleport_citizen(int $id, JSONRequestParser $parser, ZoneHandler $handler, TownHandler $townHandler): Response
    {
        /** @var Town $town */
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $targets = $parser->get_array('targets');
        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $to = $parser->get('to');
        if ($to !== 'town' && ( !is_array($to) || count($to) !== 2 ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $target_zone = $to === 'town' ? null : $this->entity_manager->getRepository(Zone::class)->findOneByPosition($town,$to[0],$to[1]);
        if ($target_zone === null && $to !== 'town') return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $old_zones = [];
        $cp_target_zone = !$target_zone || $handler->check_cp($target_zone);

        $escort = $parser->get('escort', false);

        foreach (array_unique($targets) as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);

            if (!$citizen || $citizen->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($citizen->getZone() === $target_zone) continue;

            $movers = [$citizen];

            if($escort) {
                foreach ($citizen->getValidLeadingEscorts() as $escort)
                    $movers[] = $escort->getCitizen();
            } else {
                foreach ($citizen->getValidLeadingEscorts() as $escort) {
                    $escort->getCitizen()->getEscortSettings()->setLeader(null);
                    $this->entity_manager->persist($escort->getCitizen());
                }
            }

            foreach ($movers as $mover){
                if ($mover->getZone()) {
                    if (!isset($old_zones[$mover->getZone()->getId()]))
                        $old_zones[$mover->getZone()->getId()] = [$mover->getZone(), $handler->check_cp( $mover->getZone() )];

                    if ($dig_timer = $mover->getCurrentDigTimer()) {
                        $dig_timer->setPassive(true);
                        $this->entity_manager->persist( $dig_timer );
                    }

                    $mover->getZone()->removeCitizen( $mover );
                }

                if ($target_zone) $target_zone->addCitizen( $mover );
                $this->entity_manager->persist($mover);
            }
        }

        foreach ($old_zones as $old_zone_data) {
            $handler->handleCitizenCountUpdate($old_zone_data[0],$old_zone_data[1]);
            $this->entity_manager->persist($old_zone_data[0]);
        }

        if ($target_zone) {
            $upgraded_map = $townHandler->getBuilding($town,'item_electro_#00', true);
            $target_zone
                ->setDiscoveryStatus( Zone::DiscoveryStateCurrent )
                ->setZombieStatus( max($upgraded_map ? Zone::ZombieStateExact : Zone::ZombieStateEstimate, $target_zone->getZombieStatus() ) );
            $handler->handleCitizenCountUpdate($target_zone,$cp_target_zone);
            $this->entity_manager->persist($target_zone);
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/alias", name="admin_alias_citizen", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Change the Alias of a citizen
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @param ZoneHandler $handler
     * @param TownHandler $townHandler
     * @return Response
     */
    public function alias_citizen(int $id, JSONRequestParser $parser, ConfMaster $cf, TownHandler $townHandler): Response
    {
        /** @var Town $town */
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $alias = $parser->trimmed('alias');
        $targets = $parser->get_array('targets');
        if ($alias != null && !$alias)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        if (count($targets) > 1)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var Citizen $citizen */
        $citizen = $this->entity_manager->getRepository(Citizen::class)->find($targets[0]);

        $town_conf = $cf->getTownConfiguration($citizen->getTown());

        $citizen_alias_active = $town_conf->get(TownConf::CONF_FEATURE_CITIZEN_ALIAS, false);

        if(!$citizen_alias_active)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if($alias == null) {
            $citizen->setAlias(null);
        } else {
            $apply_result = $this->citizen_handler->applyAlias($citizen, $alias);
            if($apply_result == -1) {
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
            }
        }
        
        try {
            $this->entity_manager->persist( $citizen );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
        }
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/spawn_item", name="admin_spawn_item", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Add or remove an item from the bank
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @param InventoryHandler $handler
     * @param ItemFactory $itemFactory
     * @return Response
     */
    public function spawn_item(int $id, JSONRequestParser $parser, InventoryHandler $handler, ItemFactory $itemFactory): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $prototype_id = $parser->get('prototype');
        $number = $parser->get_int('number');
        $targets = $parser->get_array('targets');

        $conf = $parser->get_array('conf');
        $poison = $conf['poison'] ?? false;
        if ($poison > 1) $poison = ItemPoisonType::from( $poison );
        $broken = $conf['broken'] ?? false;
        $essential = $conf['essential'] ?? false;
        $hidden = $conf['hidden'] ?? false;

        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var ItemPrototype $itemPrototype */
        if ($prototype_id == "all") {
            $itemPrototype = $this->entity_manager->getRepository(ItemPrototype::class)->findAll();
        } else {
            $itemPrototype = $this->entity_manager->getRepository(ItemPrototype::class)->find($prototype_id);
            if (!$itemPrototype) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        if (!is_array($itemPrototype))
            $itemPrototype = [$itemPrototype];

        /** @var Inventory[] $inventories */
        $inventories = [];

        foreach (array_unique($targets['chest'] ?? []) as $target) {
            /** @var CitizenHome $home */
            $home = $this->entity_manager->getRepository(CitizenHome::class)->find($target);
            if (!$home || $home->getCitizen()->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories[] = $home->getChest();
        }

        foreach (array_unique($targets['rucksack'] ?? []) as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories[] = $citizen->getInventory();
        }

        foreach (array_unique($targets['zone'] ?? []) as $target) {
            /** @var Zone $zone */
            $zone = $this->entity_manager->getRepository(Zone::class)->find($target);
            if (!$zone || $zone->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories[] = $zone->getFloor();
        }

        foreach (array_unique($targets['bank'] ?? []) as $target) {
            if ($target !== $town->getId()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $inventories[] = $town->getBank();
        }

        foreach ($inventories as $inventory) {
            for ($i = 0; $i < $number; $i++) {
                foreach ($itemPrototype as $proto) {
                    $handler->forceMoveItem($inventory, $itemFactory->createItem($proto->getName(), $broken, $poison)->setEssential($essential)->setHidden($hidden && $inventory->getZone()));
                }

            }
            $this->entity_manager->persist($inventory);
        }

        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/get_zone_infos", name="get_zone_infos", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Returns the floor of a given zone
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function get_zone_infos(int $id, JSONRequestParser  $parser): Response{
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $zone_id = $parser->get('zone_id', -1);
        $zone = $this->entity_manager->getRepository(Zone::class)->find($zone_id);

        if(!$zone || $zone->getTown() !== $town)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $view = $this->renderView("ajax/game/inventory.html.twig", [
            'size' => 0,
            'items' => $zone->getFloor()->getItems()
        ]);

        return AjaxResponse::success(true, [
            'view' => $view,
            'zone_digs' => $zone->getDigs(),
            'ruin_digs' => $zone->getPrototype() !== null ? $zone->getRuinDigs() : 0,
            'ruin_bury' => $zone->getBuryCount(),
            'camp_levl' => $zone->getImprovementLevel(),
            'ruin_camp' => $zone->getPrototype()?->getCampingLevel(),
            'zone_log' => $this->renderView("ajax/admin/towns/log.html.twig", [
                'additional_log_params' => [ 'zone_id' => $zone_id ],
                'log_content' => $this->renderLog($parser->has('day') ? $parser->get('day') : $town->getDay(), $town, $zone)->getContent(),
                'log_source' => $this->urlGenerator->generate('get_zone_info_log', ['id' => $id]),
                'day' => $parser->has('day') ? $parser->get('day') : $town->getDay()
            ]),
        ]);
    }

    /**
     * @Route("jx/admin/town/{id<\d+>}/get_zone_info_log", priority=1, name="get_zone_info_log")
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Returns the floor of a given zone
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function get_zone_info_log(int $id, JSONRequestParser  $parser): Response {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return new Response('');

        $zone_id = $parser->get('zone_id', -1);
        $zone = $this->entity_manager->getRepository(Zone::class)->find($zone_id);

        if(!$zone || $zone->getTown() !== $town)
            return new Response('');

        return $this->renderLog($parser->has('day') ? $parser->get('day') : $town->getDay(), $town, $zone);
    }

    /**
     * @Route("api/admin/town/{id}/get_citizen_infos", name="get_citizen_infos", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Returns the floor of a given zone
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function get_citizen_infos(int $id, JSONRequestParser  $parser): Response{
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $citizen_id = $parser->get('citizen_id', -1);
        $citizen = $this->entity_manager->getRepository(Citizen::class)->find($citizen_id);

        if(!$citizen || $citizen->getTown() !== $town)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $rucksack = $this->renderView("ajax/game/inventory.html.twig", [
            'size' => $this->inventory_handler->getSize( $citizen->getInventory() ),
            'items' => $citizen->getInventory()->getItems()
        ]);

        $chest = $this->renderView("ajax/game/inventory.html.twig", [
            'size' => $this->inventory_handler->getSize( $citizen->getHome()->getChest() ),
            'items' => $citizen->getHome()->getChest()->getItems()
        ]);

        $pictos = $this->renderView("ajax/admin/towns/distinctions.html.twig", [
            'pictos' => $this->entity_manager->getRepository(Picto::class)->findPictoByUserAndTown($citizen->getUser(), $citizen->getTown()),
        ]);

        return AjaxResponse::success(true, [
            'rucksack' => $rucksack,
            'chest' => $chest,
            'pictos' => $pictos,
        ]);
    }

    /**
     * @Route("api/admin/town/{id}/set_zone_attribs", name="set_zone_attribs", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Returns the floor of a given zone
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @return Response
     */
    public function set_zone_attribs(int $id, JSONRequestParser  $parser): Response{
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

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

        $this->entity_manager->persist($zone);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/modify_prof", name="admin_modify_profession", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Changes the profession of citizens
     * @param int $id Town ID
     * @param JSONRequestParser $parser
     * @param CitizenHandler $handler
     * @return Response
     */
    public function modify_profession(int $id, JSONRequestParser $parser, CitizenHandler $handler): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $pro_id = $parser->get_int('profession');
        $targets = $parser->get_array('targets');

        $disabled_profs = $this->conf->getTownConfiguration($town)->get(TownConf::CONF_DISABLED_JOBS, []);

        if (empty($targets))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var CitizenProfession $profession */
        $profession = $this->entity_manager->getRepository(CitizenProfession::class)->find($pro_id);
        if (!$profession || $profession->getName() === CitizenProfession::DEFAULT || in_array($profession->getName(), $disabled_profs))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var Inventory[] $inventories */
        $inventories = [];

        foreach (array_unique($targets) as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town)
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($citizen->getProfession() !== $profession) {
                $handler->applyProfession($citizen, $profession);
                $this->entity_manager->persist($citizen);
            }
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{tid}/event-tag/{act}", name="admin_town_event_tag_control", requirements={"tid"="\d+","act"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * @param int $tid
     * @param int $act
     * @return Response
     */
    public function ranking_event_toggle_town(int $tid, int $act): Response
    {
        $town_proxy = $this->entity_manager->getRepository(TownRankingProxy::class)->find($tid);
        if (!$town_proxy) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $town_proxy->setEvent( $act !== 0 );
        $this->entity_manager->persist($town_proxy);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{tid}/unrank/{act}", name="admin_town_town_ranking_control", requirements={"tid"="\d+","act"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * @param int $tid
     * @param int $act
     * @return Response
     */
    public function ranking_toggle_town(int $tid, int $act): Response
    {
        $town_proxy = $this->entity_manager->getRepository(TownRankingProxy::class)->find($tid);
        if (!$town_proxy) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $town_proxy->setDisabled( $act !== 0 );
        $this->entity_manager->persist($town_proxy);
        $this->entity_manager->flush();

        foreach ($town_proxy->getCitizens() as $citizen) {
            $this->entity_manager->persist($citizen->getUser()
                ->setSoulPoints( $this->user_handler->fetchSoulPoints( $citizen->getUser(), false ) )
                ->setImportedSoulPoints( $this->user_handler->fetchImportedSoulPoints( $citizen->getUser() ) )
            );
            foreach ($this->entity_manager->getRepository(Picto::class)->findNotPendingByUserAndTown($citizen->getUser(), $town_proxy) as $picto)
                $this->entity_manager->persist($picto->setDisabled($act !== 0 || $citizen->getDisabled()));
        }


        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{tid}/relang", name="admin_town_town_lang_control", requirements={"tid"="\d+","act"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * @param int $tid
     * @param JSONRequestParser $parser
     * @param GameFactory $gameFactory
     * @return Response
     */
    public function switch_town_lang(int $tid, JSONRequestParser $parser, GameFactory $gameFactory): Response
    {
        /** @var TownRankingProxy $town_proxy */
        $town_proxy = $this->entity_manager->getRepository(TownRankingProxy::class)->find($tid);
        if (!$town_proxy || $town_proxy->getImported()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $lang = $parser->get('lang');
        $rename = $parser->get( 'rename' );

        if ($lang !== ($town_proxy->getLanguage() ?? '') && !in_array( $lang, ['de','en','fr','es','multi'] ))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if ($lang !== ($town_proxy->getLanguage() ?? '')) {
            $town_proxy->setLanguage( $lang );
            $town_proxy->getTown()?->setLanguage($lang);
        }

        if ($rename) {
            $old_name = $town_proxy->getName( );
            $name = $gameFactory->createTownName( $lang );
            $town_proxy->setName( $name );
            $town_proxy->getTown()?->setName($name);

            foreach ($town_proxy->getCitizens() as $citizen)
                $this->entity_manager->persist($this->crow_service->createPM_moderation( $citizen->getUser(), CrowService::ModerationActionDomainRanking, CrowService::ModerationActionTargetGameName, CrowService::ModerationActionEdit, $town_proxy, $old_name ));
        }

        $this->entity_manager->persist($town_proxy);
        if ($town_proxy->getTown()) $this->entity_manager->persist($town_proxy->getTown());
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{tid}/unrank_single/{cid}/{act}", name="admin_town_citizen_ranking_control", requirements={"tid"="\d+","cid"="\d+","act"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * @param int $tid
     * @param int $cid
     * @param int $act
     * @return Response
     */
    public function ranking_toggle_citizen(int $tid, int $cid, int $act): Response
    {
        $town_proxy = $this->entity_manager->getRepository(TownRankingProxy::class)->find($tid);
        if (!$town_proxy) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $citizen_proxy = $this->entity_manager->getRepository(CitizenRankingProxy::class)->find($cid);
        if (!$citizen_proxy) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (!$town_proxy->getCitizens()->contains($citizen_proxy))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $citizen_proxy->setDisabled( $act !== 0 );
        if (!$citizen_proxy->getDisabled() && $citizen_proxy->getResetMarker()) {
            $this->entity_manager->remove( $citizen_proxy->getResetMarker() );
            $citizen_proxy->setResetMarker(null);
        }
        $this->entity_manager->persist($citizen_proxy);
        $this->entity_manager->flush();

        $this->entity_manager->persist($citizen_proxy->getUser()
            ->setSoulPoints( $this->user_handler->fetchSoulPoints( $citizen_proxy->getUser(), false ) )
            ->setImportedSoulPoints( $this->user_handler->fetchImportedSoulPoints( $citizen_proxy->getUser() ) )
        );
        foreach ($this->entity_manager->getRepository(Picto::class)->findNotPendingByUserAndTown($citizen_proxy->getUser(), $town_proxy) as $picto)
            $this->entity_manager->persist($picto->setDisabled($act !== 0 || $town_proxy->getDisabled()));

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/picto/give", name="admin_town_give_picto", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Give picto to all citizens of a town
     * @param int $id Town ID
     * @param JSONRequestParser $parser The Request Parser
     * @return Response
     */
    public function town_give_picto(int $id, JSONRequestParser $parser): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        $townRanking = $this->entity_manager->getRepository(TownRankingProxy::class)->find($id);
        /** @var Town $town */
        if (!$town && !$townRanking) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        if (!$town && $townRanking)
            $town = $townRanking;

        $prototype_id = $parser->get('prototype');
        $number = $parser->get('number', 1);

        /** @var PictoPrototype $pictoPrototype */
        $pictoPrototype = $this->entity_manager->getRepository(PictoPrototype::class)->find($prototype_id);

        foreach ($town->getCitizens() as $citizen) {
            /** @var Citizen $citizen */
            // if(!$citizen->getAlive()) continue;

            $picto = $this->entity_manager->getRepository(Picto::class)->findByUserAndTownAndPrototype($citizen->getUser(), $town, $pictoPrototype);
            if (null === $picto) {
                $picto = new Picto();
                $picto->setPrototype($pictoPrototype)
                    ->setPersisted(2)
                    ->setUser($citizen->getUser());
                if (is_a($town, Town::class))
                    $picto->setOld($town->getSeason() === null)->setTown($town)->setDisabled( $town->getRankingEntry()->getDisabled() || $citizen->getRankingEntry()->getDisabled() );
                else
                    $picto->setTownEntry($town)->setDisabled( $town->getDisabled() || $citizen->getRankingEntry()->getDisabled() );
                $citizen->getUser()->addPicto($picto);
                $this->entity_manager->persist($citizen->getUser());
            }

            $picto->setCount($picto->getCount() + $number);

            $this->entity_manager->persist($picto);
        }

        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/home/manage", name="admin_town_manage_home", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Give or take status from selected citizens of a town
     * @param int $id Town ID
     * @param JSONRequestParser $parser The Request Parser
     * @param CitizenHandler $handler
     * @return Response
     */
    public function town_manage_home(int $id, JSONRequestParser $parser, CitizenHandler $handler): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $target   = $parser->get('target');
        $citizens = $parser->get_array('citizen');
        $dif      = $parser->get_int('dif', 0);
        $t_id     = $parser->get_int('id', -1);

        if ($dif === 0) return AjaxResponse::success();

        foreach ($citizens as $cid) {

            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($cid);
            if (!$citizen || $citizen->getTown() !== $town || !$citizen->getAlive()) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if (!$citizen->getHome()->getPrototype()->getAllowSubUpgrades() && in_array($target, ['proto','sub']))
                return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            switch ($target) {

                case 'home':
                    $new_proto = $this->entity_manager->getRepository(CitizenHomePrototype::class)->findOneByLevel( $citizen->getHome()->getPrototype()->getLevel() + $dif );
                    if (!$new_proto) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $citizen->getHome()->setPrototype( $new_proto );
                    $this->entity_manager->persist($citizen->getHome());
                    break;

                case 'sub':
                    $upgrade = $this->entity_manager->getRepository(CitizenHomeUpgrade::class)->find($t_id);
                    if ($upgrade === null || $upgrade->getHome() !== $citizen->getHome())
                        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    if ($upgrade->getLevel() + $dif <= 0) {
                        $citizen->getHome()->removeCitizenHomeUpgrade($upgrade);
                        $upgrade->setHome(null);
                        $this->entity_manager->persist($citizen->getHome());
                        $this->entity_manager->remove($upgrade);
                    } else {
                        $level_proto = $this->entity_manager->getRepository(CitizenHomeUpgradeCosts::class)->findOneBy(['prototype' => $upgrade->getPrototype(), 'level' => $upgrade->getLevel() + $dif]);

                        if ($level_proto === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
                        $this->entity_manager->persist($upgrade->setLevel( $upgrade->getLevel() + $dif ));
                    }

                    break;

                case 'proto':
                    $upgrade_proto = $this->entity_manager->getRepository(CitizenHomeUpgradePrototype::class)->find($t_id);
                    if ($upgrade_proto === null)
                        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    foreach ($citizen->getHome()->getCitizenHomeUpgrades() as $upgrade) if ($upgrade->getPrototype() === $upgrade_proto)
                        return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $level_proto = $this->entity_manager->getRepository(CitizenHomeUpgradeCosts::class)->findOneBy(['prototype' => $upgrade_proto, 'level' => $dif]);

                    if ($level_proto === null) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                    $new_upgrade = (new CitizenHomeUpgrade())->setLevel($dif)->setPrototype($upgrade_proto);
                    $citizen->getHome()->addCitizenHomeUpgrade($new_upgrade);

                    $this->entity_manager->persist($citizen->getHome());
                    $this->entity_manager->persist($new_upgrade);
                    break;

                default: return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
            }
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/status/manage", name="admin_town_manage_status", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Give or take status from selected citizens of a town
     * @param int $id Town ID
     * @param JSONRequestParser $parser The Request Parser
     * @param CitizenHandler $handler
     * @return Response
     */
    public function town_manage_status(int $id, JSONRequestParser $parser, CitizenHandler $handler): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $status_id = $parser->get_int('status');
        $targets = $parser->get_array('targets', []);

        $control = $parser->get_int('control', 0) > 0;

        /** @var CitizenStatus $citizenStatus */
        $citizenStatus = $this->entity_manager->getRepository(CitizenStatus::class)->find($status_id);
        if (!$citizenStatus) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($control) $this->citizen_handler->inflictStatus( $citizen, $citizenStatus );
            else $this->citizen_handler->removeStatus( $citizen, $citizenStatus );

            $this->entity_manager->persist($citizen);
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    private function town_manage_pseudo_role(Town $town, JSONRequestParser $parser, TownHandler $townHandler): Response {
        $targets = $parser->get_array('targets');
        $control = $parser->get_int('control', 0) > 0;

        $citizens = [];
        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            $citizens[] = $citizen;
        }

        switch ($parser->get('role')) {

            case '_ban_':
                foreach ($citizens as $citizen) {
                    $citizen->setBanished($control);
                    $this->entity_manager->persist($citizen);
                }
                break;
            case '_esc_':
                $c1 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_hide']);
                $c2 = $this->entity_manager->getRepository(CitizenStatus::class)->findOneBy(['name' => 'tg_tomb']);

                foreach ($citizens as $citizen) {
                    if (!$citizen->getZone() || !$citizen->getAlive() || $citizen->activeExplorerStats() || $citizen->getStatus()->contains($c1) || $citizen->getStatus()->contains($c2)) continue;

                    if (!$control) {
                        if ($citizen->getEscortSettings()) $this->entity_manager->remove($citizen->getEscortSettings());
                        $citizen->setEscortSettings(null);

                    } elseif (!$citizen->getEscortSettings())
                        $citizen->setEscortSettings((new CitizenEscortSettings())->setCitizen($citizen)->setAllowInventoryAccess(true)->setForceDirectReturn(false));
                    else $citizen->getEscortSettings()->setAllowInventoryAccess(true)->setForceDirectReturn(false);
                    $this->entity_manager->persist($citizen);
                }
                break;
            case '_nw_':
                $watchers = $this->entity_manager->getRepository(CitizenWatch::class)->findCurrentWatchers($town);
                foreach ($citizens as $citizen) {
                    $activeCitizenWatcher = null;

                    foreach ($watchers as $watcher)
                        if ($watcher->getCitizen() === $citizen){
                            $activeCitizenWatcher = $watcher;
                            break;
                        }

                    if ($control) {
                        if ($activeCitizenWatcher) continue;
                        $citizenWatch = (new CitizenWatch())->setCitizen($citizen)->setDay($town->getDay());
                        $town->addCitizenWatch($citizenWatch);
                        $this->entity_manager->persist($citizenWatch);
                    } else {
                        if ($activeCitizenWatcher === null) continue;
                        $town->removeCitizenWatch($activeCitizenWatcher);
                        $citizen->removeCitizenWatch($activeCitizenWatcher);
                        $this->entity_manager->remove($activeCitizenWatcher);
                    }

                    $this->entity_manager->persist($citizen);
                }
                break;
            case '_sh_':
                $armag_day   = $this->entity_manager->getRepository(SpecialActionPrototype::class)->findOneBy(['name' => "special_armag_d"]);
                $armag_night = $this->entity_manager->getRepository(SpecialActionPrototype::class)->findOneBy(['name' => "special_armag_n"]);

                foreach ($this->entity_manager->getRepository(HeroicActionPrototype::class)->findAll() as $heroic_action)
                    foreach ($citizens as $citizen) {
                        $citizen->addHeroicAction( $heroic_action );
                        $this->citizen_handler->removeStatus($citizen,'tg_hero');

                        $citizen->addSpecialAction($armag_day);
                        $citizen->addSpecialAction($armag_night);

                        $this->entity_manager->persist( $citizen );
                    }
                break;
            case '_wt_':
                if (!$townHandler->getBuilding($town,'item_tagger_#00'))
                    return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

                /** @var ZombieEstimation $est */
                $est = $this->entity_manager->getRepository(ZombieEstimation::class)->findOneBy(['town' => $town, 'day' => $town->getDay()]);
                if (!$est) return AjaxResponse::error( ErrorHelper::ErrorInternalError );

                foreach ($citizens as $citizen) {
                    if (!$control)
                        $est->removeCitizen($citizen);
                    else $est->addCitizen($citizen);
                }

                $this->entity_manager->persist($est);

                break;
            case '_rst_':
                if ($control)
                    foreach ($citizens as $citizen) {
                        $locked = $citizen->hasStatus( 'tg_stats_locked' );
                        if ($locked)
                            $this->citizen_handler->removeStatus( $citizen, 'tg_stats_locked' );

                        foreach ($citizen->getStatus() as $status)
                            if (!$status->getHidden()) $this->citizen_handler->removeStatus( $citizen, $status );

                        if ($locked) $this->citizen_handler->inflictStatus( $citizen, 'tg_stats_locked' );
                        $this->entity_manager->persist($citizen);
                    }

                break;
            default: return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/role/manage", name="admin_town_manage_role", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Give or take role from selected citizens of a town
     * @param int $id Town ID
     * @param JSONRequestParser $parser The Request Parser
     * @param CitizenHandler $handler
     * @return Response
     */
    public function town_manage_role(int $id, JSONRequestParser $parser, TownHandler $handler): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        if (in_array($parser->get('role'), ['_ban_','_esc_','_nw_','_sh_','_wt_','_rst_'] ))
            return $this->town_manage_pseudo_role($town,$parser,$handler);

        $role_id = $parser->get_int('role');
        $targets = $parser->get_array('targets');

        $control = $parser->get_int('control', 0) > 0;

        /** @var CitizenRole $citizenRole */
        $citizenRole = $this->entity_manager->getRepository(CitizenRole::class)->find($role_id);
        if (!$citizenRole) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if ($control) $this->citizen_handler->addRole($citizen, $citizenRole);
            else $this->citizen_handler->removeRole($citizen, $citizenRole);

            $this->entity_manager->persist($citizen);
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/pp/alter", name="admin_town_alter_pp", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Change AP/CP/MP of selected citizens of a town
     * @param int $id Town ID
     * @param JSONRequestParser $parser The Request Parser
     * @return Response
     */
    public function town_alter_points(int $id, JSONRequestParser $parser): Response
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $point = $parser->get('point', '');
        if (!in_array($point, ['ap','bp','mp','gh','cc','cn'])) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $number = $parser->get_int('num', 6);

        $control = $parser->get_int('control', 0);
        if (!in_array($control, [-1,0,1])) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $targets = $parser->get_array('targets');

        foreach ($targets as $target) {
            /** @var Citizen $citizen */
            $citizen = $this->entity_manager->getRepository(Citizen::class)->find($target);
            if (!$citizen || $citizen->getTown() !== $town) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

            if (!$citizen->getActive()) continue;

            switch ($point) {
                case 'ap': $this->citizen_handler->setAP($citizen, false, ($control === 0) ? $number : $citizen->getAp() + $control * $number); break;
                case 'bp': $this->citizen_handler->setBP($citizen, false, ($control === 0) ? $number : $citizen->getBp() + $control * $number); break;
                case 'mp': $this->citizen_handler->setPM($citizen, false, ($control === 0) ? $number : $citizen->getPm() + $control * $number); break;
                case 'gh': $citizen->setGhulHunger( max(0, ($control === 0) ? $number : $citizen->getGhulHunger() + $control * $number) ); break;
                case 'cc': $citizen->setCampingChance( min(100,max(0, ($control === 0) ? $number : $citizen->getCampingChance() + $control * $number)) / 100.0 ); break;
                case 'cn': $citizen->setCampingCounter( max(0, ($control === 0) ? $number : $citizen->getCampingCounter() + $control * $number) ); break;
                default: break;
            }

            $this->entity_manager->persist($citizen);
        }

        $this->entity_manager->flush();
        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/buildings/add", name="admin_town_add_building", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Add a building to the town
     * @param int $id ID of the town
     * @param JSONRequestParser $parser The JSON request parser
     * @param TownHandler $th The town handler
     * @return Response
     */
    public function town_add_building(int $id, JSONRequestParser $parser, TownHandler $th, GameProfilerService $gps)
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        if (!$parser->has_all(['prototype_id'])) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $proto_id = $parser->get("prototype_id");

        $proto = $this->entity_manager->getRepository(BuildingPrototype::class)->find($proto_id);
        if (!$proto)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $th->addBuilding($town, $proto);
        $gps->recordBuildingDiscovered( $proto, $town, null, 'debug' );

        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/buildings/set-ap", name="admin_town_set_building_ap", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Set AP to a building of a town
     * @param int $id ID of the town
     * @param JSONRequestParser $parser The JSON request parser
     * @param TownHandler $th The town handler
     * @return Response
     */
    public function town_set_building_ap(int $id, JSONRequestParser $parser, TownHandler $th, GameProfilerService $gps)
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        if (!$parser->has_all(['building', 'ap'])) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $building_id = $parser->get("building");

        /** @var Building $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($building_id);
        if (!$building)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $ap = intval($parser->get("ap"));

        if ($ap >= $building->getPrototype()->getAp()) {
            $ap = $building->getPrototype()->getAp();
        }

        $building->setAp($ap);

        if ($building->getAp() >= $building->getPrototype()->getAp()) {
            $building->setComplete(true);
            $th->triggerBuildingCompletion($town, $building);
            $gps->recordBuildingConstructed( $building->getPrototype(), $town, null, 'debug' );
        } elseif ($building->getAp() <= 0) {
            $building->setComplete(false);
            $th->destroy_building($town, $building);
            $gps->recordBuildingDestroyed( $building->getPrototype(), $town, 'debug' );
        }

        $this->entity_manager->persist($building);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("api/admin/town/{id}/buildings/set-hp", name="admin_town_set_building_hp", requirements={"id"="\d+"})
     * @AdminLogProfile(enabled=true)
     * @Security("is_granted('ROLE_ADMIN')")
     * Set HP to a building of a town
     * @param int $id ID of the town
     * @param JSONRequestParser $parser The JSON request parser
     * @param TownHandler $th The town handler
     * @return Response
     */
    public function town_set_building_hp(int $id, JSONRequestParser $parser, TownHandler $th, GameProfilerService $gps)
    {
        $town = $this->entity_manager->getRepository(Town::class)->find($id);
        if (!$town) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        if (!$parser->has_all(['building', 'hp'])) {
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);
        }

        $building_id = $parser->get("building");

        /** @var Building $building */
        $building = $this->entity_manager->getRepository(Building::class)->find($building_id);
        if (!$building)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $hp = $parser->get_int("hp");

        if ($hp >= $building->getPrototype()->getHp()) {
            $hp = $building->getPrototype()->getHp();
        }

        $impervious = $building->getPrototype()->getImpervious();
        if (in_array($building->getPrototype()->getName(), ['small_arma_#00'])) $impervious = false;

        if (!$building->getComplete() || ($hp < $building->getPrototype()->getHp() && $impervious))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $building->setHp($hp);

        if ($building->getHp() <= 0) {
            $building->setComplete(false);
            $th->destroy_building($town, $building);
            $gps->recordBuildingDestroyed( $building->getPrototype(), $town, 'debug' );
        }

        $this->entity_manager->persist($building);
        $this->entity_manager->persist($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }

    /**
     * @Route("jx/admin/towns/old/fuzzyfind", name="admin_old_towns_fuzzyfind")
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function old_towns_fuzzyfind(JSONRequestParser $parser, EntityManagerInterface $em): Response
    {
        if (!$parser->has_all(['name'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $towns = $em->getRepository(TownRankingProxy::class)->findByNameContains($parser->get('name'));

        return $this->render('ajax/admin/towns/townlist.html.twig', $this->addDefaultTwigArgs("admin_towns", [
            'towns' => $towns,
            'nohref' => $parser->get('no-href', false),
            'target' => 'admin_old_town_explorer'
        ]));
    }

    /**
     * @Route("jx/admin/towns/fuzzyfind", name="admin_towns_fuzzyfind")
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    public function towns_fuzzyfind(JSONRequestParser $parser, EntityManagerInterface $em): Response
    {
        if (!$parser->has_all(['name'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $towns = $em->getRepository(Town::class)->findByNameContains($parser->get('name'));

        return $this->render('ajax/admin/towns/townlist.html.twig', $this->addDefaultTwigArgs("admin_towns", [
            'towns' => $towns,
            'nohref' => $parser->get('no-href', false),
            'target' => 'admin_town_explorer'
        ]));
    }
}
