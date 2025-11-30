<?php

namespace App\Messages\Media;

use App\Entity\Media;
use App\Service\Media\MediaService;
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
        private MediaService $mediaService,
        private EntityManagerInterface $em,
        ParameterBagInterface $parameterBag
    ) {
        $this->public = $parameterBag->get('kernel.project_dir') . '/public/storage';
    }

    #[AsMessageHandler]
    public function createVariant( CreateMediaVariantMessage $message ): void {
        /** @var Media $media */
        $media = $this->em->getRepository(Media::class)->find( $message->uuid );
        if (!$media) return;

        if ($media->hasConversion($message->variant) && !$message->force)
            return;

        $variant = $this->mediaService->getCollectionForMedia( $media )?->getVariant( $message->variant );
        if (!$variant) return;

        $original = "{$this->public}/{$media->getUrl( )}";

        $image = $variant->performEncode(
            $variant->process( new ImageManager( Driver::class, strip: true )->read( $original ) )
        );

        $ext = MediaService::mimeTypeToExtension( $image->mimetype(), true );
        $targetUrl = $media->getTargetUrl( $message->variant, Uuid::v4()->toString() . $ext );
        if ($targetUrl === null) return;

        $savePath = "{$this->public}/{$targetUrl}";
        $dir = dirname( $savePath );
        if (!is_dir($dir)) mkdir( $dir, recursive: true );

        $image->save( $savePath );

        if ($media->hasConversion($message->variant))
            unlink( "{$this->public}/{$media->getUrl( $message->variant )}" );

        $this->em->persist( $media->setConversion( $message->variant, $targetUrl ) );
        $this->em->flush();

    }

}
