<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\AdminReport;
use App\Entity\BlackboardEdit;
use App\Entity\Citizen;
use App\Entity\CouncilEntry;
use App\Entity\Town;
use App\Entity\Zone;
use App\Enum\Configuration\TownSetting;
use App\Enum\EventStages\BuildingValueQuery;
use App\Response\AjaxResponse;
use App\Service\AdminLog;
use App\Service\ErrorHelper;
use App\Service\EventProxyService;
use App\Service\GazetteService;
use App\Service\Maps\MazeMaker;
use Exception;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownERuinController extends AdminActionController
{
    /**
     * @param Town $town
     * @return Response
     */
    #[Route(path: 'jx/manage/town/{id<\d+>}/eruins_explorer', name: 'admin_town_eruins_explorer')]
    #[IsGranted('cheat', 'town')]
    public function town_explorer_eruins_explorer(Town $town): Response {
        $conf_self = $this->conf->getTownConfiguration($town);

		$explorables = [];
		foreach ($town->getZones() as $zone)
			/** @var Zone $zone */
			if ($zone->getPrototype() && $zone->getPrototype()->getExplorable()) {
				$explorables[$zone->getId()] = ['rz' => [], 'z' => $zone, 'x' => $zone->getExplorerStats(), 'ax' => $zone->activeExplorerStats()];
				if ($zone->activeExplorerStats()) $explorables[$zone->getId()]['axt'] = max(0, $zone->activeExplorerStats()->getTimeout()->getTimestamp() - time());
				$rz = $zone->getRuinZones();
				foreach ($rz as $r) {
					if (!isset( $explorables[$zone->getId()]['rz'][$r->getZ()] ))
						$explorables[$zone->getId()]['rz'][$r->getZ()] = [];
					$explorables[$zone->getId()]['rz'][$r->getZ()][] = $r;
				}
				ksort($explorables[$zone->getId()]['rz']);
			}

		return $this->render('ajax/manage/towns/explorer_eruins_explorer.html.twig', $this->addDefaultTwigArgs(null, array_merge([
			'town' => $town,
			'conf' => $conf_self,
			'day' => $town->getDay(),
			'tab' => "eruins_explorer",
			'explorables' => $explorables,
            'town_conf' => $this->conf->getTownConfiguration( $town )
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
    public function admin_regenerate_ruins(Town $town, MazeMaker $mazeMaker, AdminLog $logger): Response {
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
