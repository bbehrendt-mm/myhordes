<?php

namespace App\Controller\Management\Town;

use App\Annotations\AdminLogProfile;
use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Entity\CitizenRankingProxy;
use App\Entity\Picto;
use App\Entity\PictoPrototype;
use App\Entity\Town;
use App\Entity\TownRankingProxy;
use App\Response\AjaxResponse;
use App\Service\ErrorHelper;
use App\Service\JSONRequestParser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/', condition: 'request.isXmlHttpRequest()')]
#[GateKeeperProfile(allow_during_attack: true)]
class AdminTownOldTownsController extends AdminActionController
{
    /**
     * @param TownRankingProxy $town
     * @param string|null $tab the tab we want to display
     * @return Response
     */
    #[Route(path: 'jx/manage/town/proxy/{id<\d+>}/{tab?}', name: 'admin_old_town_explorer')]
    public function old_town_explorer(TownRankingProxy $town, ?string $tab): Response
    {
        $this->denyAccessUnlessGranted('list', Town::class);
        $pictoProtos = $this->entity_manager->getRepository(PictoPrototype::class)->findAll();
        usort($pictoProtos, function ($a, $b) {
            return strcmp($this->translator->trans($a->getLabel(), [], 'game'), $this->translator->trans($b->getLabel(), [], 'game'));
        });

        return $this->render('ajax/manage/towns/old_town_explorer.html.twig', $this->addDefaultTwigArgs('old_explorer', [
            'town' => $town,
            'day' => $town->getDays(),
            'pictoPrototypes' => $pictoProtos,
            'tab' => $tab
        ]));
    }

    /**
     * @param TownRankingProxy $town
     * @param JSONRequestParser $parser
     * @return Response
     */
	#[Route(path: 'api/manage/town/proxy/{id<\d+>}/get_citizen_infos', name: 'get_old_citizen_infos')]
	#[AdminLogProfile(enabled: true)]
	public function get_old_citizen_infos(TownRankingProxy $town, JSONRequestParser  $parser): Response{
        $this->denyAccessUnlessGranted('list', Town::class);
        $citizen_id = $parser->get('citizen_id', -1);
		$citizen = $this->entity_manager->getRepository(CitizenRankingProxy::class)->find($citizen_id);

		if(!$citizen || $citizen->getTown() !== $town)
			return AjaxResponse::error(ErrorHelper::ErrorInvalidRequest);

		$pictos = $this->renderView("ajax/manage/towns/distinctions.html.twig", [
			'pictos' => $this->entity_manager->getRepository(Picto::class)->findPictoByUserAndTown($citizen->getUser(), $citizen->getTown()),
		]);

		return AjaxResponse::success(true, [
			'pictos' => $pictos,
		]);
	}
}
