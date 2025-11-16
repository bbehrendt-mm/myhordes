<?php

namespace App\Controller\REST\Admin\Forum;

use App\Annotations\GateKeeperProfile;
use App\Controller\CustomAbstractCoreController;
use App\Entity\Post;
use App\Entity\User;
use App\Entity\UserModerationNote;
use App\Service\Forum\PostService;
use App\Service\JSONRequestParser;
use Carbon\Carbon;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use function Symfony\Component\Clock\now;

#[Route(path: '/rest/v1/admin/forum', name: 'rest_admin_forum_management_', condition: "request.headers.get('Accept') === 'application/json'")]
#[IsGranted('ROLE_CROW')]
#[GateKeeperProfile('skip')]
class ForumManagementController extends CustomAbstractCoreController
{
    /**
     * @param User $user
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     * @param PostService $postService
     * @return JsonResponse
     */
    #[Route(path: '/user/{id}', name: 'purge_user_posts', methods: ['DELETE'])]
    public function purgeUserPosts(User $user, EntityManagerInterface $em, TranslatorInterface $translator, PostService $postService): JsonResponse {

        $success = 0;
        foreach ($em->getRepository(Post::class)->findBy(['owner' => $user, 'hidden' => false]) as $post)
            if ($postService->hidePost( $post, $this->getUser(), checkModPermissions: false, addCrowAnnounce: false, purgeNotifications: false ))
                $success++;

        $this->addFlash( 'notice', $translator->trans('Es wurden {n} Posts gelöscht.', [
            'n' => $success,
        ], 'admin') );

        return new JsonResponse([ 'success' => $success > 0 ]);
    }

    /**
     * @param User $user
     * @param EntityManagerInterface $em
     * @param TranslatorInterface $translator
     * @param JSONRequestParser $request
     * @return JsonResponse
     */
    #[Route(path: '/user/{id}/notes', name: 'add_user_note', methods: ['PUT'])]
    public function addUserNote(User $user, EntityManagerInterface $em, TranslatorInterface $translator, JSONRequestParser $request): JsonResponse {

        if (!$request->has('text', true)) return new JsonResponse([], 400);

        $em->persist( (new UserModerationNote())
            ->setOwner( $this->getUser() )
            ->setUser( $user )
            ->setCreatedAt( Carbon::now()->toDateTimeImmutable() )
            ->setText( $request->get('text') )
        );

        $em->flush();

        return new JsonResponse([ 'success' => true ]);
    }

}
