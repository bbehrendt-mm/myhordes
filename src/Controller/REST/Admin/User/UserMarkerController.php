<?php

namespace App\Controller\REST\Admin\User;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\AutomaticAccountMarker;
use App\Entity\ServerSettings;
use App\Entity\User;
use App\Enum\AutomaticAccountMarkerType;
use App\Enum\ServerSetting;
use App\Service\JSONRequestParser;
use Carbon\Carbon;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/rest/v1/admin/user/{user}/markers', name: 'rest_admin_user_marker_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_CROW')]
#[GateKeeperProfile('skip')]
class UserMarkerController extends CustomAbstractCoreController
{
    /**
     * @param User $user
     * @param AutomaticAccountMarkerType $type
     * @param bool $enable
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route(path: '/all/{type}', name: 'delete_marker_all', defaults: ['enable' => false], methods: ['DELETE'])]
    #[Route(path: '/all/{type}', name: 'restore_marker_all', defaults: ['enable' => true], methods: ['PUT'])]
    public function modifyAll(
        #[MapEntity(mapping: ['user' => 'id'])]
        User $user,
        AutomaticAccountMarkerType $type,
        bool $enable,
        EntityManagerInterface $em,
    ): JsonResponse {
        $user->getActiveAutomaticAccountMarkersFor( $type )
            ->map( fn(AutomaticAccountMarker $marker) => $marker->setEnabled( $enable ) );

        $em->persist( $user );
        $em->flush();
        return new JsonResponse(['success' => true]);
    }

    /**
     * @param bool $enable
     * @param AutomaticAccountMarker $marker
     * @param EntityManagerInterface $em
     * @return JsonResponse
     */
    #[Route(path: '/{id}', name: 'delete_marker', defaults: ['enable' => false], methods: ['DELETE'])]
    #[Route(path: '/{id}', name: 'restore_marker', defaults: ['enable' => true], methods: ['PUT'])]
    public function modify(
        bool $enable,
        #[MapEntity(mapping: ['id' => 'id', 'user' => 'user'])]
        AutomaticAccountMarker $marker,
        EntityManagerInterface $em,
    ): JsonResponse {
        if (Carbon::createFromImmutable( $marker->getExpiresAt() )->isPast())
            return new JsonResponse([], Response::HTTP_NOT_ACCEPTABLE);

        $em->persist( $marker->setEnabled( $enable ) );
        $em->flush();
        return new JsonResponse(['success' => true]);
    }
}
