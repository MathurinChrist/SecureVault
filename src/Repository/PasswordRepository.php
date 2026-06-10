<?php

namespace App\Repository;

use App\Entity\Password;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Password>
 */
class PasswordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Password::class);
    }

    public function findRecentByUser($user, int $limit = 5)
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC'], $limit);
    }

    public function countByUser($user)
    {
        return $this->count(['user' => $user]);
    }
}
