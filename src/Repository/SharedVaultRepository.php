<?php

namespace App\Repository;

use App\Entity\SharedVault;
use App\Entity\User;
use App\Entity\Vault;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SharedVault>
 */
class SharedVaultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SharedVault::class);
    }

    /** @return SharedVault[] */
    public function findPendingForUser(User $user): array
    {
        return $this->createQueryBuilder('sv')
            ->where('sv.recipient = :user')
            ->andWhere('sv.accepted = false')
            ->setParameter('user', $user)
            ->orderBy('sv.sharedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return SharedVault[] */
    public function findAcceptedForUser(User $user): array
    {
        return $this->createQueryBuilder('sv')
            ->where('sv.recipient = :user')
            ->andWhere('sv.accepted = true')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    public function findByVaultAndRecipient(Vault $vault, User $user): ?SharedVault
    {
        return $this->findOneBy(['vault' => $vault, 'recipient' => $user]);
    }
}
