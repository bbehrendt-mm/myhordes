<?php

namespace App\EventListener\DoctrineExtensions;

use App\Entity\Media;
use App\Entity\Morph;
use App\EventListener\ContainerTypeTrait;
use App\Service\Actions\Cache\InvalidateTagsInAllPoolsAction;
use App\Traits\Entity\DoctrineExtensions;
use App\Traits\Entity\LinksMedia;
use App\Traits\Entity\LinksMorph;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\OnClearEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\PreUpdateEventArgs;
use Exception;
use FilesystemIterator;
use Psr\Container\ContainerInterface;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

#[AsEntityListener(event: Events::prePersist, method: 'queueToStorage', entity: Media::class)]
#[AsEntityListener(event: Events::postPersist, method: 'writeToStorage', entity: Media::class)]
#[AsEntityListener(event: Events::preRemove, method: 'deleteFromStorage', entity: Media::class)]
#[AsEntityListener(event: Events::postUpdate, method: 'flushCaches', entity: Media::class)]
#[AsDoctrineListener(event: Events::loadClassMetadata)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
#[AsDoctrineListener(event: Events::onClear)]
class TraitExtensionListener implements ServiceSubscriberInterface
{
    use ContainerTypeTrait;

    private readonly string $public;

    /**
     * @var string[]
     */
    private array $directories_to_delete = [];
    private array $caches_to_invalidate = [];

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
        return [
            MessageBusInterface::class,
            InvalidateTagsInAllPoolsAction::class,
        ];
    }

    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs): void
    {
        $classMetadata = $eventArgs->getClassMetadata();
        if (in_array( Morph::class, class_parents( $classMetadata->getName() ) )) {
            $prefix = strtolower(new ReflectionClass($classMetadata->getName())->getShortName()); // you can omit this line and ...
            $classMetadata->table['indexes']["{$prefix}_morph_relation"] =
                ['columns' => ['model_type', 'model_id']
            ];
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void {
        $entity = $args->getObject();

        if (in_array(DoctrineExtensions::class, class_uses($entity))) {
            /** @var DoctrineExtensions $entity */

            if ($entity::usesDoctrineTrait(LinksMorph::class)) {
                /** @var LinksMorph $entity */
                $morphClasses = $entity::getMorphTo();
                foreach ($morphClasses as $morphClass) {
                    $entities = $args->getObjectManager()->getRepository($morphClass)->findBy([
                        'modelType' => $args->getObjectManager()->getClassMetadata($entity::class)->getName(),
                        'modelID' => $entity->getPrimaryKey()
                    ]);
                    foreach ($entities as $morph) {
                        /** @var Morph $morph */
                        if ($morph->chainDelete( $entity )) $args->getObjectManager()->remove($morph);
                        else $args->getObjectManager()->persist($morph);
                    }
                }
            }

            if ($entity::usesDoctrineTrait(LinksMedia::class)) {
                /** @var LinksMedia $entity */
                $this->directories_to_delete[] = $entity->getMediaBasePath();
            }
        }
    }

    /**
     * @throws Exception
     */
    public function onFlush(OnFlushEventArgs $args): void {
        foreach ($this->media_to_persist as $media) {
            if (!$media->transientImage || !$media->transientOwner)
                throw new Exception("New media entity has no transientImage or transientOwner set!");

            if ($media->getModelID() === null || $media->getStorage() === null || $media->getCreatedAt() === null) {
                $media
                    ->setModelID($media->transientOwner->getPrimaryKey())
                    ->buildStoragePath($media->transientOwner->getMediaPath())
                    ->setCreatedAt(new DateTimeImmutable());

                $args->getObjectManager()->getUnitOfWork()->recomputeSingleEntityChangeSet(
                    $args->getObjectManager()->getClassMetadata(Media::class),
                    $media
                );
            }
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

        if (!empty($this->caches_to_invalidate)) {
            $this->getService(InvalidateTagsInAllPoolsAction::class)($this->caches_to_invalidate);
            $this->caches_to_invalidate = [];
        }
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

        $this->getService(InvalidateTagsInAllPoolsAction::class)([
            ...$media->relatedCaches,
            "media_{$media->getMangledClassName()}_{$media->getModelID()}!{$media->getCollection()}"
        ]);

        if ($media->autoVariants) {
            $bus = $this->getService(MessageBusInterface::class);
            foreach ($media->pendingConversionMessages() as $message)
                $bus->dispatch( $message->appendToRelatedCaches( $media->relatedCaches ) );
        }
    }

    public function deleteFromStorage(Media $media): void {
        $this->directories_to_delete[] = $media->getStorage();
        $this->caches_to_invalidate[] = "media_{$media->getMangledClassName()}_{$media->getModelID()}!{$media->getCollection()}";
    }

    public function flushCaches(Media $media): void {
        $this->getService(InvalidateTagsInAllPoolsAction::class)([
            ...$media->relatedCaches,
            "media_{$media->getMangledClassName()}_{$media->getModelID()}!{$media->getCollection()}"
        ]);
    }
}
