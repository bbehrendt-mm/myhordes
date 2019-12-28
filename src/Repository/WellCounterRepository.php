<?php

namespace App\Repository;

use App\Entity\WellCounter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method WellCounter|null find($id, $lockMode = null, $lockVersion = null)
 * @method WellCounter|null findOneBy(array $criteria, array $orderBy = null)
 * @method WellCounter[]    findAll()
 * @method WellCounter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WellCounterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WellCounter::class);
    }

    // /**
    //  * @return WellCounter[] Returns an array of WellCounter objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('w.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?WellCounter
    {
        return $this->createQueryBuilder('w')
            ->andWhere('w.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
