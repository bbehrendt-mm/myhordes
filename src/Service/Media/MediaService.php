<?php
namespace App\Service\Media;

use App\Entity\Media;
use App\Structures\Media\MediaCollection;
use App\Structures\Media\MediaVariantInterface;
use App\Traits\Entity\LinksMedia;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

readonly class MediaService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ParameterBagInterface $parameterBag
    ) {}

    private function checkTrait(object $object): bool {
        return in_array(LinksMedia::class, class_uses($object));
    }

    private function getMediaCriteria(string $type, string $id, string $collection): Criteria {
        return Criteria::create()->orderBy(['createdAt' => Order::Descending])
            ->where( Criteria::expr()->eq( 'modelType', $type ) )
            ->andWhere( Criteria::expr()->eq( 'modelID', $id ) )
            ->andWhere( Criteria::expr()->eq( 'collection', $collection ) );
    }

    /**
     * @param LinksMedia $object
     * @param string $collection
     * @return int
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function countMediaForObject(object $object, string $collection): int {
        if (!$this->checkTrait($object)) return 0;

        $collectionObject = $object::mediaCollection($collection);
        if ($collectionObject === null) return 0;

        $criteria = $this->getMediaCriteria(
            $this->entityManager->getClassMetadata($object::class)->getName(),
            $object->getPrimaryKey(),
            $collection
        );

        return $this->entityManager->getRepository(Media::class)->matching($criteria)->count();
    }

    /**
     * @param LinksMedia $object
     * @param string $collection
     * @return bool
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function hasMediaForObject(object $object, string $collection): bool {
        return $this->countMediaForObject($object, $collection) > 0;
    }

    /**
     * @param LinksMedia|null $object
     * @param string $collection
     * @return Collection
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function getMediaForObject(?object $object, string $collection): Collection {
        if (!$object || !$this->checkTrait($object)) return new ArrayCollection();

        $collectionObject = $object::mediaCollection($collection);
        if ($collectionObject === null) return new ArrayCollection();

        $criteria = $this->getMediaCriteria(
            $this->entityManager->getClassMetadata($object::class)->getName(),
            $object->getPrimaryKey(),
            $collection
        );

        if ($collectionObject?->isSingleFile()) $criteria->setMaxResults(1);
        return $this->entityManager->getRepository(Media::class)->matching($criteria);
    }

    /**
     * @param LinksMedia $object
     * @param string $collection
     * @return void
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function clearMediaFromObject(object $object, string $collection): void {
        foreach ( $this->getMediaForObject($object, $collection) as $media )
            $this->entityManager->remove($media);
    }

    /**
     * @param LinksMedia|null $object
     * @param string $collection
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function getSingleMediaForObject(?object $object, string $collection): ?Media {
        $collection = $this->getMediaForObject($object, $collection);
        return $collection->count() === 1 ? $collection->first() : null;
    }

    /**
     * @param Media $media
     * @return LinksMedia|null
     * @noinspection PhpDocSignatureInspection
     */
    public function getObjectForMedia(Media $media): object {
        return $this->entityManager->getRepository( $media->getModelType() )->find( $media->getModelID() );
    }

    public function getCollectionForMedia(Media $media): ?MediaCollection {
        return $this->getObjectForMedia($media)::mediaCollection($media->getCollection());
    }

    private function attachImageToObjectCollection(object $object, MediaCollection $collection, Media $media, ImageInterface $image): void {
        $model_type = $this->entityManager->getClassMetadata($object::class)->getName();
        $model_id = $object->tryPrimaryKey();

        $media->transientImage = $image;
        $media->transientOwner = $object;

        $this->entityManager->persist($media);

        if ($model_id !== null && $collection->isSingleFile())
            foreach ($this->entityManager->getRepository(Media::class)->matching($this->getMediaCriteria( $model_type, $model_id, $collection->name )) as $m)
                $this->entityManager->remove($m);
    }

    /**
     * @param LinksMedia $object
     * @param ImageInterface $image
     * @param string $collection
     * @param string $filename
     * @param string $mime
     * @param int $size
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function addMediaToObjectFromImage(object $object, ImageInterface $image, string $collection, string $filename, string $mime, int $size): ?Media {
        if (!$this->checkTrait($object)) return null;

        $collectionObject = $object::mediaCollection($collection);
        if ($collectionObject === null) return null;

        $media = new Media()
            ->setId( Uuid::v7() )
            ->setCollection($collection)
            ->setModelType( $this->entityManager->getClassMetadata($object::class)->getName() )
            ->setFilename($filename)
            ->setMime( $mime )
            ->setMetaFromImage( $image, mime: $mime, size: $size )
            ->setCreatedAt( new \DateTimeImmutable() );

        if ($object->tryPrimaryKey() !== null)
            $media
                ->setModelID( $object->getPrimaryKey() )
                ->buildStoragePath( $object->getMediaPath() );

        foreach ($collectionObject->getVariants( $image ) as $variant)
            $media->registerConversion( $variant->name, $variant->tags, $variant->serialize() );

        $this->attachImageToObjectCollection($object, $collectionObject, $media, $image);
        return $media;
    }

    /**
     * @param LinksMedia $object
     * @param string $path
     * @param string $collection
     * @param string|null $filename
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     */
    public function addMediaToObjectFromFile(object $object, string $path, string $collection, ?string $filename = null): ?Media {
        return $this->addMediaToObjectFromImage(
            $object,
            new ImageManager( Driver::class, strip: true )->read($path),
            $collection,
            $filename ?? basename($path),
            mime_content_type($path),
            filesize($path)
        );
    }

    /**
     * @param LinksMedia $object
     * @param string $data
     * @param string $mime
     * @param string $collection
     * @param string|null $filename
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function addMediaToObjectFromBinaryString(object $object, string $data, ?string $mime, string $collection, ?string $filename = null): ?Media {
        $image = new ImageManager( Driver::class, strip: true )->read($data);
        $mime ??= $image->encode()->mimetype();
        return $this->addMediaToObjectFromImage(
            $object,
            $image,
            $collection,
            ($filename ?? 'blob') . self::mimeTypeToExtension($mime),
            $mime,
            strlen($data)
        );
    }


    /**
     * @param Media $media
     * @param ImageInterface $image
     * @param EncodedImageInterface $encoded
     * @param string $variantName
     * @param MediaVariantInterface|null $variant
     * @return bool
     */
    public function storePreConvertedImageAsConversion(Media $media, ImageInterface $image, EncodedImageInterface $encoded, string $variantName, ?MediaVariantInterface $variant = null): bool {

        $public = "{$this->parameterBag->get('kernel.project_dir')}/public/storage";

        $ext = self::mimeTypeToExtension( $encoded->mimetype(), true );
        $targetUrl = $media->getTargetUrl( $variantName, Uuid::v4()->toString() . $ext );
        if ($targetUrl === null) return false;

        $savePath = "{$public}/{$targetUrl}";
        $dir = dirname( $savePath );
        if (!is_dir($dir)) mkdir( $dir, recursive: true );

        $encoded->save( $savePath );

        if ($media->hasConversion($variantName))
            unlink( "{$public}/{$media->getUrl( $variantName )}" );

        $media->setConversion( $variantName, $targetUrl, $image, $encoded, $variant );
        return true;
    }

    public function getObjectIDsWithMedia(string $entityClass, string $collection): array {
        return $this->entityManager->getRepository(Media::class)
            ->createQueryBuilder('q')
            ->select('q.modelID')->distinct()
            ->where('q.collection = :collection')->setParameter('collection', $collection)
            ->andWhere('q.modelType = :modelType')->setParameter('modelType', $entityClass)
            ->getQuery()->getSingleColumnResult();
    }

    /**
     * @param LinksMedia $object
     * @param resource $data
     * @param string $mime
     * @param string $collection
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function addMediaToObjectFromResource(object $object, mixed $data, string $mime, string $collection): ?Media {
        return $this->addMediaToObjectFromBinaryString($object, stream_get_contents($data), $mime, $collection);
    }

    public static function mimeTypeToExtension(string $mime, bool $dot = true): string {

        $ext = match($mime) {
            'image/jpeg', 'image/jpe' => 'jpg',
            'image/tiff' => 'tif',
            default => null
        };

        if ($ext === null && str_starts_with($mime, 'image/')) $ext = substr($mime, 6);

        if ($ext === null) return '';
        else return $dot ? ".$ext" : $ext;
    }

    public static function extensionToMimeType(string $ext): string {
        $ext = ltrim($ext, '.');
        return "image/$ext";
    }
}
