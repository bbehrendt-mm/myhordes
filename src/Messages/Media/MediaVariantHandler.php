<?php

namespace App\Messages\Media;

use App\Entity\Media;
use App\Entity\User;
use App\Service\Actions\Cache\InvalidateTagsInAllPoolsAction;
use App\Service\Actions\Mercure\BroadcastViaMercureAction;
use App\Service\Locksmith;
use App\Service\Media\MediaService;
use App\Structures\Media\AnonymousMediaVariant;
use Doctrine\ORM\EntityManagerInterface;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

readonly class MediaVariantHandler
{
    private string $public;

    public function __construct(
        private EntityManagerInterface $em,
        private MediaService $mediaService,
        private Locksmith $locksmith,
        private BroadcastViaMercureAction $broadcast,
        ParameterBagInterface $parameterBag,
    ) {
        $this->public = $parameterBag->get('kernel.project_dir') . '/public/storage';
    }

    #[AsMessageHandler]
    public function createVariant( CreateMediaVariantMessage $message ): void {
        $lock = $this->locksmith->waitForLock("media_{$message->uuid}_process", 120);

        /** @var Media $media */
        $media = $this->em->getRepository(Media::class)->find( $message->uuid );
        if (!$media) return;

        $variant = new AnonymousMediaVariant( $message->variantData );
        $original = "{$this->public}/{$media->getUrl( )}";

        $raw = $variant->process( new ImageManager( Driver::class, strip: true )->read( $original ) );
        if (!$variant->enabledFor( $raw )) return;

        $image = $variant->performEncode($raw);

        $this->mediaService->storePreConvertedImageAsConversion(
            $media, $raw, $image, $message->variantName, $variant
        );

        $this->em->persist( $media );
        $this->em->flush();

        $lock->release();

        if ($media->getModelType() === User::class) {
            $owner = $this->em->getRepository(User::class)->find($media->getModelID());
            $conversion = $media->getConversion($message->variantName);
            if ($owner && $conversion)
                ($this->broadcast)(
                       'related-media-update',
                       [
                           'media' => $media->getId(),
                           'collection' => $media->getCollection(),
                           'data' => [
                               'id' => $conversion->conversion,
                               'url' => $conversion->url,
                               'format' => $conversion->mime,
                               'size' => $conversion->size,
                               'x' => $conversion->width,
                               'y' => $conversion->height,
                               'f' => $conversion->frames
                           ]
                       ],
                    users: $owner
                );
        }
    }

}
