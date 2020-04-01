<?php

namespace App\Repository;

use App\Entity\TrashCounter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method TrashCounter|null find($id, $lockMode = null, $lockVersion = null)
 * @method TrashCounter|null findOneBy(array $criteria, array $orderBy = null)
 * @method TrashCounter[]    findAll()
 * @method TrashCounter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrashCounterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrashCounter::class);
    }

    // /**
    //  * @return TrashCounter[] Returns an array of TrashCounter objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?TrashCounter
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
