<?php

namespace App\Repository;

use App\Entity\UserSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserSession>
 *
 * @method UserSession|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserSession|null findOneBy(array $criteria, array $orderBy = null)
 * @method UserSession[]    findAll()
 * @method UserSession[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserSession::class);
    }

    public function findActiveSessionsByUser($user)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.user = :val')
            ->andWhere('s.isActive = :active')
            ->setParameter('val', $user)
            ->setParameter('active', true)
            ->orderBy('s.lastUsedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
