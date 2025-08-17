<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\Citizen;
use App\Entity\Town;
use App\Entity\TownRankingProxy;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\GameProfilerService;
use App\Service\JSONRequestParser;
use App\Service\TownHandler;
use App\Structures\EventConf;
use App\Structures\TownSetup;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownCommonsController extends AdminActionController
{
	/**
     * @return Response
     */
    #[Route(path: 'jx/manage/towns', name: 'admin_town_list')]
    public function town_list(): Response
    {
        $this->denyAccessUnlessGranted('list', Town::class);
        $towns = array_filter(
            $this->entity_manager->getRepository(Town::class)->findAll(),
            fn(Town $town) => $this->isGranted( 'spy', $town )
        );

        return $this->render('ajax/manage/towns/list.html.twig', $this->addDefaultTwigArgs('towns', [
            'towns' => $towns,
            'citizen_stats' => $this->entity_manager->getRepository(Citizen::class)->getStatByLang(),
            'langs' => $this->generatedLangs,
        ]));
    }

    /**
     * @param int $page The page we're viewing
     * @return Response
     */
    #[Route(path: 'jx/manage/towns/old/{page}', name: 'admin_old_town_list', requirements: ['page' => '\d+'])]
    public function old_town_list(int $page = 1): Response
    {
        $this->denyAccessUnlessGranted('list', Town::class);

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

        return $this->render('ajax/manage/towns/old_towns_list.html.twig', $this->addDefaultTwigArgs('old_towns', [
            'towns' => $paginator
                ->getQuery()
                ->setFirstResult($pageSize * ($page - 1)) // set the offset
                ->setMaxResults($pageSize)
                ->getResult(),
            'page' => $page,
            'pages' => $pagesCount
        ]));
    }

    /**
     * @param JSONRequestParser $parser
     * @param GameFactory $gameFactory
     * @param TownHandler $townHandler
     * @param GameProfilerService $gps
     * @return Response
     * @throws Exception
     */
    #[Route(path: 'api/manage/town/new', name: 'admin_new_town')]
    #[AdminLogProfile(enabled: true)]
    public function add_default_town( JSONRequestParser $parser, GameFactory $gameFactory, TownHandler $townHandler, GameProfilerService $gps): Response {
        $this->denyAccessUnlessGranted('create', Town::class);

        $town_name = $parser->get('name', null) ?: null;
        $town_type = $parser->get('type', '');
        $town_lang = $parser->get('lang', 'de');
        $town_time = $parser->get('time', '');

        try {
            $town_time = empty($town_time) ? null : new \DateTime($town_time);
            if ($town_time <= new \DateTime()) $town_time = null;
        } catch (\Throwable) {
            $town_time = null;
        }


        if (!in_array($town_lang, array_merge($this->generatedLangsCodes, ['multi'])))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $this->logger->invoke("[add_default_town] Admin <info>{$this->getUser()->getName()}</info> created a <info>$town_lang</info> town (custom name: '<info>$town_name</info>'), which is of type <info>$town_type</info>");

        $current_events = $this->conf->getCurrentEvents();
        $name_changers = array_values(
            array_map( fn(EventConf $e) => $e->get( EventConf::EVENT_MUTATE_NAME ), array_filter($current_events,fn(EventConf $e) => $e->active() && $e->get( EventConf::EVENT_MUTATE_NAME )))
        );

        $town = $gameFactory->createTown( new TownSetup( $town_type, name: $town_name, language: $town_lang, nameMutator: $name_changers[0] ?? null ));
        if (!$town) {
            $this->logger->invoke("Town creation failed!");
            return AjaxResponse::error(ErrorHelper::ErrorInternalError);
        }

        $town->setScheduledFor( $town_time );

        try {
            $this->entity_manager->persist( $town );
            $this->entity_manager->flush();
            $gps->recordTownCreated( $town, $this->getUser(), 'manual' );
            $this->entity_manager->flush();
        } catch (Exception $e) {
            return AjaxResponse::error(ErrorHelper::ErrorDatabaseException, ['e' => $e->getMessage()]);
        }

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
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route(path: 'jx/manage/towns/old/fuzzyfind', name: 'admin_old_towns_fuzzyfind')]
    public function old_towns_fuzzyfind(JSONRequestParser $parser, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('list', Town::class);
        if (!$parser->has_all(['name'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $towns = $em->getRepository(TownRankingProxy::class)->findByNameContains($parser->get('name'));

        return $this->render('ajax/manage/towns/townlist.html.twig', $this->addDefaultTwigArgs("admin_towns", [
            'towns' => $towns,
            'nohref' => $parser->get('no-href', false),
            'target' => 'admin_old_town_explorer'
        ]));
    }

    /**
     * @param JSONRequestParser $parser
     * @param EntityManagerInterface $em
     * @return Response
     */
    #[Route(path: 'jx/manage/towns/fuzzyfind', name: 'admin_towns_fuzzyfind')]
    public function towns_fuzzyfind(JSONRequestParser $parser, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('list', Town::class);
        if (!$parser->has_all(['name'], true))
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $towns = array_filter(
            $em->getRepository(Town::class)->findByNameContains($parser->get('name')),
            fn(Town $town) => $this->isGranted( 'spy', $town )
        );

        return $this->render('ajax/manage/towns/townlist.html.twig', $this->addDefaultTwigArgs("admin_towns", [
            'towns' => $towns,
            'nohref' => $parser->get('no-href', false),
            'target' => 'admin_town_dashboard'
        ]));
    }
}
