<?php

namespace App\Service\Actions\User;

use App\Entity\Media;
use App\Entity\NotificationSubscription;
use App\Entity\Picto;
use App\Entity\PictoPrototype;
use App\Entity\PictoRollup;
use App\Entity\Season;
use App\Entity\TownRankingProxy;
use App\Entity\User;
use App\Enum\UserSetting;
use App\Service\Locksmith;
use App\Service\Media\MediaService;
use ArrayHelpers\Arr;
use Carbon\Carbon;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class RecalculateMediaExpirationAction
{
    public function __construct(
        private EntityManagerInterface $em,
        private MediaService $mediaService,
        private Locksmith $locksmith,
    ) { }

    private static function earliest(?DateTimeImmutable $current, Carbon $check ): DateTimeImmutable {
        if ($current === null) return $check->toDateTimeImmutable();
        $current = Carbon::createFromImmutable( $current );
        if ($current->isBefore($check)) return $current->toDateTimeImmutable();
        else return $check->toDateTimeImmutable();
    }

    public function __invoke(User $user, bool $recalculate = false): void
    {
        $must_delete = [];
        $expiration = [];

        $current_avatar = $this->mediaService->getSingleMediaForObject( $user, 'avatar' );
        $avatar_history = $this->mediaService->getMediaForObject( $user, 'avatar-history' );

        $all = array_filter([
            $current_avatar?->getId()?->toString(),
            ...$avatar_history->map( fn(Media $m) => $m->getId()->toString() )->toArray(),
        ]);

        $objects_with_source = 0;
        foreach ($avatar_history as $avatar_history_item) {
            if (array_intersect($avatar_history_item->getMetaKey('copies', []), $all)) {
                if ($objects_with_source) $must_delete[] = $avatar_history_item->getId()->toString();
                else $expiration[$avatar_history_item->getId()->toString()] = self::earliest( $avatar_history_item->getDeleteAt(), new Carbon()->addDay() );

                $objects_with_source++;
            } elseif ( $avatar_history_item->getMetaKey('source', '') === $current_avatar?->getId()?->toString() ) {
                if ($objects_with_source) $must_delete[] = $avatar_history_item->getId()->toString();
                else $expiration[$avatar_history_item->getId()->toString()] = self::earliest( $avatar_history_item->getDeleteAt(), new Carbon()->addDay() );

                $objects_with_source++;
            }
        }


        $totalSize = $current_avatar ? $this->mediaService->getTotalMediaSize( $current_avatar ) : 0;
        $position = 0;

        foreach ($avatar_history as $avatar_history_item) {
            if (in_array($avatar_history_item->getId(), $must_delete)) continue;

            $totalSize += $this->mediaService->getTotalMediaSize( $avatar_history_item );

            if (!array_key_exists($avatar_history_item->getId()->toString(), $expiration)) {
                if ($position === 0) $expiration[$avatar_history_item->getId()->toString()] = null;
                elseif ($totalSize <= 1048576) {
                    if ($recalculate) $expiration[$avatar_history_item->getId()->toString()] = null;
                }
                elseif ($totalSize <= 2097152) $expiration[$avatar_history_item->getId()->toString()] = self::earliest( $recalculate ? null : $avatar_history_item->getDeleteAt(), new Carbon()->addDays(30) );
                elseif ($totalSize <= 4194304) $expiration[$avatar_history_item->getId()->toString()] = self::earliest( $recalculate ? null : $avatar_history_item->getDeleteAt(), new Carbon()->addDays(7) );
                elseif ($totalSize <= 6291456) $expiration[$avatar_history_item->getId()->toString()] = self::earliest( $recalculate ? null : $avatar_history_item->getDeleteAt(), new Carbon()->addDay() );
                else $must_delete[] = $avatar_history_item->getId()->toString();
            }

            $position++;
        }

        $this->em->clear();
        foreach ($must_delete as $id) {
            $lock = $this->locksmith->waitForLock("media_{$id}_process", 2);
            $media = $this->em->getRepository(Media::class)->find($id);
            if ($media) {
                $this->em->remove($media);
                $this->em->flush();
                $this->em->clear();
            }
            $lock->release();
        }

        foreach ($expiration as $id => $date) {
            $lock = $this->locksmith->waitForLock("media_{$id}_process", 2);
            $media = $this->em->getRepository(Media::class)->find($id);
            if ($media) {
                $this->em->persist($media->setDeleteAt($date));
                $this->em->flush();
                $this->em->clear();
            }
            $lock->release();
        }
    }
}
