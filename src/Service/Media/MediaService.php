<?php
namespace App\Service\Media;

use App\Entity\Media;
use App\Structures\MediaCollection;
use App\Traits\Entity\LinksMedia;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Order;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Symfony\Component\Uid\Uuid;

readonly class MediaService
{

    public function __construct(
        private EntityManagerInterface $entityManager
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
     * @param LinksMedia $object
     * @param string $collection
     * @return Collection
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function getMediaForObject(object $object, string $collection): Collection {
        if (!$this->checkTrait($object)) return new ArrayCollection();

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
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     * @throws Exception
     */
    public function getSingleMediaForObject(object $object, string $collection): ?Media {
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
     * @param string $path
     * @param string $collection
     * @param string|null $filename
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     */
    public function addMediaToObjectFromFile(object $object, string $path, string $collection, ?string $filename = null): ?Media {
        if (!$this->checkTrait($object)) return null;

        $collectionObject = $object::mediaCollection($collection);
        if ($collectionObject === null) return null;

        $image = new ImageManager( Driver::class, strip: true )->read($path);
        $mime = mime_content_type($path);

        $conversions = [];
        foreach ($collectionObject->getVariants( $image ) as $variant)
            $conversions[] = $variant->name;

        $media = new Media()
            ->setId( Uuid::v7() )
            ->setCollection($collection)
            ->setModelType( $this->entityManager->getClassMetadata($object::class)->getName() )
            ->setFilename($filename ?? basename($path))
            ->setMime( $mime )
            ->setMetaFromImage( $image, mime: $mime, size: filesize($path) )
            ->registerConversion( $conversions );

        $this->attachImageToObjectCollection($object, $collectionObject, $media, $image);
        return $media;
    }

    /**
     * @param LinksMedia $object
     * @param string $data
     * @param string $mime
     * @param string $collection
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
     */
    public function addMediaToObjectFromBinaryString(object $object, string $data, ?string $mime, string $collection): ?Media {
        if (!$this->checkTrait($object)) return null;

        $collectionObject = $object::mediaCollection($collection);
        if ($collectionObject === null) return null;

        $image = new ImageManager( Driver::class, strip: true )->read($data);
        $mime ??= $image->encode()->mimetype();

        $conversions = [];
        foreach ($collectionObject->getVariants( $image ) as $variant)
            $conversions[] = $variant->name;

        $media = new Media()
            ->setId( Uuid::v7() )
            ->setCollection($collection)
            ->setModelType( $this->entityManager->getClassMetadata($object::class)->getName() )
            ->setFilename("blob" . self::mimeTypeToExtension($mime))
            ->setMime( $mime )
            ->setMetaFromImage( $image, mime: $mime, size: strlen($data) )
            ->registerConversion( $conversions );

        $this->attachImageToObjectCollection($object, $collectionObject, $media, $image);
        return $media;
    }

    /**
     * @param LinksMedia $object
     * @param resource $data
     * @param string $mime
     * @param string $collection
     * @return Media|null
     * @noinspection PhpDocSignatureInspection
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
