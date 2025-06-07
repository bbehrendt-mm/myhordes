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
use App\Entity\MayorMark;
use App\Entity\SpecialActionPrototype;
use App\Entity\Town;
use App\Entity\TownClass;
use App\Entity\TownSlotReservation;
use App\Entity\User;
use App\Enum\Configuration\MyHordesSetting;
use App\Response\AjaxResponse;
use App\Service\Actions\Ghost\ExplainTownConfigAction;
use App\Service\Actions\Security\GenerateMercureToken;
use App\Service\ConfMaster;
use App\Service\ErrorHelper;
use App\Service\GameFactory;
use App\Service\JSONRequestParser;
use App\Service\TownHandler;
use App\Service\UserHandler;
use App\Structures\EventConf;
use App\Structures\MyHordesConf;
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
    public function __construct(
        private readonly UserHandler $userHandler
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

}
