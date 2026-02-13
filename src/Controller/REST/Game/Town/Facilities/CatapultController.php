<?php

namespace App\Controller\REST\Game\Town\Facilities;

use App\Annotations\GateKeeperProfile;
use App\Annotations\Semaphore;
use App\Controller\CustomAbstractCoreController;
use App\Controller\CustomAbstractCoreEventController;
use App\Entity\Item;
use App\Entity\Zone;
use App\Enum\ActionHandler\CountType;
use App\Enum\ClientSignal;
use App\Event\Game\Town\Basic\Well\WellExtractionCheckEvent;
use App\Event\Game\Town\Basic\Well\WellExtractionExecuteEvent;
use App\Event\Game\Town\Basic\Well\WellInsertionCheckEvent;
use App\Event\Game\Town\Basic\Well\WellInsertionExecuteEvent;
use App\Service\ActionHandler;
use App\Service\CitizenHandler;
use App\Service\ErrorHelper;
use App\Service\EventFactory;
use App\Service\Globals\ResponseGlobal;
use App\Service\JSONRequestParser;
use App\Service\LogTemplateHandler;
use App\Service\RandomGenerator;
use App\Service\TownHandler;
use App\Structures\ActionHandler\Evaluation;
use App\Structures\ActionHandler\Execution;
use App\Structures\CatapultActionTarget;
use App\Traits\Controller\ActiveCitizen;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/rest/v1/game/town/facilities/catapult', name: 'rest_town_facilities_catapult_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_USER')]
#[Semaphore('town', scope: 'town')]
#[GateKeeperProfile(only_alive: true, only_with_profession: true, only_in_town: true)]
class CatapultController extends CustomAbstractCoreController
{
    use ActiveCitizen;

    /**
     * @param Packages $asset
     * @return JsonResponse
     */
    #[Route(path: '', name: 'base', methods: ['GET'])]
    #[GateKeeperProfile('skip')]
    public function index(Packages $asset): JsonResponse {
        return new JsonResponse([
            'common' => [
                'object' => $this->translator->trans('Zu verschickender Gegenstand:', [], 'game'),
                'confirm' => $this->translator->trans('Gegenstand katapultieren', [], 'game'),
            ],
        ]);
    }

    #[Route(path: '/{id}', name: 'catapult', methods: ['POST'])]
    public function catapult(Item $item,
        TownHandler $townHandler,
        JSONRequestParser $parser,
        CitizenHandler $ch,
        RandomGenerator $randomGenerator,
        EntityManagerInterface $em,
        LogTemplateHandler $log,
        ActionHandler $actionHandler,
        Packages $asset,
        ResponseGlobal $response
    ): JsonResponse {
        $citizen = $this->getActiveCitizen();
        $town = $citizen->getTown();

        if (!$citizen->getInventory()->getItems()->contains($item))
            return new JsonResponse(status: Response::HTTP_NOT_FOUND);

        // Check if catapult is build
        if (!$townHandler->getBuilding($town, 'item_courroie_#00', true) || !$citizen->hasRole('cata'))
            return new JsonResponse(status: Response::HTTP_PRECONDITION_FAILED);

        // Check if improved catapult is build
        $improved = $townHandler->getBuilding( $town, 'item_courroie_#01', true ) !== null;

        // Get prototype ID
        if (!$parser->has_all(['x','y'], false))
            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);

        $x = (int)$parser->get('x');
        $y = (int)$parser->get('y');

        if ($item->getEssential() || $item->getBroken() || ($action = $item->getPrototype()->getCatapultAction()) === null)
            return new JsonResponse(status: Response::HTTP_BAD_REQUEST);

        $target_zone = $town->getZone($x,$y);
        if (!$target_zone) return new JsonResponse(status: Response::HTTP_NOT_FOUND);

        // Check if the improved catapult is built
        $ap = $improved ? 2 : 4;

        // Make sure the citizen has enough AP
        if ($citizen->getAp() < $ap || $ch->isTired($citizen))
            return new JsonResponse(['error' => ErrorHelper::ErrorNoAP], status: Response::HTTP_NOT_ACCEPTABLE);

        if ($item->getPrototype()->hasProperty('pet') && !$townHandler->getBuilding($town, 'small_catapult3_#00' ))
            return new JsonResponse( ['success' => false, 'message' => $this->translator->trans('Die Stadt benötigt einen Kleinen Tribok, um Haustiere katapultieren zu können...', [], 'game')] );

        // Different target zone
        $deviantChance = $improved ? 0.05 : 0.25;
        if ($randomGenerator->chance($deviantChance)) {
            $alt_zones = array_filter( $town->getZoneCross( $x, $y, 1 )->toArray(), fn(Zone $z) => $z->getId() !== $target_zone->getId() );
            if (!empty($alt_zones)) $target_zone = $randomGenerator->pick($alt_zones);
        }

        // Deduct AP
        $ch->setAP($citizen, true, -$ap);

        $cachedItemPrototype = $item->getPrototype();

        /** @var Execution $result */
        $error = $actionHandler->execute(
            $citizen, $item, new CatapultActionTarget($target_zone),
            $action, $msg, $r, true,
            execution: $result
        );

        if ($error !== ActionHandler::ErrorNone)
            return new JsonResponse(['error' => $error], status: Response::HTTP_INTERNAL_SERVER_ERROR);

        $messages = [];
        if ($msg) $messages[] = $msg;
        $messages[] = $this->translator->trans('Sorgfältig verpackt hast du {item} in das Katapult gelegt. Der Gegenstand wurde auf {zone} geschleudert.', [
            '{item}' => "<span><img alt='' src='" . $asset->getUrl("build/images/item/item_{$cachedItemPrototype->getIcon()}.gif") . "' />" . $this->translator->trans($cachedItemPrototype->getLabel(), [], 'items') . "</span>",
            '{zone}' => "<strong>[{$target_zone->getX()}/{$target_zone->getY()}]</strong>"
        ], 'game');

        $kills = $result->getCounter( CountType::Kills );
        if ($kills > 0) $messages[] = $this->translator->trans('Durch den Einschlag wurden {kills} Zombies getötet!', ['kills' => $kills], 'game');

        foreach ($r as $rr) $em->remove($rr);

        // Log entry
        $em->persist($log->catapultUsage($citizen, $cachedItemPrototype, $target_zone, $kills));

        // Persist
        $em->persist( $citizen );
        $em->persist( $target_zone );
        $em->persist( $town );

        // Flush
        $em->flush();

        $response->withSignal(ClientSignal::InventoryUpdated, ClientSignal::StatusUpdated, ClientSignal::LogUpdated, ClientSignal::MapUpdated );
        return new JsonResponse([
            'success' => true,
            'message' => implode('<hr/>', $messages),
            'incidentals' => [
                'ap' => $citizen->getAp(),
            ]
        ]);
    }
}
