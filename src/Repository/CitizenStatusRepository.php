<?php

namespace App\Repository;

use App\Entity\CitizenStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method CitizenStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method CitizenStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method CitizenStatus[]    findAll()
 * @method CitizenStatus[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CitizenStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CitizenStatus::class);
    }

    // /**
    //  * @return CitizenStatus[] Returns an array of CitizenStatus objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('c.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?CitizenStatus
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
