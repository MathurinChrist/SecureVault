<?php

namespace App\Repository;

use App\Entity\VaultPermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VaultPermission>
 */
class VaultPermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VaultPermission::class);
    }

    public function findByCode(string $code): ?VaultPermission
    {
        return $this->findOneBy(['code' => $code]);
    }
}
