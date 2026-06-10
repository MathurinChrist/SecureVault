<?php

namespace App\Repository;

use App\Entity\PasswordEntry;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PasswordEntry>
 */
class PasswordEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordEntry::class);
    }

    /** @return PasswordEntry[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->addSelect('v')
            ->join('p.vault', 'v')
            ->where('v.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
