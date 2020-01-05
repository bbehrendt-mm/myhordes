<?php

namespace App\Repository;

use App\Entity\RequireItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method RequireItem|null find($id, $lockMode = null, $lockVersion = null)
 * @method RequireItem|null findOneBy(array $criteria, array $orderBy = null)
 * @method RequireItem[]    findAll()
 * @method RequireItem[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RequireItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RequireItem::class);
    }

    // /**
    //  * @return RequireItem[] Returns an array of RequireItem objects
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
    public function findOneBySomeField($value): ?RequireItem
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
