<?php

namespace App\Controller\REST\Admin\Schedule;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\AttackSchedule;
use App\Entity\Citizen;
use App\Entity\User;
use App\Enum\UserAccountType;
use App\Service\JSONRequestParser;
use App\Structures\MyHordesConf;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * @Route("/rest/v1/admin/schedule/attack", name="rest_admin_schedule_attack_", condition="request.headers.get('Accept') === 'application/json'")
 * @IsGranted("ROLE_ADMIN")
 * @GateKeeperProfile("skip")
 */
class AttackScheduleController extends CustomAbstractCoreController
{
    private function current_delay( EntityManagerInterface $em ): JsonResponse {
        $planned = $em->getRepository(AttackSchedule::class)->findBy(['completed' => false, 'startedAt' => null], ['timestamp' => 'ASC']);
        if (empty($planned)) return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);

        if (count($planned) > 1)
            $em->remove( $planned[array_key_first($planned)] );
        else {
            $planned = $planned[array_key_first($planned)];
            $datemod = $this->conf->getGlobalConf()->get(MyHordesConf::CONF_NIGHTLY_DATEMOD, 'tomorrow');
            if ($datemod !== 'never') {
                $new_date = (new DateTime())->setTimestamp( $planned->getTimestamp()->getTimestamp() )->modify($datemod);
                if ($new_date !== false && $new_date > $planned->getTimestamp())
                    $em->persist( $planned->setTimestamp( DateTimeImmutable::createFromMutable($new_date)) );
                else return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);
            } else return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);
        }

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @Route("/current", name="find", methods={"PATCH"})
     * @param EntityManagerInterface $em
     * @param JSONRequestParser $parser
     * @return JsonResponse
     */
    public function modify(EntityManagerInterface $em, JSONRequestParser $parser): JsonResponse {
        if (!$parser->has('method', true))
            return new JsonResponse([], Response::HTTP_UNPROCESSABLE_ENTITY);

        return match ( $parser->get('method') ) {
            'delay' => $this->current_delay( $em ),
            default => new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE)
        };
    }
}
