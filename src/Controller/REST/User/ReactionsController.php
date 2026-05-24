<?php

namespace App\Controller\REST\User;

use App\Annotations\GateKeeperProfile;
use App\Controller\Admin\AdminActionController;
use App\Controller\CustomAbstractCoreController;
use App\Entity\AdminReport;
use App\Entity\BlackboardEdit;
use App\Entity\Citizen;
use App\Entity\CitizenRankingProxy;
use App\Entity\Emotes;
use App\Entity\ExternalApp;
use App\Entity\ForumUsagePermissions;
use App\Entity\GlobalPrivateMessage;
use App\Entity\Post;
use App\Entity\PrivateMessage;
use App\Entity\Reaction;
use App\Entity\ReactionSet;
use App\Entity\Town;
use App\Entity\TownAdminUser;
use App\Entity\User;
use App\Entity\UserGroup;
use App\Entity\UserGroupAssociation;
use App\Enum\AdminReportSpecification;
use App\Service\Actions\Mercure\BroadcastViaMercureAction;
use App\Service\CrowService;
use App\Service\EventProxyService;
use App\Service\JSONRequestParser;
use App\Service\Locksmith;
use App\Service\Media\MediaService;
use App\Service\PermissionHandler;
use App\Service\RandomGenerator;
use App\Service\RateLimitingFactoryProvider;
use App\Service\TimeKeeperService;
use App\Service\User\UserCapabilityService;
use App\Translation\T;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Url;
use Symfony\Component\Validator\Validation;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

#[Route(path: '/rest/v1/user/reactions', name: 'rest_user_reactions_', condition: "request.headers.get('Accept') === 'application/json'")]
#[GateKeeperProfile('skip')]
class ReactionsController extends CustomAbstractCoreController
{
    /**
     * @param Packages $asset
     * @return JsonResponse
     */
    #[Route(path: '', name: 'base', methods: ['GET'])]
    public function index(Packages $asset): JsonResponse {

        return new JsonResponse([
            'add'    => $this->translator->trans('Neue Reaktion hinzufügen...', [], 'global'),
            'agree'  => $this->translator->trans('Diese Reaktion hinzufügen', [], 'global'),
            'remove' => $this->translator->trans('Meine Reaktion entfernen', [], 'global'),
            'close'  => $this->translator->trans('Menü schließen', [], 'global'),
        ]);
    }

    private function renderReactions(ReactionSet $reactionSet, EntityManagerInterface $em): array {
        $data = $em->getRepository( Reaction::class )
            ->createQueryBuilder('r')
            ->select('count(r.id) as n', 'e.path as path', 'e.id as id', 'e.i18n as i18n')
            ->where('r.parent = :parent')->setParameter('parent', $reactionSet->getId(), UuidType::NAME)
            ->join('r.emote', 'e')
            ->groupBy('e.id')
            ->orderBy('n', 'DESC')
            ->getQuery()->getArrayResult();
        dump($data);
        return $data;
    }

    private function renderCachedReaction(bool $clear, User $user, ReactionSet $reactionSet, Packages $asset, EntityManagerInterface $em, TagAwareCacheInterface $gameCachePool): array {
        $key = "reaction_{$reactionSet->getId()}";
        if ($clear) $gameCachePool->delete($key);

        $reactions = new ArrayCollection( $gameCachePool->get( $key, function(ItemInterface $item) use ($em, $reactionSet) {
            $item->expiresAfter( 604800 );
            return $this->renderReactions( $reactionSet, $em );
        }))->map( fn(array $r) => [
            'id' => $r['id'],
            'path' => $asset->getUrl( $r['i18n']
                ? str_replace('{lang}', $user->getLanguage() ?? 'de', $r['path'])
                : $r['path']
            ),
            'count' => $r['n'],
        ]);

        return [
            'id' => $reactionSet->getId(),
            'me' => $user->getId(),
            'own' => $reactionSet->getReactionFor( $user )?->getEmote()?->getId(),
            'reactions' => $reactions->toArray(),
        ];
    }

    /**
     * @param ReactionSet $reactions
     * @param EntityManagerInterface $em
     * @param UserCapabilityService $capability
     * @param TagAwareCacheInterface $gameCachePool
     * @param Packages $asset
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route(path: '/{id}', name: 'get_reaction', methods: ['GET'])]
    public function get_reaction(
        ReactionSet $reactions,
        EntityManagerInterface $em,
        UserCapabilityService $capability,
        TagAwareCacheInterface $gameCachePool,
        Packages $asset,
    ): JsonResponse {
        if (!$this->getUser() || !$capability->hasRole( $this->getUser(), 'ROLE_USER' ))
            return new JsonResponse([], Response::HTTP_FORBIDDEN);

        return new JsonResponse($this->renderCachedReaction( false, $this->getUser(), $reactions, $asset, $em, $gameCachePool ));
    }

    /**
     * @param ReactionSet $reactions
     * @param Emotes $emote
     * @param EntityManagerInterface $em
     * @param UserCapabilityService $capability
     * @param TagAwareCacheInterface $gameCachePool
     * @param Packages $asset
     * @param Locksmith $locksmith
     * @return JsonResponse
     */
    #[Route(path: '/{id}/{emote}', name: 'put_reaction', methods: ['PUT'])]
    public function put_reaction(
        #[MapEntity(id: 'id')]
        ReactionSet $reactions,
        #[MapEntity(id: 'emote')]
        Emotes $emote,
        EntityManagerInterface $em,
        UserCapabilityService $capability,
        TagAwareCacheInterface $gameCachePool,
        Packages $asset,
        Locksmith $locksmith,
    ): JsonResponse {
        if (!$this->getUser() || !$capability->hasRole( $this->getUser(), 'ROLE_USER' ))
            return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $lock = $locksmith->getAcquiredLock( "modify_reaction_{$reactions->getId()}");
        $em->persist(($reactions->getReactionFor( $this->getUser() ) ?? new Reaction()
            ->setOwner( $this->getUser() )
            ->setParent( $reactions )
        )->setEmote( $emote ));
        $em->flush();
        $lock->release();

        return new JsonResponse($this->renderCachedReaction( true, $this->getUser(), $reactions, $asset, $em, $gameCachePool ));
    }

    /**
     * @param ReactionSet $reactions
     * @param EntityManagerInterface $em
     * @param UserCapabilityService $capability
     * @param TagAwareCacheInterface $gameCachePool
     * @param Packages $asset
     * @param Locksmith $locksmith
     * @return JsonResponse
     */
    #[Route(path: '/{id}', name: 'delete_reaction', methods: ['DELETE'])]
    public function delete_reaction(
        #[MapEntity(id: 'id')]
        ReactionSet $reactions,
        EntityManagerInterface $em,
        UserCapabilityService $capability,
        TagAwareCacheInterface $gameCachePool,
        Packages $asset,
        Locksmith $locksmith,
    ): JsonResponse {
        if (!$this->getUser() || !$capability->hasRole( $this->getUser(), 'ROLE_USER' ))
            return new JsonResponse([], Response::HTTP_FORBIDDEN);

        $lock = $locksmith->getAcquiredLock( "modify_reaction_{$reactions->getId()}");
        $reaction = $reactions->getReactionFor( $this->getUser() );
        if ($reaction) {
            $em->remove($reaction);
            $em->flush();
        }
        $lock->release();

        return new JsonResponse($this->renderCachedReaction( !!$reaction, $this->getUser(), $reactions, $asset, $em, $gameCachePool ));
    }

}
