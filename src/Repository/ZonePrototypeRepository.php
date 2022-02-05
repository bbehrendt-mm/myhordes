<?php

namespace App\Repository;

use App\Entity\ItemCategory;
use App\Entity\ZonePrototype;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method ZonePrototype|null find($id, $lockMode = null, $lockVersion = null)
 * @method ZonePrototype|null findOneBy(array $criteria, array $orderBy = null)
 * @method ZonePrototype[]    findAll()
 * @method ZonePrototype[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ZonePrototypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ZonePrototype::class);
    }

    public function findOneByLabel(string $value): ?ZonePrototype
    {
        try {
            return $this->createQueryBuilder('z')
                ->andWhere('z.label = :val')
                ->setParameter('val', $value)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }

    /**
     * @param int $distance
     * @param bool $explorable
     * @return ZonePrototype[] Returns an array of ZonePrototype objects
     */
    public function findByDistance(int $distance, bool $explorable = false)
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.minDistance <= :dist')->andWhere('z.maxDistance >= :dist')->setParameter('dist', $distance)
            ->andWhere('z.explorable = :exp')->setParameter('exp', $explorable)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param bool $explorable
     * @return int Minimum ruin distance (AP)
     */
    public function findMinRuinDistance(bool $explorable = false): int
    {
        try {
            return $this->createQueryBuilder('z')
                ->select('MIN(z.minDistance)')
                ->andWhere('z.explorable = :exp')->setParameter('exp', $explorable)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 1;
        }
    }

    /**
     * @param bool $explorable
     * @return int Maximum ruin distance (AP)
     */
    public function findMaxRuinDistance(bool $explorable = false): int
    {
        try {
            return $this->createQueryBuilder('z')
                ->select('MAX(z.maxDistance)')
                ->andWhere('z.explorable = :exp')->setParameter('exp', $explorable)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (\Exception $e) {
            return 28;
        }
    }

    // /**
    //  * @return ZonePrototype[] Returns an array of ZonePrototype objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('z.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?ZonePrototype
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
