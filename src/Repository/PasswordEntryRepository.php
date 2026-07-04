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

    public function findRecentByUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countOldByUser(User $user, int $days = 180): int
    {
        $threshold = new \DateTimeImmutable("-{$days} days");

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->andWhere('p.createdAt < :threshold')
            ->setParameter('user', $user)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return PasswordEntry[] */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return PasswordEntry[] */
    public function searchByUser(User $user, string $query): array
    {
        $q = '%' . mb_strtolower(trim($query)) . '%';

        return $this->createQueryBuilder('p')
            ->leftJoin('p.vault', 'v')
            ->where('p.user = :user')
            ->andWhere(
                'LOWER(p.title) LIKE :q OR LOWER(p.username) LIKE :q OR LOWER(p.url) LIKE :q OR LOWER(v.name) LIKE :q'
            )
            ->setParameter('user', $user)
            ->setParameter('q', $q)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
