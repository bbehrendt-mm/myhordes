<?php

namespace App\Service\Morph;

use App\Entity\Morph;
use App\Traits\Entity\DoctrineExtensions;
use App\Traits\Entity\LinksMorph;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

readonly class MorphService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    private function checkTrait(object $object): bool {
        if (!in_array(DoctrineExtensions::class, class_uses($object))) return false;
        /** @var DoctrineExtensions $object */
        return $object::usesDoctrineTrait( LinksMorph::class );
    }

    private function checkMorph(object $object, string $morphClass): bool {
        if (!in_array(DoctrineExtensions::class, class_uses($object))) return false;
        /** @var DoctrineExtensions $object */
        if (!$object::usesDoctrineTrait( LinksMorph::class )) return false;
        /** @var LinksMorph $object */
        return in_array( $this->entityManager->getClassMetadata($morphClass)->getName(), $object::getMorphTo() );
    }

    private function getMorphCriteria(string $type, string $id): Criteria {
        return Criteria::create(true)
            ->where( Criteria::expr()->eq( 'modelType', $type ) )
            ->andWhere( Criteria::expr()->eq( 'modelID', $id ) );
    }

    /**
     * Returns a collection of morphs for the specified object.
     *
     * @template T of Morph
     * @psalm-param class-string<T> $morphClass
     * @psalm-param object<LinksMorph> $object
     * @return Collection<T>
     * @throws Exception
     */
    public function getMorphCollection(string $morphClass, object $object): Collection {
        if (!$this->checkMorph( $object, $morphClass )) return new ArrayCollection();

        /** @var LinksMorph $object */
        return $this->entityManager->getRepository( $morphClass )
            ->matching( $this->getMorphCriteria(
                $this->entityManager->getClassMetadata($object::class)->getName(),
                $object->getPrimaryKey()
            ) );
    }

    /**
     * Creates a new morph for the specified object.
     *
     * @template T of Morph
     * @psalm-param class-string<T> $morphClass
     * @psalm-param object<LinksMorph> $object
     * @return T
     * @throws Exception
     */
    public function createMorph(string $morphClass, object $object): Morph {
        if (!$this->checkMorph( $object, $morphClass ))
            throw new Exception( "Unable to create a morph of type $morphClass for object of type " . get_class( $object ) );

        return new $morphClass()
            ->setModelType( $this->entityManager->getClassMetadata($object::class)->getName() )
            ->setModelID( $object->getPrimaryKey() );
    }

    /**
     * Fetches the first morph for the specified object or creates a new one.
     *
     * @template T of Morph
     * @psalm-param class-string<T> $morphClass
     * @psalm-param object<LinksMorph> $object
     * @return T
     * @throws Exception
     */
    public function firstOrCreateMorph(string $morphClass, object $object): Morph {
        if (!$this->checkMorph( $object, $morphClass ))
            throw new Exception( "Unable to create a morph of type $morphClass for object of type " . get_class( $object ) );

        /** @var LinksMorph $object */
        return $this->entityManager->getRepository( $morphClass )
            ->matching( $this->getMorphCriteria(
                $this->entityManager->getClassMetadata($object::class)->getName(),
                $object->getPrimaryKey()
            ) )->first() ?: new $morphClass()
            ->setModelType( $this->entityManager->getClassMetadata($object::class)->getName() )
            ->setModelID( $object->getPrimaryKey() );
    }

    /**
     * Attach an existing morph to the specified object.
     *
     * @template T of Morph
     * @psalm-param T $morph
     * @psalm-param object<LinksMorph> $object
     * @return T
     * @throws Exception
     */
    public function attachMorph(Morph $morph, object $object): Morph {
        if (!$this->checkMorph( $object, $morph::class ))
            throw new Exception( "Unable to attach a morph of type " . $morph::class . " to an object of type " . get_class( $object ) );

        return $morph
            ->setModelType( $this->entityManager->getClassMetadata($object::class)->getName() )
            ->setModelID( $object->getPrimaryKey() );
    }

}
