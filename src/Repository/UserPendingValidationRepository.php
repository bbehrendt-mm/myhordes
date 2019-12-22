<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\UserPendingValidation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\NonUniqueResultException;

/**
 * @method UserPendingValidation|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserPendingValidation|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserPendingValidation[]    findAll()
 * @method UserPendingValidation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserPendingValidationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserPendingValidation::class);
    }

    public function findOneByToken(string $value): ?UserPendingValidation
    {
        try {
            return $this->createQueryBuilder('uv')
                ->andWhere('uv.pkey = :val')->setParameter('val', $value)
                ->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) { return null; }
    }

    public function findOneByUser(User $user): ?UserPendingValidation
    {
        try {
            return $this->createQueryBuilder('uv')
                ->andWhere('uv.user = :val')->setParameter('val', $user->getId())
                ->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) { return null; }
    }

    public function findOneByTokenAndUser(string $value, ?User $user = null): ?UserPendingValidation
    {
        try {
            $query = $this->createQueryBuilder('uv')
                ->andWhere('uv.pkey = :key')->setParameter('key', $value);
            if ($user)
                $query->andWhere('uv.user = :uid')->setParameter('uid', $user->getId());

            return $query->getQuery()->getOneOrNullResult();
        } catch (NonUniqueResultException $e) { return null; }
    }

    // /**
    //  * @return UserPendingValidation[] Returns an array of UserPendingValidation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?UserPendingValidation
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
