<?php

namespace App\Repository;

use App\Entity\Town;
use App\Entity\Gazette;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;
use Exception;

/**
 * @method Gazette|null find($id, $lockMode = null, $lockVersion = null)
 * @method Gazette|null findOneBy(array $criteria, array $orderBy = null)
 * @method Gazette[]    findAll()
 * @method Gazette[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GazetteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Gazette::class);
    }

    // /**
    //  * @return Gazette[] Returns an array of Gazette objects
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
    public function findOneBySomeField($value): ?Gazette
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
