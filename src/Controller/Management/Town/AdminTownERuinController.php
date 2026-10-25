<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\AdminReport;
use App\Entity\BlackboardEdit;
use App\Entity\Citizen;
use App\Entity\CouncilEntry;
use App\Entity\RuinZone;
use App\Entity\Town;
use App\Entity\Zone;
use App\Entity\ZonePrototype;
use App\Enum\Configuration\TownSetting;
use App\Enum\EventStages\BuildingValueQuery;
use App\Response\AjaxResponse;
use App\Service\AdminLog;
use App\Service\ErrorHelper;
use App\Service\EventProxyService;
use App\Service\GazetteService;
use App\Service\Maps\MazeMaker;
use Doctrine\Common\Collections\Criteria;
use Exception;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownERuinController extends AdminActionController
{
    /**
     * @param Town $town
     * @param Zone|null $zone
     * @param int|null $floor
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/eruins_explorer', name: 'admin_town_eruins_explorer')]
    #[Route(path: 'jx/manage/town/{id<\d+>}/eruins_explorer/{zid<\d+>}/{floor<-?\d+>}', name: 'admin_town_eruins_explorer_with_map')]
    #[IsGranted('cheat', 'town')]
    public function town_explorer_eruins_explorer(
        #[MapEntity(id: 'id')] Town $town,
        #[MapEntity(id: 'zid')] ?Zone $zone = null,
        ?int $floor = null,
    ): Response {
        $conf_self = $this->conf->getTownConfiguration($town);

        $explorable_ruin_zones = $town->getZones()->matching(
            Criteria::create(true)->where(
                Criteria::expr()->in('prototype', $this->entity_manager->getRepository(ZonePrototype::class)->findBy(['explorable' => true]))
            ) );

        $explorables = $explorable_ruin_zones->map(function(Zone $active_zone) use ($zone, $floor) {
            $data = [
                'rz' => [],
                'z' => $active_zone,
                'x' => $active_zone->getExplorerStats(),
                'ax' => $active_zone->activeExplorerStats(),
                'axt' => $active_zone->activeExplorerStats() ? max(0, $active_zone->activeExplorerStats()->getTimeout()->getTimestamp() - time()) : null,
                'd' => $this->entity_manager->getRepository(RuinZone::class)
                    ->createQueryBuilder('r')
                    ->select(
                        'MIN( r.x ) AS x0', 'MAX( r.x ) AS x1',
                        'MIN( r.y ) AS y0', 'MAX( r.y ) AS y1',
                        'MIN( r.z ) AS z0', 'MAX( r.z ) AS z1',
                    )
                    ->where('r.zone = :zone')->setParameter('zone', $active_zone)
                    ->getQuery()->getSingleResult()
            ];

            if ($zone?->getId() !== $active_zone->getId()) return $data;

            $rz = $active_zone->getRuinZones()->matching(Criteria::create()->where(Criteria::expr()->eq('z', $floor)))->toArray();
            $data['rz'] = $rz;

            return $data;
        })->toArray();

		return $this->render('ajax/manage/towns/explorer_eruins_explorer.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'conf' => $conf_self,
			'day' => $town->getDay(),
			'tab' => "eruins_explorer",
			'explorables' => $explorables,
            'town_conf' => $this->conf->getTownConfiguration( $town ),

            'selected_zone' => $zone,
            'selected_floor' => $floor,
		])));
	}

    /**
     * @param Town $town
     * @param MazeMaker $mazeMaker
     * @param AdminLog $logger
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/admin_regenerate_ruins', name: 'admin_regenerate_ruins')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function admin_regenerate_ruins(#[MapEntity(id: 'id')] Town $town, MazeMaker $mazeMaker, AdminLog $logger): Response {
        $explorables = [];

        foreach ($town->getZones() as $zone)
        {
            /** @var Zone $zone */
            if ($zone->getPrototype() && $zone->getPrototype()->getExplorable()) {
                $explorables[$zone->getId()] = $zone;
            }
        }


        $conf = $this->conf->getTownConfiguration( $town );

        foreach ($explorables as $zone)
        {

            $mazeMaker->setTargetZone($zone);
            $zone->setExplorableFloors($conf->get(TownSetting::ERuinSpaceFloors));

            $mazeMaker->createField();
            $mazeMaker->generateCompleteMaze();

            try {
                $this->entity_manager->persist($town);
                $this->entity_manager->flush();
            } catch (Exception $e) {
                $logger->invoke(strval($e));
                return AjaxResponse::error(ErrorHelper::ErrorDatabaseException);
            }
        }

        $this->clearTownCaches($town);
        return AjaxResponse::success();
    }
}
