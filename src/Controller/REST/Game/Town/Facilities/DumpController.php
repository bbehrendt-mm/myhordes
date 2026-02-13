<?php

namespace App\Controller\REST\Game\Town\Facilities;

use App\Annotations\GateKeeperProfile;
use App\Annotations\Semaphore;
use App\Controller\CustomAbstractCoreEventController;
use App\Entity\ItemPrototype;
use App\Event\Game\Town\Addon\Dump\DumpInsertionCheckEvent;
use App\Event\Game\Town\Addon\Dump\DumpInsertionExecuteEvent;
use App\Event\Game\Town\Addon\Dump\DumpRetrieveCheckEvent;
use App\Event\Game\Town\Addon\Dump\DumpRetrieveExecuteEvent;
use App\EventListener\Game\Town\Addon\Dump\DumpUpgradesCheckListener;
use App\Service\EventFactory;
use App\Service\JSONRequestParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/rest/v1/game/town/facilities/dump', name: 'rest_town_facilities_dump_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_USER')]
#[GateKeeperProfile(only_alive: true, only_with_profession: true, only_in_town: true)]
#[Semaphore('town', scope: 'town')]
class DumpController extends CustomAbstractCoreEventController
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Route(path: '/{id}', name: 'insert', methods: ['PUT'])]
    public function insert(
        ItemPrototype $itemPrototype,
        EventFactory $e, JSONRequestParser $parser,
    ): JsonResponse {
        return $this->processEventChain(
            $e->gameInteractionEvent( DumpInsertionCheckEvent::class )->setup($itemPrototype, $parser->get_int('ap', 1, 1)),
            DumpInsertionExecuteEvent::class
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    #[Route(path: '/{id}', name: 'retrieve', methods: ['PATCH'])]
    public function retrieve(
        ItemPrototype $itemPrototype,
        EventFactory $e, JSONRequestParser $parser
    ): JsonResponse {
        return $this->processEventChain(
            $e->gameInteractionEvent( DumpRetrieveCheckEvent::class )->setup($itemPrototype, $parser->get_int('ap', 1, 1)),
            DumpRetrieveExecuteEvent::class
        );
    }

    #[Route(path: '/{id}', name: 'insert_home', methods: ['POST'])]
    public function insert_home(
        ItemPrototype $itemPrototype,
        EventFactory $e, JSONRequestParser $parser
    ): JsonResponse {
        return $this->processEventChain(
            $e->gameInteractionEvent( DumpInsertionCheckEvent::class )->setup($itemPrototype, $parser->get_int('ap', 1, 1), true),
            DumpInsertionExecuteEvent::class
        );
    }
}
