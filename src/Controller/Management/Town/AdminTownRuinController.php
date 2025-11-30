<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\Town;
use App\Entity\Zone;
use App\Entity\ZonePrototype;
use App\Enum\Configuration\TownSetting;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use App\Service\Maps\MazeMaker;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownRuinController extends AdminActionController
{
    /**
     * @param Town $town
     * @param JSONRequestParser $parser
     * @param MazeMaker $mazeMaker
     * @return Response
     */
    #[Route(path: 'api/manage/town/{id<\d+>}/spawn_ruin', name: 'admin_spawn_ruin')]
    #[IsGranted('cheat', 'town')]
    #[AdminLogProfile(enabled: true)]
    public function spawn_ruin(Town $town, JSONRequestParser $parser, MazeMaker $mazeMaker): Response
    {
        $prototype_id = $parser->get('prototype');
        $zone = $parser->get_int('zone');

        /** @var ZonePrototype $ruin */
        $ruin = $this->entity_manager->getRepository(ZonePrototype::class)->find($prototype_id);
        if (!$ruin) return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        /** @var Zone $zone */
        $zone = $this->entity_manager->getRepository(Zone::class)->find($zone);
        if (!$zone || $zone->getTown() !== $town)
            return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

        $this->map_maker->spawnRuin($town, $zone, $ruin);
        if ($ruin->getExplorable()) {
            $mazeMaker->setTargetZone($zone);
            $zone->setExplorableFloors($this->conf->getTownConfiguration($town)->get(TownSetting::ERuinSpaceFloors));
            $mazeMaker->createField();
            $mazeMaker->generateCompleteMaze();
        }

        $this->clearTownCaches($town);
        $this->entity_manager->flush();

        return AjaxResponse::success();
    }
}
