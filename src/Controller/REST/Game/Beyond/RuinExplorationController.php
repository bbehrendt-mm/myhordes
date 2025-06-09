<?php

namespace App\Controller\REST\Game\Beyond;

use App\Annotations\GateKeeperProfile;
use App\Annotations\Semaphore;
use App\Controller\GhostController;
use App\Entity\AccountRestriction;
use App\Entity\Citizen;
use App\Entity\CitizenProfession;
use App\Entity\CitizenRankingProxy;
use App\Entity\CitizenRole;
use App\Entity\ItemPrototype;
use App\Entity\MayorMark;
use App\Entity\RuinExplorerStats;
use App\Entity\RuinZone;
use App\Entity\SpecialActionPrototype;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\TownSlotReservation;
use App\Entity\User;
use App\Enum\ClientSignal;
use App\Enum\Configuration\MyHordesSetting;
use App\Enum\Game\ExplorableRuinSkin;
use App\Response\AjaxResponse;
use App\Service\Actions\Ghost\ExplainTownConfigAction;
use App\Service\Actions\Security\GenerateMercureToken;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\Globals\ResponseGlobal;
use App\Service\JSONRequestParser;
use App\Service\TownHandler;
use App\Service\UserHandler;
use App\Structures\EventConf;
use App\Structures\MyHordesConf;
use App\Traits\Controller\ActiveCitizen;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use function Symfony\Component\Clock\now;

/**
 * @method User getUser
 */
#[Route(path: '/rest/v1/game/beyond/e-ruin', name: 'rest_town_core_door_', condition: "request.headers.get('Accept') === 'application/json'")]
#[GateKeeperProfile(only_ghost: true)]
#[IsGranted('ROLE_USER')]
class RuinExplorationController extends AbstractController
{
    use ActiveCitizen;

    public function __construct(
        private readonly UserHandler            $userHandler,
        private readonly EntityManagerInterface $entityManager,
    ) {

    }

    #[Route(path: '', name: 'base', methods: ['GET'])]
    #[GateKeeperProfile('skip')]
    public function index(TranslatorInterface $trans, Packages $asset, EntityManagerInterface $em, ConfMaster $conf): JsonResponse {
        return new JsonResponse([
            'common' => [

            ],
        ]);
    }

    #[Route(path: '/assets/{theme}', name: 'assets', methods: ['GET'])]
    #[GateKeeperProfile('skip')]
    public function assets(ExplorableRuinSkin $theme, Packages $asset): JsonResponse {
        return new JsonResponse([
            'tiles' => $theme->assetsTiles()->map( fn(string $s) => $asset->getUrl( $s ) )->toArray(),
            'doors' => [
                'open_up'   => $theme->assetDoors(true, 1)->map( fn(string $s) => $asset->getUrl( $s ) )->toArray(),
                'open_down' => $theme->assetDoors(true, -1)->map( fn(string $s) => $asset->getUrl( $s ) )->toArray(),
                'open'   => $theme->assetDoors(true)->map( fn(string $s) => $asset->getUrl( $s ) )->toArray(),
                'closed' => $theme->assetDoors(false)->map( fn(string $s) => $asset->getUrl( $s ) )->toArray(),
            ],
            'decals' => $theme->assetDecals()->map( fn(array $a) => [...$a, 'i' => $asset->getUrl( $a['i'] )] )->toArray(),
        ]);
    }

    protected function getCurrentRuinZone(): RuinZone {
        $citizen = $this->getActiveCitizen();
        $ex = $this->getActiveCitizen()->activeExplorerStats();
        return $this->entityManager->getRepository(RuinZone::class)->findOneByPosition($citizen->getZone(), $ex->getX(), $ex->getY(), $ex->getZ());
    }

    protected function renderTileset(RuinZone $zone): array {
        return [
            'tile' => $zone->isEntry() ? -1 : $zone->getCorridor(),
            'door' => $zone->getDoorPosition(),
            'elev' => $zone->getDoorPosition() ? $zone->getPrototype()->getLevel() : 0,
            'deco' => $zone->getUnifiedDecals(),
        ];
    }

    protected function renderStatus(RuinZone $zone, RuinExplorerStats $stats): array {
        $guide = $stats->getCitizen()->hasRole('guide');
        $exitZone =
            $stats->getCitizen()->getProfession()->getName() === 'tamer'
                ? ($stats->getZ() === 0
                    ? $this->entityManager->getRepository(RuinZone::class)->findOneBy(['zone' => $zone->getZone(), 'x' => 0, 'y' => 0, 'z' => 0])
                    : $this->entityManager->getRepository(RuinZone::class)->findOneBy(['zone' => $zone->getZone(), 'z' => $stats->getZ(), 'connect' => -1])
                ) : null;

        $in_grace = $stats->isGrace() && $stats->getStarted() !== null && (new DateTime())->modify('-30sec') < $stats->getStarted();

        $exit_angle = null;
        if ($exitZone) {
            $dx = $exitZone->getX() - $zone->getX();
            $dy = $exitZone->getY() - $zone->getY();
            if ($dx !== 0 || $dy !== 0) {
                $exit_angle = round( acos( $dy / sqrt( pow($dx, 2) + pow($dy, 2) ) ) * 57.2957795 );
                if ($dx > 0) $exit_angle = 360 - $exit_angle;
            }
        }

        return [
            'paused' => $in_grace,
            'exit' => $exit_angle,
            'shifted' => $stats->getInRoom(),
            'activity' => $guide ? (0.1 + 0.9 * (4-min(4, $zone->getRoomDistance()))/4) : 1,
            'floor' => $zone->getFloor()->getId(),
        ];
    }

    #[Route(path: '/explore', name: 'get_exploration_info', methods: ['GET'])]
    #[GateKeeperProfile(only_alive: true, only_with_profession: true, only_in_ruin: true)]
    public function explore(): Response
    {
        $ex = $this->getActiveCitizen()->activeExplorerStats();
        $ruinZone = $this->getCurrentRuinZone();

        return new JsonResponse([
            'status' => $this->renderStatus($ruinZone, $ex),
            'tileset' => $this->renderTileset($ruinZone),
        ]);
    }

    #[Route(path: '/explore', name: 'move_exploration', methods: ['PATCH'])]
    #[GateKeeperProfile(only_alive: true, only_with_profession: true, only_in_ruin: true)]
    public function explore_move(JSONRequestParser $parser, ResponseGlobal $response) {
        $ruinZone = $this->getCurrentRuinZone();
        $ex = $this->getActiveCitizen()->activeExplorerStats();

        if ($ruinZone->getZombies() > 0 && !$ex->getEscaping())
            return new JsonResponse([], Response::HTTP_CONFLICT);

        if ($ex->getInRoom())
            return new JsonResponse([], Response::HTTP_NOT_FOUND);

        $dx = (int)$parser->get('dx', 0);
        $dy = (int)$parser->get('dy', 0);

        if (abs($dx) + abs($dy) !== 1)
            return new JsonResponse([], Response::HTTP_NOT_FOUND);

        if (
            ($dx == 1  && !$ruinZone->hasCorridor( RuinZone::CORRIDOR_E )) ||
            ($dx == -1 && !$ruinZone->hasCorridor( RuinZone::CORRIDOR_W )) ||
            ($dy == 1  && !$ruinZone->hasCorridor( RuinZone::CORRIDOR_N )) ||
            ($dy == -1 && !$ruinZone->hasCorridor( RuinZone::CORRIDOR_S ))
        ) return new JsonResponse([], Response::HTTP_NOT_FOUND);


        if ($ex->isGrace()) {
            $ex->setGrace(false);
            if ($ex->getStarted() !== null) {
                $offset = max(0, min(30, time() - $ex->getStarted()->getTimestamp()));
                $ex->setTimeout( (new DateTime())->setTimestamp( $ex->getTimeout()->getTimestamp() - ( 30 - $offset ) ) );
            }
        }

        $ex
            ->setX( $ex->getX() + $dx )
            ->setY( $ex->getY() - $dy )
            ->setEscaping( false );

        $this->entityManager->persist($ex);
        $this->entityManager->flush();

        $new_zone = $this->getCurrentRuinZone();

        return new JsonResponse([
            'status' => $this->renderStatus($new_zone, $ex),
            'tileset' => $this->renderTileset($new_zone),
        ]);
    }

}
