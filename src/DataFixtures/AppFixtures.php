<?php

namespace App\DataFixtures;

use App\Entity\Alert;
use App\Entity\LoginAttempt;
use App\Entity\Notification;
use App\Entity\PasswordEntry;
use App\Entity\Role;
use App\Entity\User;
use App\Entity\Vault;
use App\Entity\VaultPermission;
use App\Service\EncryptionService;
use App\Service\VaultKeyService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const SHARED_KEY_VERSION = 0;

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
        private readonly EncryptionService $encryptionService,
        private readonly VaultKeyService $vaultKeyService,
        #[Autowire(env: 'VAULT_ENCRYPTION_KEY')] private readonly string $sharedEncryptionKey,
    ) {}

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $this->loadRoles($manager);
        $this->loadVaultPermissions($manager);
        $manager->flush();

        $adminUser    = $this->loadAdminUser($manager);
        $regularUsers = $this->loadRegularUsers($manager, $faker, 9);
        $manager->flush();

        $allUsers = array_merge([$adminUser], $regularUsers);

        foreach ($allUsers as $user) {
            $vaults = $this->loadVaultsForUser($manager, $faker, $user, random_int(1, 3));
            $this->loadPasswordEntriesForVaults($manager, $faker, $vaults, $user);
            $this->loadAlertsForUser($manager, $faker, $user, random_int(1, 4));
            $this->loadNotificationsForUser($manager, $faker, $user, random_int(2, 5));
            $this->loadLoginAttemptsForUser($manager, $faker, $user, random_int(3, 10));
        }

        $manager->flush();
    }

    // -------------------------------------------------------------------------

    private function loadRoles(ObjectManager $manager): void
    {
        $roles = [
            [Role::ROLE_USER,    'Utilisateur standard',    'Accès standard à l\'application'],
            [Role::ROLE_MANAGER, 'Manager',                 'Gestion des utilisateurs et coffres'],
            [Role::ROLE_ADMIN,   'Administrateur',          'Accès complet à l\'administration'],
        ];

        foreach ($roles as [$name, $label, $description]) {
            if ($manager->getRepository(Role::class)->findOneBy(['name' => $name])) {
                continue;
            }
            $role = (new Role())->setName($name)->setDescription($description);
            $manager->persist($role);
        }
    }

    private function loadVaultPermissions(ObjectManager $manager): void
    {
        $perms = [
            [VaultPermission::READ,  'Lecture',        'Consultation uniquement'],
            [VaultPermission::WRITE, 'Écriture',       'Lecture + ajout / modification'],
            [VaultPermission::ADMIN, 'Administration', 'Accès complet + partage'],
        ];

        foreach ($perms as [$code, $name, $description]) {
            if ($manager->getRepository(VaultPermission::class)->findOneBy(['code' => $code])) {
                continue;
            }
            $perm = (new VaultPermission())->setCode($code)->setName($name)->setDescription($description);
            $manager->persist($perm);
        }
    }

    private function loadAdminUser(ObjectManager $manager): User
    {
        $existing = $manager->getRepository(User::class)->findOneBy(['email' => 'admin@securevault.local']);
        if ($existing) {
            return $existing;
        }

        $admin = (new User())
            ->setEmail('admin@securevault.local')
            ->setFirstName('Admin')
            ->setLastName('SecureVault')
            ->setRoles(['ROLE_ADMIN', 'ROLE_USER'])
            ->setEmailVerified(true)
            ->setEncryptionKey($this->vaultKeyService->generateSalt());

        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin1234!'));
        $manager->persist($admin);

        return $admin;
    }

    /** @return User[] */
    private function loadRegularUsers(ObjectManager $manager, \Faker\Generator $faker, int $count): array
    {
        $users = [];

        $testAccounts = [
            ['alice@securevault.local', 'Alice',   'Martin',   'User1234!'],
            ['bob@securevault.local',   'Bob',     'Dupont',   'User1234!'],
            ['carol@securevault.local', 'Carol',   'Bernard',  'User1234!'],
        ];

        foreach ($testAccounts as [$email, $first, $last, $pass]) {
            if ($manager->getRepository(User::class)->findOneBy(['email' => $email])) {
                continue;
            }
            $user = (new User())
                ->setEmail($email)
                ->setFirstName($first)
                ->setLastName($last)
                ->setRoles(['ROLE_USER'])
                ->setEmailVerified(true)
                ->setEncryptionKey($this->vaultKeyService->generateSalt());

            $user->setPassword($this->hasher->hashPassword($user, $pass));
            $manager->persist($user);
            $users[] = $user;
        }

        for ($i = 0; $i < $count - count($testAccounts); $i++) {
            $email = $faker->unique()->safeEmail();
            if ($manager->getRepository(User::class)->findOneBy(['email' => $email])) {
                continue;
            }
            $user = (new User())
                ->setEmail($email)
                ->setFirstName($faker->firstName())
                ->setLastName($faker->lastName())
                ->setRoles(['ROLE_USER'])
                ->setEmailVerified($faker->boolean(80))
                ->setEncryptionKey($this->vaultKeyService->generateSalt());

            $user->setPassword($this->hasher->hashPassword($user, 'User1234!'));
            $manager->persist($user);
            $users[] = $user;
        }

        return $users;
    }

    /** @return Vault[] */
    private function loadVaultsForUser(ObjectManager $manager, \Faker\Generator $faker, User $user, int $count): array
    {
        $categories = ['Personnel', 'Travail', 'Finance', 'Shopping', 'Réseaux sociaux', 'Divertissement'];
        $vaults     = [];

        for ($i = 0; $i < $count; $i++) {
            $vault = (new Vault())
                ->setName($faker->randomElement($categories) . ' ' . $faker->word())
                ->setDescription($faker->boolean(60) ? $faker->sentence() : null)
                ->setArchived($faker->boolean(10))
                ->setUser($user);

            $manager->persist($vault);
            $vaults[] = $vault;
        }

        return $vaults;
    }

    private function loadPasswordEntriesForVaults(ObjectManager $manager, \Faker\Generator $faker, array $vaults, User $user): void
    {
        $services = [
            ['Google',      'https://google.com'],
            ['GitHub',      'https://github.com'],
            ['Netflix',     'https://netflix.com'],
            ['Amazon',      'https://amazon.fr'],
            ['LinkedIn',    'https://linkedin.com'],
            ['Twitter',     'https://twitter.com'],
            ['Spotify',     'https://spotify.com'],
            ['PayPal',      'https://paypal.com'],
            ['Dropbox',     'https://dropbox.com'],
            ['Slack',       'https://slack.com'],
            ['Apple',       'https://appleid.apple.com'],
            ['Microsoft',   'https://microsoft.com'],
        ];

        $sharedKey = hash('sha256', $this->sharedEncryptionKey, true);

        foreach ($vaults as $vault) {
            $entryCount = random_int(2, 5);
            shuffle($services);

            for ($i = 0; $i < $entryCount && isset($services[$i]); $i++) {
                [$title, $url] = $services[$i];
                $plaintext = $faker->password(10, 20);

                $entry = (new PasswordEntry())
                    ->setTitle($title)
                    ->setUsername($faker->boolean(70) ? $faker->email() : $faker->userName())
                    ->setEncryptedPassword($this->encryptionService->encrypt($plaintext, $sharedKey))
                    ->setKeyVersion(self::SHARED_KEY_VERSION)
                    ->setUrl($url)
                    ->setNotes($faker->boolean(30) ? $faker->sentence() : null)
                    ->setFavorite($faker->boolean(20))
                    ->setVault($vault)
                    ->setUser($user);

                $manager->persist($entry);
            }
        }
    }

    private function loadAlertsForUser(ObjectManager $manager, \Faker\Generator $faker, User $user, int $count): void
    {
        $types = [
            ['Mot de passe faible détecté',     'danger',  'security',
             'Un de vos mots de passe est trop court ou trop simple. Changez-le dès que possible.'],
            ['Mot de passe non modifié depuis 6 mois', 'warning', 'password_age',
             'Ce mot de passe n\'a pas été mis à jour depuis plus de 6 mois. Il est recommandé de le changer régulièrement.'],
            ['Connexion depuis un nouvel appareil', 'info',   'login',
             'Une connexion a été détectée depuis un appareil ou une localisation inhabituelle.'],
            ['Fuite de données détectée',       'danger',  'breach',
             'Votre adresse e-mail a été trouvée dans une fuite de données. Modifiez vos mots de passe concernés.'],
            ['Authentification 2FA désactivée', 'warning', 'two_factor',
             'La double authentification est désactivée sur votre compte. Activez-la pour une meilleure sécurité.'],
        ];

        for ($i = 0; $i < $count; $i++) {
            [$title, $type, $category, $description] = $faker->randomElement($types);

            $alert = (new Alert())
                ->setTitle($title)
                ->setDescription($description)
                ->setType($type)
                ->setCategory($category)
                ->setIsRead($faker->boolean(30))
                ->setUser($user);

            $manager->persist($alert);
        }
    }

    private function loadNotificationsForUser(ObjectManager $manager, \Faker\Generator $faker, User $user, int $count): void
    {
        $templates = [
            ['Bienvenue sur SecureVault !',      Notification::TYPE_SUCCESS, 'Votre compte a été créé avec succès. Explorez vos fonctionnalités.'],
            ['Nouveau partage reçu',             Notification::TYPE_SHARE,   'Un utilisateur a partagé un coffre avec vous.'],
            ['Alerte de sécurité',               Notification::TYPE_SECURITY,'Activité suspecte détectée sur votre compte.'],
            ['Mise à jour disponible',           Notification::TYPE_INFO,    'Une nouvelle version de SecureVault est disponible.'],
            ['Score de sécurité amélioré',       Notification::TYPE_SUCCESS, 'Votre score de sécurité a augmenté suite à vos récentes modifications.'],
        ];

        for ($i = 0; $i < $count; $i++) {
            [$title, $type, $message] = $faker->randomElement($templates);

            $notif = (new Notification())
                ->setTitle($title)
                ->setMessage($message)
                ->setType($type)
                ->setIsRead($faker->boolean(40))
                ->setUser($user);

            $manager->persist($notif);
        }
    }

    private function loadLoginAttemptsForUser(ObjectManager $manager, \Faker\Generator $faker, User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $attempt = (new LoginAttempt())
                ->setUser($user)
                ->setIpAddress($faker->ipv4())
                ->setSuccess($faker->boolean(85));

            $manager->persist($attempt);
        }
    }
}
