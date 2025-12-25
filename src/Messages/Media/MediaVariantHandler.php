<?php

namespace App\Messages\Media;

use App\Entity\Media;
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
        ParameterBagInterface $parameterBag
    ) {
        $this->public = $parameterBag->get('kernel.project_dir') . '/public/storage';
    }

    #[AsMessageHandler]
    public function createVariant( CreateMediaVariantMessage $message ): void {
        /** @var Media $media */
        $media = $this->em->getRepository(Media::class)->find( $message->uuid );
        if (!$media) return;

        $variant = new AnonymousMediaVariant( $message->variantData );
        $original = "{$this->public}/{$media->getUrl( )}";

        $raw = $variant->process( new ImageManager( Driver::class, strip: true )->read( $original ) );
        if (!$variant->enabledFor( $raw )) return;

        $image = $variant->performEncode($raw);

        $ext = MediaService::mimeTypeToExtension( $image->mimetype(), true );
        $targetUrl = $media->getTargetUrl( $message->variantName, Uuid::v4()->toString() . $ext );
        if ($targetUrl === null) return;

        $savePath = "{$this->public}/{$targetUrl}";
        $dir = dirname( $savePath );
        if (!is_dir($dir)) mkdir( $dir, recursive: true );

        $image->save( $savePath );

        if ($media->hasConversion($message->variantName))
            unlink( "{$this->public}/{$media->getUrl( $message->variantName )}" );

        $this->em->persist( $media->setConversion( $message->variantName, $targetUrl, $raw, $image, $variant ) );
        $this->em->flush();

    }

}
