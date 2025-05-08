<?php

namespace App\Controller\REST\Game;

use App\Controller\CustomAbstractController;
use App\Service\ConfMaster;
use App\Service\CitizenHandler;
use App\Service\GazetteService;
use App\Service\TimeKeeperService;
use App\Service\InventoryHandler;
use App\Service\HookExecutor;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route(path: '/rest/v1/game/raventimes/', name: 'rest_game_raventimes_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_USER')]
class RavenTimesController extends CustomAbstractController
{

    public function __construct(
		EntityManagerInterface $em,
		TranslatorInterface $translator,
        TimeKeeperService $tk,
		CitizenHandler $ch,
        ConfMaster $conf,
		InventoryHandler $ih,
		HookExecutor $hookExecutor,
    	private GazetteService $gazette_service,
    )
    {
        parent::__construct($conf, $em, $tk, $ch, $ih, $translator, $hookExecutor);
    }

	#[Route(path: 'gazette/{day}', name: 'gazette_day', methods: ['GET'])]
	public function gazette_day(int $day): JsonResponse
	{
		$activeCitizen = $this->getActiveCitizen();

        if (!$this->gazette_service->canReadGazette($activeCitizen))
			return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $in_town = $activeCitizen->getZone() === null;
		$town = $activeCitizen->getTown();

		if ($day < 1 || $day > $town->getDay())
			return new JsonResponse([], Response::HTTP_BAD_REQUEST);

		// Disallow access to older gazettes if the citizen is not in town
		if (!$in_town && $activeCitizen->getAlive() && $day !== $town->getDay())
			return new JsonResponse([], Response::HTTP_BAD_REQUEST);

		return new JsonResponse($this->gazette_service->renderGazette($town, $day), Response::HTTP_OK);
	}
}
