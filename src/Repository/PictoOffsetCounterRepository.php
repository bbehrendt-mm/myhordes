<?php

namespace App\Repository;

use App\Entity\PictoOffsetCounter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PictoOffsetCounter>
 *
 * @method PictoOffsetCounter|null find($id, $lockMode = null, $lockVersion = null)
 * @method PictoOffsetCounter|null findOneBy(array $criteria, array $orderBy = null)
 * @method PictoOffsetCounter[]    findAll()
 * @method PictoOffsetCounter[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PictoOffsetCounterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PictoOffsetCounter::class);
    }

    //    /**
    //     * @return PictoOffsetCounter[] Returns an array of PictoOffsetCounter objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?PictoOffsetCounter
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
