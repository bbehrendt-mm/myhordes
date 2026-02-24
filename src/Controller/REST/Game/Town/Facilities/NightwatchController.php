<?php

namespace App\Controller\REST\Game\Town\Facilities;

use App\Annotations\GateKeeperProfile;
use App\Annotations\Semaphore;
use App\Controller\CustomAbstractCoreController;
use App\Entity\CitizenWatch;
use App\Enum\Configuration\TownSetting;
use App\Enum\EventStages\BuildingValueQuery;
use App\Service\ErrorHelper;
use App\Service\EventProxyService;
use App\Service\InventoryHandler;
use App\Service\TownHandler;
use App\Traits\Controller\ActiveCitizen;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/rest/v1/game/town/facilities/watch', name: 'rest_town_facilities_watch_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_USER')]
#[GateKeeperProfile(only_alive: true, only_with_profession: true, only_in_town: true)]
#[Semaphore('town', scope: 'town')]
class NightwatchController extends CustomAbstractCoreController
{
    use ActiveCitizen;

    #[Route(path: '/watchman', name: 'set', defaults: ['enable' => true], methods: ['PUT'])]
    #[Route(path: '/watchman', name: 'unset', defaults: ['enable' => false], methods: ['DELETE'])]
    public function watchman(TownHandler $townHandler, EntityManagerInterface $entityManager, EventProxyService $proxy, InventoryHandler $inventoryHandler, bool $enable): JsonResponse {
        $town = $this->getActiveCitizen()->getTown();
        $conf = $this->conf->getTownConfiguration( $town );

        // Check that nightwatch is enabled
        if (!$conf->get(TownSetting::OptFeatureNightwatch))
            return new JsonResponse(['error' => ErrorHelper::ErrorActionNotAvailable], Response::HTTP_NOT_ACCEPTABLE);

        // Check that nightwatch building is build OR instant nightwatch is enabled
        if (!$townHandler->getBuilding($town, 'small_round_path_#00', true) && !$conf->get(TownSetting::OptFeatureNightwatchInstant))
            return new JsonResponse(['error' => ErrorHelper::ErrorActionNotAvailable], Response::HTTP_NOT_ACCEPTABLE);

        // Get active watch entity for the current user if one exists
        $activeCitizenWatcher = $entityManager->getRepository(CitizenWatch::class)->findWatchOfCitizenForADay( $this->getActiveCitizen() );
        if (($activeCitizenWatcher === null) !== $enable )
            return new JsonResponse(['error' => ErrorHelper::ErrorActionNotAvailable], Response::HTTP_PRECONDITION_FAILED);

        if(!$enable) {

            // Disable watch
            $town->removeCitizenWatch($activeCitizenWatcher);
            $this->getActiveCitizen()->removeCitizenWatch($activeCitizenWatcher);
            $entityManager->remove($activeCitizenWatcher);
            $this->addFlash('notice', $this->translator->trans('Du verlässt deinen Posten?...',[], 'game'));

        } else {

            // Check item limits
            if ($inventoryHandler->isOverEncumbered( $this->getActiveCitizen()->getInventory() ))
                return new JsonResponse(['error' => 'message', 'message' =>
                    $this->translator->trans('Du magst stark sein, aber du bist kein mächtiger Chuck. Erleichtere dich deiner überzählichen Gegenstände, bevor du dich der Wache anschließt.',[], 'game')
                ], Response::HTTP_CONFLICT);

            // Check that the watcher limit is not yet reached
            if ($entityManager->getRepository(CitizenWatch::class)->count( ['town' => $town, 'day' => $town->getDay()] ) >= $proxy->queryTownParameter( $town, BuildingValueQuery::NightWatcherCap ))
                return new JsonResponse(['error' => ErrorHelper::ErrorActionNotAvailable], Response::HTTP_CONFLICT);

            // Create new watch entity
            $town->addCitizenWatch($citizenWatch = new CitizenWatch()
                ->setTown($town)
                ->setCitizen($this->getActiveCitizen())
                ->setDay($town->getDay()));

            $this->getActiveCitizen()->addCitizenWatch($citizenWatch);
            $entityManager->persist($citizenWatch);

            $this->addFlash('notice', $this->translator->trans('Bravo, du hast die verantwortungsvolle Aufgabe übernommen, deine Mitbürger vor den Untoten zu beschützen...',[], 'game'));
        }

        // Persist changes
        $entityManager->persist($this->getActiveCitizen());
        $entityManager->persist($town);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
