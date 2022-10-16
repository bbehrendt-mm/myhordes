<?php

namespace App\Repository;

use App\Entity\ItemCategory;
use App\Entity\RuinZonePrototype;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method RuinZonePrototype|null find($id, $lockMode = null, $lockVersion = null)
 * @method RuinZonePrototype|null findOneBy(array $criteria, array $orderBy = null)
 * @method RuinZonePrototype[]    findAll()
 * @method RuinZonePrototype[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RuinZonePrototypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RuinZonePrototype::class);
    }

    /**
     * @return RuinZonePrototype[]
     */
    public function findLocked()
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.keyImprint IS NOT NULL')
            ->andWhere('z.level = 0')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return RuinZonePrototype[]
     */
    public function findUnlocked()
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.keyImprint IS NULL')
            ->andWhere('z.level = 0')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return RuinZonePrototype[]
     */
    public function findUp()
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.level = 1')
            ->getQuery()
            ->getResult()
            ;
    }

    /**
     * @return RuinZonePrototype[]
     */
    public function findDown()
    {
        return $this->createQueryBuilder('z')
            ->andWhere('z.level = -1')
            ->getQuery()
            ->getResult()
            ;
    }

    // /**
    //  * @return RuinZonePrototype[] Returns an array of RuinZonePrototype objects
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
    public function findOneBySomeField($value): ?RuinZonePrototype
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
