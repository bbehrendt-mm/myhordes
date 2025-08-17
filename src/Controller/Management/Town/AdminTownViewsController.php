<?php

namespace App\Controller\Management\Town;

use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\AdminReport;
use App\Entity\BlackboardEdit;
use App\Entity\Citizen;
use App\Entity\CouncilEntry;
use App\Entity\Town;
use App\Enum\EventStages\BuildingValueQuery;
use App\Service\EventProxyService;
use App\Service\GazetteService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownViewsController extends AdminActionController
{
    /**
     * @param Town $town
     * @param GazetteService $gazetteService
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/register', name: 'admin_town_register')]
    #[IsGranted('spy', 'town')]
    public function town_explorer_register(Town $town, GazetteService $gazetteService): Response {
		return $this->render('ajax/manage/towns/explorer_register.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
			'tab' => "register",
			'gazette' => $gazetteService->renderGazette( $town, $town->getDay(), true),
			'council' => array_map( fn(CouncilEntry $c) => [$gazetteService->parseCouncilLog( $c ), $c->getCitizen()], array_filter( $this->entity_manager->getRepository(CouncilEntry::class)->findBy(['town' => $town, 'day' => $town->getDay()], ['ord' => 'ASC']),
				fn(CouncilEntry $c) => ($c->getTemplate() && $c->getTemplate()->getText() !== null)
			)),
		])));
	}

    /**
     * @param Town $town
     * @param int $highlight
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/blackboard/{highlight<\d+>}', name: 'admin_town_blackboard')]
    #[IsGranted('ROLE_CROW')]
    public function town_explorer_blackboard(Town $town, int $highlight = 0): Response {
        $blackboards = $this->entity_manager->getRepository(BlackboardEdit::class)->findBy([ 'town' => $town ], ['time' => 'DESC'], $highlight > 0 ? 500 : 100);
        $reports_q = $this->entity_manager->getRepository(AdminReport::class)->findBy(['blackBoard' => $blackboards]);

        $reports = [];
        foreach ($blackboards as $b) $reports[$b->getId()] = [];
        foreach ($reports_q as $r) $reports[$r->getBlackBoard()->getId()][] = $r;

		return $this->render('ajax/manage/towns/explorer_blackboard.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
			'tab' => "blackboard",
			'highlight' => $highlight,
			'blackboards' => $blackboards,
			'reports' => $reports,
		])));
	}

    /**
     * @param Town $town
     * @param EventProxyService $proxy
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/estimations', name: 'admin_town_estimations')]
    #[IsGranted('spy', 'town')]
    public function town_explorer_estimations(Town $town, EventProxyService $proxy): Response {
        $maxAttacks = [];
        foreach ($town->getZombieEstimations() as $estimation) {
            $day = $estimation->getDay();
            $alive_citizens = $town->getCitizens()->filter( fn(Citizen $c) => $c->getAlive() || $c->getDayOfDeath() >= $day )->count();
            $maxAttacks[$day] = [ $alive_citizens, $proxy->queryTownParameter( $town, BuildingValueQuery::MaxActiveZombies, [$alive_citizens] ) ];
        }

		return $this->render('ajax/manage/towns/explorer_estimations.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
            'active' => $maxAttacks,
			'tab' => "estimations",
		])));
	}

    /**
     * @param Town $town
     * @param string|null $conf
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/config/{conf?}', name: 'admin_town_config')]
    #[IsGranted('administrate', 'town')]
    public function town_explorer_config(Town $town, ?string $conf): Response {
		$conf_self = $this->conf->getTownConfiguration($town);
		$conf_compare = match($conf) {
			'small', 'remote', 'panda', 'default' => $this->conf->getTownConfigurationByType($conf),
			default => null,
		};

		return $this->render('ajax/manage/towns/explorer_config.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'day' => $town->getDay(),
			'tab' => "config",
			'opt_conf' => $conf,
			'conf' => $conf_self,
			'conf_self' => $conf_self,
			'conf_compare' => $conf_compare,
			'conf_keys' => array_unique( array_merge( array_keys( $conf_self->raw() ), array_keys( $conf_compare?->raw() ?? [] ) ) ),
		])));
	}

    /**
     * @param Town $town
     * @param int $day
     * @param GazetteService $gazetteService
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/gazette/{day<\d+>}', name: 'admin_town_explorer_gazette', priority: 1)]
    #[IsGranted('spy', 'town')]
    public function api_explore_gazette(Town $town, int $day, GazetteService $gazetteService): Response {
        return $this->render('ajax/game/gazette_widget.html.twig', [
            'soul' => true,
            'gazette' => $gazetteService->renderGazette( $town, $day, true ),
            'council' => array_map( fn(CouncilEntry $c) => [$gazetteService->parseCouncilLog( $c ), $c->getCitizen()], array_filter( $this->entity_manager->getRepository(CouncilEntry::class)->findBy(['town' => $town, 'day' => $day], ['ord' => 'ASC']),
                fn(CouncilEntry $c) => ($c->getTemplate() && $c->getTemplate()->getText() !== null)
            ))
        ]);
    }
}
