<?php

namespace App\DataFixtures;

use App\Entity\Role;
use App\Entity\User;
use App\Entity\VaultPermission;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $this->loadRoles($manager);
        $this->loadVaultPermissions($manager);
        $this->loadAdminUser($manager);

        $manager->flush();
    }

    private function loadRoles(ObjectManager $manager): void
    {
        $roles = [
            [Role::ROLE_USER,    'Utilisateur',    'Accès standard à l\'application'],
            [Role::ROLE_MANAGER, 'Manager',        'Gestion des utilisateurs et des coffres'],
            [Role::ROLE_ADMIN,   'Administrateur', 'Accès complet à l\'administration'],
        ];

        foreach ($roles as [$name, $label, $description]) {
            if ($manager->getRepository(Role::class)->findOneBy(['name' => $name]) !== null) {
                continue;
            }

            $role = new Role();
            $role->setName($name)->setDescription($description);
            $manager->persist($role);
        }
    }

    private function loadVaultPermissions(ObjectManager $manager): void
    {
        $permissions = [
            [VaultPermission::READ,  'Lecture',        'Consultation uniquement'],
            [VaultPermission::WRITE, 'Écriture',       'Lecture + ajout / modification'],
            [VaultPermission::ADMIN, 'Administration', 'Accès complet + partage'],
        ];

        foreach ($permissions as [$code, $name, $description]) {
            $existing = $manager->getRepository(VaultPermission::class)->findOneBy(['code' => $code]);
            if ($existing !== null) {
                continue;
            }

            $perm = new VaultPermission();
            $perm->setCode($code)->setName($name)->setDescription($description);
            $manager->persist($perm);
        }
    }

    private function loadAdminUser(ObjectManager $manager): void
    {
        $existing = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@securevault.local']);
        if ($existing !== null) {
            return;
        }

        $admin = new User();
        $admin->setEmail('admin@securevault.local')
              ->setFirstName('Admin')
              ->setLastName('SecureVault')
              ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
              ->setPassword($this->hasher->hashPassword($admin, 'Admin1234!'))
              ->setEmailVerified(true);

        $manager->persist($admin);
    }
}
