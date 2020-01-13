<?php

namespace App\Repository;

use App\Entity\RequireLocation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method RequireLocation|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequireLocation|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequireLocation[]    findAll()
 * @method RequireLocation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequireLocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequireLocation::class);
    }

    // /**
    //  * @return RequireLocation[] Returns an array of RequireLocation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('r.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?RequireLocation
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
