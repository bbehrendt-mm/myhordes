<?php

namespace App\EventListener\Media;

use App\Entity\Media;
use App\EventListener\ContainerTypeTrait;
use App\Messages\Media\CreateMediaVariantMessage;
use App\Service\Media\MediaService;
use App\Traits\Entity\LinksMedia;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Exception;
use FilesystemIterator;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

#[AsEntityListener(event: Events::prePersist, method: 'queueToStorage', entity: Media::class)]
#[AsEntityListener(event: Events::postPersist, method: 'writeToStorage', entity: Media::class)]
#[AsEntityListener(event: Events::preRemove, method: 'deleteFromStorage', entity: Media::class)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsDoctrineListener(event: Events::onClear)]
class MediaListener implements ServiceSubscriberInterface
{
    use ContainerTypeTrait;

    private readonly string $public;

    /**
     * @var string[]
     */
    private array $directories_to_delete = [];

    /**
     * @var Media[]
     */
    private array $media_to_persist = [];

    public function __construct(
        private readonly ContainerInterface $container,
        ParameterBagInterface $parameterBag
    ) {
        $this->public = $parameterBag->get('kernel.project_dir') . '/public/storage';
    }

    public static function getSubscribedServices(): array
    {
        return [MessageBusInterface::class, MediaService::class];
    }

    public function preRemove(PreRemoveEventArgs $args): void {
        /** @var LinksMedia $entity */
        $entity = $args->getObject();

        if (in_array(LinksMedia::class, class_uses($entity))) {
            $entities = $args->getObjectManager()->getRepository(Media::class)->findBy([
                'modelType' => $args->getObjectManager()->getClassMetadata($entity::class)->getName(),
                'modelID' => $entity->getPrimaryKey()
            ]);

            foreach ($entities as $media) $args->getObjectManager()->remove($media);
            $this->directories_to_delete[] = $entity->getMediaBasePath();
        }
    }

    /**
     * @throws Exception
     */
    public function onFlush(OnFlushEventArgs $args): void {
        foreach ($this->media_to_persist as $media) {
            if (!$media->transientImage || !$media->transientOwner)
                throw new Exception("New media entity has no transientImage or transientOwner set!");

            $media
                ->setModelID( $media->transientOwner->getPrimaryKey() )
                ->buildStoragePath( $media->transientOwner->getMediaPath() )
                ->setCreatedAt(new DateTimeImmutable());
            $args->getObjectManager()->getUnitOfWork()->recomputeSingleEntityChangeSet(
                $args->getObjectManager()->getClassMetadata(Media::class),
                $media
            );
        }
    }

    public function postFlush(PostFlushEventArgs $args): void {
        foreach ($this->directories_to_delete as $delete_dir) {
            $dir = "{$this->public}/{$delete_dir}";
            if (is_dir($dir)) {
                $iterator = new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS);
                foreach ((new RecursiveIteratorIterator($iterator, RecursiveIteratorIterator::CHILD_FIRST)) as $file)
                    if ($file->isDir()) rmdir($file->getPathname());
                    else unlink($file->getPathname());
                rmdir($dir);
            }
        }
        $this->directories_to_delete = [];
    }

    public function onClear(OnClearEventArgs $args): void {
        $this->directories_to_delete = [];
        $this->media_to_persist = [];
    }

    public function queueToStorage(Media $media): void {
        $this->media_to_persist[] = $media;
    }

    public function writeToStorage(Media $media): void {
        $full = $this->public . "/{$media->getUrl()}";
        $dir = dirname($full);
        if (!is_dir($dir)) mkdir( dirname( $full ), recursive: true );
        $media->transientImage?->save($full);

        if ($media->autoVariants) {
            $bus = $this->getService(MessageBusInterface::class);
            $mediaService = $this->getService(MediaService::class);

            foreach ($mediaService->getCollectionForMedia($media)->getVariants( $media->transientImage ) as $variant)
                $bus->dispatch( new CreateMediaVariantMessage( $media->getId(), $variant->name, $variant->serialize() ) );
        }
    }

    public function deleteFromStorage(Media $media): void {
        $this->directories_to_delete[] = $media->getStorage();
    }
}
