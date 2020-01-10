<?php

namespace App\Repository;

use App\Entity\Citizen;
use App\Entity\DigTimer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method DigTimer|null find($id, $lockMode = null, $lockVersion = null)
 * @method DigTimer|null findOneBy(array $criteria, array $orderBy = null)
 * @method DigTimer[]    findAll()
 * @method DigTimer[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DigTimerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DigTimer::class);
    }

    public function findActiveByCitizen(Citizen $c): ?DigTimer
    {
        if (!$c->getZone()) return null;
        try {
            return $this->createQueryBuilder('d')
                ->andWhere('d.citizen = :ctz')->setParameter('ctz', $c)
                ->andWhere('d.zone = :zne')->setParameter('zne', $c->getZone())
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException $e) {
            return null;
        }
    }

    // /**
    //  * @return DigTimer[] Returns an array of DigTimer objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('d.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?DigTimer
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
