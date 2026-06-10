# Plan d'Exécution SecureVault - DDD & Architecture Hexagonale
**Équipe de 3 Développeurs** | **PHP 8.4+** | **Symfony 8.x**

---

## Architecture Hexagonale & DDD

### Structure des Bounded Contexts

```
src/
├── Domain/                    # Cœur du métier (indépendant des frameworks)
│   ├── Authentication/        # Contexte: Authentification
│   │   ├── Entity/
│   │   │   ├── User.php
│   │   │   ├── Role.php
│   │   │   └── Permission.php
│   │   ├── ValueObject/
│   │   │   ├── Email.php
│   │   │   ├── HashedPassword.php
│   │   │   └── Token.php
│   │   ├── Repository/
│   │   │   ├── UserRepositoryInterface.php
│   │   │   └── RoleRepositoryInterface.php
│   │   ├── Service/
│   │   │   ├── PasswordHasherInterface.php
│   │   │   └── EmailVerifierInterface.php
│   │   └── Event/
│   │       ├── UserRegistered.php
│   │       └── UserLoggedIn.php
│   │
│   ├── Vault/                 # Contexte: Gestion des coffres
│   │   ├── Entity/
│   │   │   ├── Vault.php (Aggregate Root)
│   │   │   ├── PasswordEntry.php (Entity)
│   │   │   ├── Category.php (Value Object)
│   │   │   └── Tag.php (Value Object)
│   │   ├── ValueObject/
│   │   │   ├── VaultName.php
│   │   │   ├── EncryptedPassword.php
│   │   │   └── VaultColor.php
│   │   ├── Repository/
│   │   │   ├── VaultRepositoryInterface.php
│   │   │   └── PasswordEntryRepositoryInterface.php
│   │   ├── Service/
│   │   │   ├── PasswordGeneratorInterface.php
│   │   │   ├── PasswordEncrypterInterface.php
│   │   │   └── PasswordStrengthCheckerInterface.php
│   │   └── Event/
│   │       ├── VaultCreated.php
│   │       ├── PasswordAdded.php
│   │       └── VaultArchived.php
│   │
│   ├── Sharing/               # Contexte: Partage de coffres
│   │   ├── Entity/
│   │   │   ├── SharedVault.php (Aggregate Root)
│   │   │   └── VaultPermission.php (Value Object)
│   │   ├── ValueObject/
│   │   │   ├── SharingPermission.php
│   │   │   └── SharingStatus.php
│   │   ├── Repository/
│   │   │   └── SharedVaultRepositoryInterface.php
│   │   ├── Service/
│   │   │   └── VaultSharingServiceInterface.php
│   │   └── Event/
│   │       ├── VaultShared.php
│   │       ├── SharingAccepted.php
│   │       └── PermissionChanged.php
│   │
│   ├── Security/              # Contexte: Sécurité & Alertes
│   │   ├── Entity/
│   │   │   ├── SecurityAlert.php
│   │   │   ├── ActivityLog.php
│   │   │   └── LoginAttempt.php
│   │   ├── ValueObject/
│   │   │   ├── AlertSeverity.php
│   │   │   ├── AlertType.php
│   │   │   └── IpAddress.php
│   │   ├── Repository/
│   │   │   └── SecurityAlertRepositoryInterface.php
│   │   ├── Service/
│   │   │   ├── BreachedPasswordCheckerInterface.php
│   │   │   └── SecurityMonitorInterface.php
│   │   └── Event/
│   │       ├── SecurityAlertTriggered.php
│   │       └── SuspiciousActivityDetected.php
│   │
│   └── Shared/                # Shared Kernel
│       ├── ValueObject/
│       │   ├── EmailAddress.php
│       │   ├── UserId.php
│       │   └── Timestamp.php
│       └── Event/
│           └── DomainEvent.php
│
├── Application/               # Cas d'utilisation (Application Services)
│   ├── Authentication/
│   │   ├── Command/
│   │   │   ├── RegisterUserCommand.php
│   │   │   ├── LoginUserCommand.php
│   │   │   └── ChangePasswordCommand.php
│   │   ├── Handler/
│   │   │   ├── RegisterUserHandler.php
│   │   │   ├── LoginUserHandler.php
│   │   │   └── ChangePasswordHandler.php
│   │   └── DTO/
│   │       ├── RegisterUserRequest.php
│   │       └── LoginUserResponse.php
│   │
│   ├── Vault/
│   │   ├── Command/
│   │   │   ├── CreateVaultCommand.php
│   │   │   ├── AddPasswordCommand.php
│   │   │   └── ArchiveVaultCommand.php
│   │   ├── Query/
│   │   │   ├── GetVaultQuery.php
│   │   │   └── ListVaultsQuery.php
│   │   ├── Handler/
│   │   │   └── ...
│   │   └── DTO/
│   │       └── ...
│   │
│   ├── Sharing/
│   │   ├── Command/
│   │   │   ├── ShareVaultCommand.php
│   │   │   ├── AcceptSharingCommand.php
│   │   │   └── UpdatePermissionCommand.php
│   │   ├── Handler/
│   │   │   └── ...
│   │   └── DTO/
│   │       └── ...
│   │
│   └── Security/
│       ├── Query/
│       │   ├── GetSecurityAlertsQuery.php
│       │   └── GetActivityLogQuery.php
│       ├── Handler/
│       │   └── ...
│       └── DTO/
│           └── ...
│
├── Infrastructure/            # Implémentations techniques
│   ├── Persistence/
│   │   ├── Doctrine/
│   │   │   ├── Repository/
│   │   │   │   ├── UserRepository.php
│   │   │   │   ├── VaultRepository.php
│   │   │   │   └── ...
│   │   │   ├── Entity/
│   │   │   │   └── ... (Entités Doctrine avec attributs ORM)
│   │   │   └── Mapping/
│   │   │       └── types/
│   │   │           └── ...
│   │   └── Repository/
│   │       └── ... (Repositories in-memory pour tests)
│   │
│   ├── Security/
│   │   ├── JWT/
│   │   │   ├── JwtAuthenticator.php
│   │   │   └── JwtTokenManager.php
│   │   └── PasswordHasher/
│   │       └── Argon2idHasher.php
│   │
│   ├── External/
│   │   ├── HaveIBeenPwned/
│   │   │   └── HaveIBeenPwnedService.php
│   │   └── Email/
│   │       ├── SymfonyMailer.php
│   │       └── EmailTemplates/
│   │
│   └── Event/
│       ├── Doctrine/
│       │   └── DoctrineEventDispatcher.php
│       └── Symfony/
│           └── SymfonyEventDispatcher.php
│
└── Presentation/              # Controllers, Templates, API
    ├── Web/
    │   ├── Controller/
    │   │   ├── AuthenticationController.php
    │   │   ├── VaultController.php
    │   │   └── ...
    │   └── Form/
    │       ├── RegistrationType.php
    │       └── VaultType.php
    │
    ├── Api/
    │   ├── Controller/
    │   │   ├── AuthApiController.php
    │   │   ├── VaultApiController.php
    │   │   └── ...
    │   ├── DTO/
    │   │   └── ...
    │   └── Serializer/
    │       └── Groups/
    │
    └── Admin/
        └── Controller/
            └── DashboardController.php
```

### Principes DDD à Appliquer

1. **Bounded Contexts** : Chaque contexte a ses propres règles métier
2. **Aggregates** : Vault est l'aggregate root pour PasswordEntry
3. **Value Objects** : Email, EncryptedPassword, etc. sont immuables
4. **Domain Events** : Événements métier déclenchés par les actions
5. **Repositories Interfaces** : Définies dans le Domain, implémentées dans l'Infrastructure
6. **Domain Services** : Logique métier complexe qui ne appartient pas à une entité

### Principes Architecture Hexagonale

1. **Domain** : Indépendant de tout framework, pure PHP
2. **Application** : Orchestre les cas d'utilisation
3. **Infrastructure** : Implémente les interfaces du Domain
4. **Presentation** : Connecte l'extérieur (HTTP) à l'Application

---

## Sommaire des Développeurs

| Développeur | Bounded Context | Focus |
|-------------|-----------------|-------|
| **Développeur 1** | Authentication | Authentification, Sécurité, JWT, CI/CD |
| **Développeur 2** | Vault | Coffres, Mots de passe, Encryption, API |
| **Développeur 3** | Sharing + Security | Partage, Notifications, Admin, Déploiement |

---

# Développeur 1 : Contexte Authentication

## Étape 1 : Domain Layer - Value Objects

### Créer `src/Domain/Authentication/ValueObject/Email.php`
```php
<?php

namespace App\Domain\Authentication\ValueObject;

readonly class Email
{
    private string $value;

    public function __construct(string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }
        $this->value = strtolower($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

### Créer `src/Domain/Authentication/ValueObject/HashedPassword.php`
```php
<?php

namespace App\Domain\Authentication\ValueObject;

readonly class HashedPassword
{
    private string $hash;

    public function __construct(string $hash)
    {
        if (str_starts_with($hash, '$argon2id$') === false) {
            throw new \InvalidArgumentException('Le hash doit utiliser Argon2id');
        }
        $this->hash = $hash;
    }

    public static function fromPlainPassword(string $plainPassword, PasswordHasherInterface $hasher): self
    {
        return new self($hasher->hash($plainPassword));
    }

    public function verify(string $plainPassword, PasswordHasherInterface $hasher): bool
    {
        return $hasher->verify($this->hash, $plainPassword);
    }

    public function hash(): string
    {
        return $this->hash;
    }
}
```

### Créer `src/Domain/Authentication/ValueObject/Token.php`
```php
<?php

namespace App\Domain\Authentication\ValueObject;

readonly class Token
{
    private string $value;

    public function __construct(string $value)
    {
        if (strlen($value) < 32) {
            throw new \InvalidArgumentException('Token trop court');
        }
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(32)));
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Token $other): bool
    {
        return hash_equals($this->value, $other->value);
    }
}
```

---

## Étape 2 : Domain Layer - Entities & Aggregates

### Créer `src/Domain/Shared/ValueObject/UserId.php`
```php
<?php

namespace App\Domain\Shared\ValueObject;

readonly class UserId
{
    public function __construct(
        public int|string $value
    ) {
    }

    public static function generate(): self
    {
        return new self(random_int(1, PHP_INT_MAX));
    }

    public function equals(UserId $other): bool
    {
        return $this->value === $other->value;
    }
}
```

### Créer `src/Domain/Authentication/Entity/User.php`
```php
<?php

namespace App\Domain\Authentication\Entity;

use App\Domain\Authentication\ValueObject\Email;
use App\Domain\Authentication\ValueObject\HashedPassword;
use App\Domain\Authentication\ValueObject\Token;
use App\Domain\Shared\ValueObject\UserId;
use App\Domain\Shared\Event\DomainEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class User
{
    private UserId $id;
    private Email $email;
    private HashedPassword $password;
    private string $firstName;
    private string $lastName;
    private bool $isActive;
    private bool $emailVerified;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private ?Token $verificationToken;
    private Collection $roles;
    private array $domainEvents = [];

    public function __construct(
        UserId $id,
        Email $email,
        HashedPassword $password,
        string $firstName,
        string $lastName
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->isActive = true;
        $this->emailVerified = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->roles = new ArrayCollection();

        $this->addDomainEvent(new UserRegistered($this));
    }

    public function verifyEmail(Token $token): void
    {
        if ($this->emailVerified) {
            return;
        }

        if (!$this->verification_token || !$this->verification_token->equals($token)) {
            throw new \InvalidArgumentException('Token invalide');
        }

        $this->emailVerified = true;
        $this->verification_token = null;
        $this->updatedAt = new \DateTimeImmutable();

        $this->addDomainEvent(new UserEmailVerified($this));
    }

    public function changePassword(HashedPassword $newPassword, HashedPassword $currentPassword): void
    {
        if (!$this->password->verify($currentPassword->hash(), new Argon2idHasher())) {
            throw new \InvalidArgumentException('Mot de passe actuel incorrect');
        }

        $this->password = $newPassword;
        $this->updatedAt = new \DateTimeImmutable();

        $this->addDomainEvent(new UserPasswordChanged($this));
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();

        $this->addDomainEvent(new UserDeactivated($this));
    }

    public function addDomainEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    // Getters
    public function id(): UserId { return $this->id; }
    public function email(): Email { return $this->email; }
    public function firstName(): string { return $this->firstName; }
    public function lastName(): string { return $this->lastName; }
    public function isActive(): bool { return $this->isActive; }
    public function isEmailVerified(): bool { return $this->emailVerified; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }
}
```

### Créer `src/Domain/Authentication/Entity/Role.php`
```php
<?php

namespace App\Domain\Authentication\Entity;

readonly class Role
{
    private const ROLE_USER = 'ROLE_USER';
    private const ROLE_MANAGER = 'ROLE_MANAGER';
    private const ROLE_ADMIN = 'ROLE_ADMIN';

    public function __construct(
        private string $name,
        private ?string $description = null
    ) {
        if (!in_array($name, [self::ROLE_USER, self::ROLE_MANAGER, self::ROLE_ADMIN], true)) {
            throw new \InvalidArgumentException('Rôle invalide');
        }
    }

    public static function user(): self
    {
        return new self(self::ROLE_USER, 'Utilisateur standard');
    }

    public static function manager(): self
    {
        return new self(self::ROLE_MANAGER, 'Gestionnaire de coffres');
    }

    public static function admin(): self
    {
        return new self(self::ROLE_ADMIN, 'Administrateur système');
    }

    public function name(): string { return $this->name; }
    public function description(): ?string { return $this->description; }
}
```

---

## Étape 3 : Domain Layer - Repository Interfaces & Events

### Créer `src/Domain/Authentication/Repository/UserRepositoryInterface.php`
```php
<?php

namespace App\Domain\Authentication\Repository;

use App\Domain\Authentication\Entity\User;
use App\Domain\Authentication\ValueObject\Email;
use App\Domain\Shared\ValueObject\UserId;

interface UserRepositoryInterface
{
    public function save(User $user): void;
    public function remove(User $user): void;
    public function findById(UserId $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function existsByEmail(Email $email): bool;
    /** @return User[] */
    public function findAll(): array;
}
```

### Créer `src/Domain/Authentication/Service/PasswordHasherInterface.php`
```php
<?php

namespace App\Domain\Authentication\Service;

interface PasswordHasherInterface
{
    public function hash(string $plainPassword): string;
    public function verify(string $hash, string $plainPassword): bool;
    public function needsRehash(string $hash): bool;
}
```

### Créer les Domain Events dans `src/Domain/Authentication/Event/`
- `UserRegistered.php`
- `UserEmailVerified.php`
- `UserPasswordChanged.php`
- `UserLoggedIn.php`
- `UserDeactivated.php`

### Créer `src/Domain/Shared/Event/DomainEvent.php` (interface de base)

---

## Étape 4 : Application Layer - Commands & Handlers

### Créer `src/Application/Authentication/Command/RegisterUserCommand.php`
```php
<?php

namespace App\Application\Authentication\Command;

readonly class RegisterUserCommand
{
    public function __construct(
        public string $email,
        public string $password,
        public string $firstName,
        public string $lastName
    ) {
    }
}
```

### Créer `src/Application/Authentication/Handler/RegisterUserHandler.php`
```php
<?php

namespace App\Application\Authentication\Handler;

use App\Application\Authentication\Command\RegisterUserCommand;
use App\Domain\Authentication\Repository\UserRepositoryInterface;
use App\Domain\Authentication\Service\PasswordHasherInterface;
use App\Domain\Authentication\ValueObject\Email;
use App\Domain\Authentication\ValueObject\HashedPassword;
use App\Domain\Shared\ValueObject\UserId;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RegisterUserHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private PasswordHasherInterface $passwordHasher
    ) {
    }

    public function __invoke(RegisterUserCommand $command): UserId
    {
        $email = new Email($command->email);
        
        if ($this->userRepository->existsByEmail($email)) {
            throw new \RuntimeException('Email déjà utilisé');
        }

        $user = new User(
            UserId::generate(),
            $email,
            HashedPassword::fromPlainPassword($command->password, $this->passwordHasher),
            $command->firstName,
            $command->lastName
        );

        $this->userRepository->save($user);

        return $user->id();
    }
}
```

### Créer les autres Commands et Handlers
- `LoginUserCommand.php` + `LoginUserHandler.php`
- `ChangePasswordCommand.php` + `ChangePasswordHandler.php`

---

## Étape 5 : Infrastructure Layer - Persistence

### Créer `src/Infrastructure/Persistence/Doctrine/Entity/UserDoctrineEntity.php`
```php
<?php

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use App\Domain\Authentication\Entity\User as DomainUser;
use App\Domain\Authentication\ValueObject\Email;
use App\Domain\Authentication\ValueObject\HashedPassword;
use App\Domain\Shared\ValueObject\UserId;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $password;

    #[ORM\Column(length: 100)]
    private string $firstName;

    #[ORM\Column(length: 100)]
    private string $lastName;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $emailVerified = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $verificationToken = null;

    public function toDomain(): DomainUser
    {
        return new DomainUser(
            new UserId($this->id),
            new Email($this->email),
            new HashedPassword($this->password),
            $this->firstName,
            $this->lastName,
            $this->isActive,
            $this->emailVerified,
            $this->createdAt,
            $this->updatedAt,
            $this->verificationToken ? new Token($this->verificationToken) : null
        );
    }

    public static function fromDomain(DomainUser $user): self
    {
        $entity = new self();
        $entity->id = $user->id()->value();
        $entity->email = $user->email()->value();
        $entity->password = $user->password()->hash();
        $entity->firstName = $user->firstName();
        $entity->lastName = $user->lastName();
        $entity->isActive = $user->isActive();
        $entity->emailVerified = $user->isEmailVerified();
        $entity->createdAt = $user->createdAt();
        $entity->updatedAt = $user->updatedAt();

        return $entity;
    }
}
```

### Créer `src/Infrastructure/Persistence/Doctrine/Repository/UserRepository.php`

### Créer `src/Infrastructure/Security/PasswordHasher/Argon2idHasher.php`

---

## Étape 6 : Infrastructure Layer - JWT Authentication

### Installer JWT Bundle
```bash
composer require lexik/jwt-authentication-bundle
```

### Créer `src/Infrastructure/Security/JWT/JwtAuthenticator.php`

### Créer `src/Infrastructure/Security/JWT/JwtTokenManager.php`

### Configurer `config/packages/security.yaml`

### Générer les clés JWT
```bash
php bin/console lexik:jwt:generate-keypair
```

---

## Étape 7 : Infrastructure Layer - Events

### Créer `src/Infrastructure/Event/Symfony/SymfonyEventDispatcher.php`
```php
<?php

namespace App\Infrastructure\Event\Symfony;

use App\Domain\Shared\Event\DomainEvent;
use App\Domain\Shared\Event\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

class SymfonyEventDispatcher implements EventDispatcher
{
    public function __construct(
        private MessageBusInterface $eventBus
    ) {
    }

    public function dispatch(DomainEvent $event): void
    {
        $this->eventBus->dispatch($event);
    }
}
```

### Créer les Event Subscribers pour :
- `UserRegistered` → Envoyer email de vérification
- `UserLoggedIn` → Logger dans ActivityLog
- `UserPasswordChanged` → Sauvegarder dans PasswordHistory

---

## Étape 8 : Presentation Layer

### Créer `src/Presentation/Web/Controller/RegistrationController.php`
```php
<?php

namespace App\Presentation\Web\Controller;

use App\Application\Authentication\Command\RegisterUserCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/register', name: 'app_register')]
class RegistrationController extends AbstractController
{
    public function __invoke(
        Request $request,
        MessageBusInterface $commandBus
    ): Response {
        if ($request->isMethod('POST')) {
            $command = new RegisterUserCommand(
                $request->request->get('email'),
                $request->request->get('password'),
                $request->request->get('firstName'),
                $request->request->get('lastName')
            );

            try {
                $commandBus->dispatch($command);
                $this->addFlash('success', 'Compte créé avec succès');
                return $this->redirectToRoute('app_login');
            } catch (\RuntimeException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('registration/register.html.twig');
    }
}
```

### Créer les autres controllers :
- `LoginController.php`
- `ProfileController.php`

### Créer les templates Twig correspondants

---

## Étape 9 : API Presentation

### Créer `src/Presentation/Api/Controller/AuthApiController.php`

### Créer les DTOs API

### Configurer les groupes de sérialisation

---

## Étape 10 : Tests & CI/CD

### Tests Unitaires Domain
- `tests/Domain/Authentication/ValueObject/EmailTest.php`
- `tests/Domain/Authentication/Entity/UserTest.php`

### Tests Application
- `tests/Application/Authentication/Handler/RegisterUserHandlerTest.php`

### Configurer `.github/workflows/ci.yml`

---

## Récapitulatif Développeur 1

**Livrables :**
- ✅ Domain Layer : Email, HashedPassword, Token, UserId, User, Role
- ✅ Repository Interfaces et Domain Services Interfaces
- ✅ Domain Events (UserRegistered, UserEmailVerified, etc.)
- ✅ Application Layer : Commands/Handlers (Register, Login, ChangePassword)
- ✅ Infrastructure : Doctrine Repositories, JWT Auth, Event Dispatcher
- ✅ Presentation : Controllers Web, API, Templates
- ✅ Tests et CI/CD

---

# Développeur 2 : Contexte Vault

## Étape 1 : Domain Layer - Value Objects Vault

### Créer `src/Domain/Vault/ValueObject/VaultName.php`
```php
<?php

namespace App\Domain\Vault\ValueObject;

readonly class VaultName
{
    private string $value;

    public function __construct(string $value)
    {
        if (strlen($value) < 3 || strlen($value) > 100) {
            throw new \InvalidArgumentException('Le nom du coffre doit contenir entre 3 et 100 caractères');
        }
        $this->value = trim($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(VaultName $other): bool
    {
        return $this->value === $other->value;
    }
}
```

### Créer `src/Domain/Vault/ValueObject/EncryptedPassword.php`
```php
<?php

namespace App\Domain\Vault\ValueObject;

readonly class EncryptedPassword
{
    private string $encryptedValue;
    private string $salt;

    public function __construct(string $encryptedValue, string $salt)
    {
        $this->encryptedValue = $encryptedValue;
        $this->salt = $salt;
    }

    public static function fromPlain(string $plainPassword, string $userKey, PasswordEncrypterInterface $encrypter): self
    {
        return $encrypter->encrypt($plainPassword, $userKey);
    }

    public function decrypt(string $userKey, PasswordEncrypterInterface $encrypter): string
    {
        return $encrypter->decrypt($this->encryptedValue, $this->salt, $userKey);
    }

    public function encryptedValue(): string { return $this->encryptedValue; }
    public function salt(): string { return $this->salt; }
}
```

### Créer `src/Domain/Vault/ValueObject/VaultColor.php`
```php
<?php

namespace App\Domain\Vault\ValueObject;

readonly class VaultColor
{
    public function __construct(
        private string $value
    ) {
        $value = strtoupper($value);
        if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
            throw new \InvalidArgumentException('Couleur invalide (format HEX requis)');
        }
        $this->value = $value;
    }

    public static function random(): self
    {
        $colors = ['#FF5733', '#33FF57', '#3357FF', '#F033FF', '#FF33A8'];
        return new self($colors[array_rand($colors)]);
    }

    public function value(): string { return $this->value; }
}
```

---

## Étape 2 : Domain Layer - Aggregate Vault

### Créer `src/Domain/Shared/ValueObject/VaultId.php`

### Créer `src/Domain/Vault/Entity/Vault.php` (Aggregate Root)
```php
<?php

namespace App\Domain\Vault\Entity;

use App\Domain\Authentication\Entity\User;
use App\Domain\Shared\ValueObject\VaultId;
use App\Domain\Vault\ValueObject\VaultName;
use App\Domain\Shared\Event\DomainEvent;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Vault
{
    private VaultId $id;
    private VaultName $name;
    private ?string $description;
    private bool $archived;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private User $owner;
    private Collection $passwordEntries;
    private Collection $tags;
    private array $domainEvents = [];

    public function __construct(
        VaultId $id,
        VaultName $name,
        User $owner,
        ?string $description = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->owner = $owner;
        $this->description = $description;
        $this->archived = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->passwordEntries = new ArrayCollection();
        $this->tags = new ArrayCollection();

        $this->addDomainEvent(new VaultCreated($this));
    }

    public function addPasswordEntry(PasswordEntry $entry): void
    {
        if ($this->archived) {
            throw new \RuntimeException('Impossible d\'ajouter un mot de passe à un coffre archivé');
        }

        $this->passwordEntries->add($entry);
        $this->updatedAt = new \DateTimeImmutable();

        $this->addDomainEvent(new PasswordAdded($this, $entry));
    }

    public function removePasswordEntry(PasswordEntry $entry): void
    {
        $this->passwordEntries->removeElement($entry);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function archive(): void
    {
        if ($this->archived) {
            return;
        }

        $this->archived = true;
        $this->updatedAt = new \DateTimeImmutable();

        $this->addDomainEvent(new VaultArchived($this));
    }

    public function unarchive(): void
    {
        $this->archived = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function rename(VaultName $newName): void
    {
        if ($this->name->equals($newName)) {
            return;
        }

        $this->name = $newName;
        $this->updatedAt = new \DateTimeImmutable();

        $this->addDomainEvent(new VaultRenamed($this));
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner->id()->equals($user->id());
    }

    public function addDomainEvent(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    // Getters
    public function id(): VaultId { return $this->id; }
    public function name(): VaultName { return $this->name; }
    public function description(): ?string { return $this->description; }
    public function isArchived(): bool { return $this->archived; }
    public function owner(): User { return $this->owner; }
    public function passwordEntries(): Collection { return $this->passwordEntries; }
}
```

### Créer `src/Domain/Vault/Entity/PasswordEntry.php`
```php
<?php

namespace App\Domain\Vault\Entity;

use App\Domain\Shared\ValueObject\PasswordEntryId;
use App\Domain\Vault\ValueObject\EncryptedPassword;
use App\Domain\Vault\Entity\Vault;

class PasswordEntry
{
    private PasswordEntryId $id;
    private string $title;
    private string $username;
    private EncryptedPassword $encryptedPassword;
    private ?string $url;
    private ?string $notes;
    private bool $favorite;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private Vault $vault;

    public function __construct(
        PasswordEntryId $id,
        string $title,
        string $username,
        EncryptedPassword $encryptedPassword,
        Vault $vault,
        ?string $url = null,
        ?string $notes = null
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->username = $username;
        $this->encryptedPassword = $encryptedPassword;
        $this->vault = $vault;
        $this->url = $url;
        $this->notes = $notes;
        $this->favorite = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function updatePassword(EncryptedPassword $newPassword): void
    {
        $this->encryptedPassword = $newPassword;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toggleFavorite(): void
    {
        $this->favorite = !$this->favorite;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function decrypt(string $userKey): string
    {
        return $this->encryptedPassword->decrypt($userKey, new Aes256GcmEncrypter());
    }

    // Getters
    public function id(): PasswordEntryId { return $this->id; }
    public function title(): string { return $this->title; }
    public function username(): string { return $this->username; }
    public function url(): ?string { return $this->url; }
    public function notes(): ?string { return $this->notes; }
    public function isFavorite(): bool { return $this->favorite; }
    public function vault(): Vault { return $this->vault; }
}
```

### Créer `src/Domain/Vault/Entity/Category.php` (Value Object)
```php
<?php

namespace App\Domain\Vault\Entity;

use App\Domain\Vault\ValueObject\VaultColor;

readonly class Category
{
    public function __construct(
        public string $name,
        public VaultColor $color
    ) {
    }
}
```

### Créer `src/Domain/Vault/Entity/Tag.php` (Value Object)
```php
<?php

namespace App\Domain\Vault\Entity;

use App\Domain\Vault\ValueObject\VaultColor;

readonly class Tag
{
    public function __construct(
        public string $name,
        public VaultColor $color
    ) {
    }
}
```

---

## Étape 3 : Domain Layer - Repository Interfaces

### Créer `src/Domain/Vault/Repository/VaultRepositoryInterface.php`
```php
<?php

namespace App\Domain\Vault\Repository;

use App\Domain\Authentication\Entity\User;
use App\Domain\Vault\Entity\Vault;
use App\Domain\Shared\ValueObject\VaultId;

interface VaultRepositoryInterface
{
    public function save(Vault $vault): void;
    public function remove(Vault $vault): void;
    public function findById(VaultId $id): ?Vault;
    public function findByOwner(User $owner): array;
    public function findSharedWithUser(User $user): array;
    public function existsByName(string $name, User $owner): bool;
}
```

### Créer `src/Domain/Vault/Repository/PasswordEntryRepositoryInterface.php`

---

## Étape 4 : Domain Layer - Services Interfaces

### Créer `src/Domain/Vault/Service/PasswordEncrypterInterface.php`
```php
<?php

namespace App\Domain\Vault\Service;

use App\Domain\Vault\ValueObject\EncryptedPassword;

interface PasswordEncrypterInterface
{
    public function encrypt(string $plainPassword, string $userKey): EncryptedPassword;
    public function decrypt(string $encryptedValue, string $salt, string $userKey): string;
}
```

### Créer `src/Domain/Vault/Service/PasswordGeneratorInterface.php`
```php
<?php

namespace App\Domain\Vault\Service;

interface PasswordGeneratorInterface
{
    public function generate(int $length, array $options): string;
    public function getStrength(string $password): array;
}
```

---

## Étape 5 : Domain Events Vault

### Créer dans `src/Domain/Vault/Event/` :
- `VaultCreated.php`
- `VaultArchived.php`
- `VaultRenamed.php`
- `PasswordAdded.php`

---

## Étape 6 : Application Layer - Vault Commands

### Créer `src/Application/Vault/Command/CreateVaultCommand.php`
```php
<?php

namespace App\Application\Vault\Command;

readonly class CreateVaultCommand
{
    public function __construct(
        public string $userId,
        public string $name,
        public ?string $description = null
    ) {
    }
}
```

### Créer `src/Application/Vault/Handler/CreateVaultHandler.php`

### Créer les autres Commands/Handlers :
- `AddPasswordCommand.php` + `AddPasswordHandler.php`
- `ArchiveVaultCommand.php` + `ArchiveVaultHandler.php`
- `UpdateVaultCommand.php` + `UpdateVaultHandler.php`

---

## Étape 7 : Application Layer - Vault Queries

### Créer `src/Application/Vault/Query/GetVaultQuery.php`
```php
<?php

namespace App\Application\Vault\Query;

readonly class GetVaultQuery
{
    public function __construct(
        public string $vaultId,
        public string $userId
    ) {
    }
}
```

### Créer `src/Application/Vault/Handler/GetVaultHandler.php`

### Créer les autres Queries/Handlers :
- `ListVaultsQuery.php` + `ListVaultsHandler.php`
- `SearchVaultsQuery.php` + `SearchVaultsHandler.php`
- `GetVaultStatisticsQuery.php` + `GetVaultStatisticsHandler.php`

---

## Étape 8 : Infrastructure Layer - Vault Repositories

### Implémenter `src/Infrastructure/Persistence/Doctrine/Repository/VaultRepository.php`

### Implémenter `src/Infrastructure/Persistence/Doctrine/Repository/PasswordEntryRepository.php`

---

## Étape 9 : Infrastructure Layer - Encryption Service

### Créer `src/Infrastructure/Vault/Service/Aes256GcmEncrypter.php`
```php
<?php

namespace App\Infrastructure\Vault\Service;

use App\Domain\Vault\Service\PasswordEncrypterInterface;
use App\Domain\Vault\ValueObject\EncryptedPassword;

class Aes256GcmEncrypter implements PasswordEncrypterInterface
{
    private const METHOD = 'aes-256-gcm';
    private const KEY_LENGTH = 32;
    private const NONCE_LENGTH = 12;
    private const TAG_LENGTH = 16;

    public function encrypt(string $plainPassword, string $userKey): EncryptedPassword
    {
        $salt = bin2hex(random_bytes(self::KEY_LENGTH / 2));
        $nonce = random_bytes(self::NONCE_LENGTH);

        $encrypted = openssl_encrypt(
            $plainPassword,
            self::METHOD,
            $userKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        return new EncryptedPassword(
            base64_encode($nonce . $tag . $encrypted),
            $salt
        );
    }

    public function decrypt(string $encryptedValue, string $salt, string $userKey): string
    {
        $data = base64_decode($encryptedValue);
        $nonce = substr($data, 0, self::NONCE_LENGTH);
        $tag = substr($data, self::NONCE_LENGTH, self::TAG_LENGTH);
        $ciphertext = substr($data, self::NONCE_LENGTH + self::TAG_LENGTH);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::METHOD,
            $userKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        if ($decrypted === false) {
            throw new \RuntimeException('Déchiffrement échoué');
        }

        return $decrypted;
    }
}
```

### Créer `src/Infrastructure/Vault/Service/RandomPasswordGenerator.php`

---

## Étape 10 : Infrastructure Layer - HaveIBeenPwned

### Créer `src/Domain/Security/Service/BreachedPasswordCheckerInterface.php`
```php
<?php

namespace App\Domain\Security\Service;

interface BreachedPasswordCheckerInterface
{
    public function isBreached(string $password): bool;
    public function getBreachCount(string $password): int;
}
```

### Créer `src/Infrastructure/External/HaveIBeenPwned/HaveIBeenPwnedService.php`
```php
<?php

namespace App\Infrastructure\External\HaveIBeenPwned;

use App\Domain\Security\Service\BreachedPasswordCheckerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HaveIBeenPwnedService implements BreachedPasswordCheckerInterface
{
    private const API_URL = 'https://api.pwnedpasswords.com/range/';

    public function __construct(
        private HttpClientInterface $client
    ) {
    }

    public function isBreached(string $password): bool
    {
        return $this->getBreachCount($password) > 0;
    }

    public function getBreachCount(string $password): int
    {
        $hash = strtoupper(sha1($password));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $response = $this->client->request('GET', self::API_URL . $prefix);
            $content = $response->getContent();

            foreach (explode("\r\n", $content) as $line) {
                [$hashSuffix, $count] = explode(':', $line);
                if ($hashSuffix === $suffix) {
                    return (int) $count;
                }
            }

            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
}
```

---

## Étape 11 : Presentation Layer - Vault Controllers

### Créer `src/Presentation/Web/Controller/VaultController.php`
```php
<?php

namespace App\Presentation\Web\Controller;

use App\Application\Vault\Command\CreateVaultCommand;
use App\Application\Vault\Query\GetVaultQuery;
use App\Application\Vault\Query\ListVaultsQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/vaults', name: 'app_vaults_')]
class VaultController extends AbstractController
{
    public function __construct(
        private MessageBusInterface $queryBus,
        private MessageBusInterface $commandBus
    ) {
    }

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $query = new ListVaultsQuery($this->getUser()->getId());
        $vaults = $this->queryBus->dispatch($query);

        return $this->render('vault/index.html.twig', [
            'vaults' => $vaults,
        ]);
    }

    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $command = new CreateVaultCommand(
                $this->getUser()->getId(),
                $request->request->get('name'),
                $request->request->get('description')
            );

            $this->commandBus->dispatch($command);

            return $this->redirectToRoute('app_vaults_index');
        }

        return $this->render('vault/new.html.twig');
    }

    #[Route('/{id}', name: 'show')]
    public function show(string $id): Response
    {
        $query = new GetVaultQuery($id, $this->getUser()->getId());
        $vault = $this->queryBus->dispatch($query);

        return $this->render('vault/show.html.twig', [
            'vault' => $vault,
        ]);
    }
}
```

### Créer `src/Presentation/Web/Controller/PasswordEntryController.php`

---

## Étape 12 : Presentation Layer - Vault API

### Créer `src/Presentation/Api/Controller/VaultApiController.php`

### Créer les DTOs API

### Configurer la sérialisation

---

## Étape 13 : Domain Layer - Specifications

### Créer `src/Domain/Vault/Specification/PasswordSpecification.php`
```php
<?php

namespace App\Domain\Vault\Specification;

use App\Domain\Vault\Entity\PasswordEntry;

readonly class PasswordSpecification
{
    private function __construct(
        public ?string $title = null,
        public ?string $url = null,
        public ?array $categoryIds = null,
        public ?array $tagIds = null,
        public ?bool $favoriteOnly = null
    ) {
    }

    public static function create(): self
    {
        return new self();
    }

    public function withTitle(string $title): self
    {
        return new self(title: $title, url: $this->url, categoryIds: $this->categoryIds, tagIds: $this->tagIds, favoriteOnly: $this->favoriteOnly);
    }

    public function withUrl(string $url): self
    {
        return new self(title: $this->title, url: $url, categoryIds: $this->categoryIds, tagIds: $this->tagIds, favoriteOnly: $this->favoriteOnly);
    }

    public function onlyFavorites(): self
    {
        return new self(title: $this->title, url: $this->url, categoryIds: $this->categoryIds, tagIds: $this->tagIds, favoriteOnly: true);
    }

    public function isSatisfiedBy(PasswordEntry $entry): bool
    {
        if ($this->title && !str_contains(strtolower($entry->title()), strtolower($this->title))) {
            return false;
        }

        if ($this->url && !str_contains(strtolower($entry->url() ?? ''), strtolower($this->url))) {
            return false;
        }

        if ($this->favoriteOnly && !$entry->isFavorite()) {
            return false;
        }

        return true;
    }
}
```

---

## Étape 14 : Tests

### Tests Unitaires Domain
- `tests/Domain/Vault/ValueObject/VaultNameTest.php`
- `tests/Domain/Vault/Entity/VaultTest.php`

### Tests Application
- `tests/Application/Vault/Handler/CreateVaultHandlerTest.php`

---

## Récapitulatif Développeur 2

**Livrables :**
- ✅ Domain Vault : VaultName, EncryptedPassword, VaultColor, Vault (Aggregate), PasswordEntry, Category, Tag
- ✅ Repository Interfaces et Services Interfaces
- ✅ Domain Events Vault
- ✅ Application Layer : Commands/Handlers/Queries
- ✅ Infrastructure : Repositories Doctrine, AES-256-GCM Encrypter, PasswordGenerator
- ✅ HaveIBeenPwned Service
- ✅ Presentation : Controllers Web/API, Templates
- ✅ Specification Pattern pour recherche
- ✅ Tests

---

# Développeur 3 : Contextes Sharing & Security

## Étape 1 : Domain Layer - Sharing Value Objects

### Créer `src/Domain/Sharing/ValueObject/SharingPermission.php`
```php
<?php

namespace App\Domain\Sharing\ValueObject;

readonly class SharingPermission
{
    public const READ = 'READ';
    public const WRITE = 'WRITE';
    public const ADMIN = 'ADMIN';

    public function __construct(
        private string $value
    ) {
        if (!in_array($value, [self::READ, self::WRITE, self::ADMIN], true)) {
            throw new \InvalidArgumentException('Permission invalide');
        }
    }

    public static function read(): self
    {
        return new self(self::READ);
    }

    public static function write(): self
    {
        return new self(self::WRITE);
    }

    public static function admin(): self
    {
        return new self(self::ADMIN);
    }

    public function canRead(): bool
    {
        return in_array($this->value, [self::READ, self::WRITE, self::ADMIN], true);
    }

    public function canWrite(): bool
    {
        return in_array($this->value, [self::WRITE, self::ADMIN], true);
    }

    public function canAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function value(): string { return $this->value; }
}
```

---

## Étape 2 : Domain Layer - Aggregate SharedVault

### Créer `src/Domain/Sharing/Entity/SharedVault.php` (Aggregate Root)
```php
<?php

namespace App\Domain\Sharing\Entity;

use App\Domain\Authentication\Entity\User;
use App\Domain\Vault\Entity\Vault;
use App\Domain\Sharing\ValueObject\SharingPermission;

readonly class SharedVault
{
    private Vault $vault;
    private User $recipient;
    private User $sender;
    private SharingPermission $permission;
    private bool $accepted;
    private \DateTimeImmutable $sharedAt;
    private ?\DateTimeImmutable $acceptedAt;

    public function __construct(
        Vault $vault,
        User $recipient,
        User $sender,
        SharingPermission $permission
    ) {
        if ($vault->isOwnedBy($recipient)) {
            throw new \InvalidArgumentException('Impossible de partager un coffre avec son propriétaire');
        }

        $this->vault = $vault;
        $this->recipient = $recipient;
        $this->sender = $sender;
        $this->permission = $permission;
        $this->accepted = false;
        $this->sharedAt = new \DateTimeImmutable();
        $this->acceptedAt = null;
    }

    public function accept(): void
    {
        if ($this->accepted) {
            return;
        }

        $this->accepted = true;
        $this->acceptedAt = new \DateTimeImmutable();
    }

    public function decline(): void
    {
        // L'aggregate sera supprimé
    }

    public function updatePermission(SharingPermission $newPermission): void
    {
        // Permission mise à jour
    }

    public function canBeAccessedBy(User $user): bool
    {
        return $this->recipient->id()->equals($user->id()) && $this->accepted;
    }

    // Getters
    public function vault(): Vault { return $this->vault; }
    public function recipient(): User { return $this->recipient; }
    public function sender(): User { return $this->sender; }
    public function permission(): SharingPermission { return $this->permission; }
    public function isAccepted(): bool { return $this->accepted; }
    public function sharedAt(): \DateTimeImmutable { return $this->sharedAt; }
}
```

---

## Étape 3 : Domain Layer - Sharing Repository Interface

### Créer `src/Domain/Sharing/Repository/SharedVaultRepositoryInterface.php`
```php
<?php

namespace App\Domain\Sharing\Repository;

use App\Domain\Authentication\Entity\User;
use App\Domain\Sharing\Entity\SharedVault;
use App\Domain\Vault\Entity\Vault;

interface SharedVaultRepositoryInterface
{
    public function save(SharedVault $sharedVault): void;
    public function remove(SharedVault $sharedVault): void;
    public function findById(int $id): ?SharedVault;
    public function findByVault(Vault $vault): array;
    public function findByRecipient(User $user): array;
    public function findByVaultAndRecipient(Vault $vault, User $user): ?SharedVault;
}
```

---

## Étape 4 : Domain Events Sharing

### Créer dans `src/Domain/Sharing/Event/` :
- `VaultShared.php`
- `SharingAccepted.php`
- `PermissionChanged.php`

---

## Étape 5 : Application Layer - Sharing Commands

### Créer `src/Application/Sharing/Command/ShareVaultCommand.php`
```php
<?php

namespace App\Application\Sharing\Command;

readonly class ShareVaultCommand
{
    public function __construct(
        public string $vaultId,
        public string $recipientEmail,
        public string $permission,
        public string $senderId
    ) {
    }
}
```

### Créer `src/Application/Sharing/Handler/ShareVaultHandler.php`

### Créer les autres Commands/Handlers :
- `AcceptSharingCommand.php` + `AcceptSharingHandler.php`
- `DeclineSharingCommand.php` + `DeclineSharingHandler.php`
- `UpdatePermissionCommand.php` + `UpdatePermissionHandler.php`

---

## Étape 6 : Infrastructure Layer - Sharing Repository

### Implémenter `src/Infrastructure/Persistence/Doctrine/Repository/SharedVaultRepository.php`

---

## Étape 7 : Presentation Layer - Sharing Controllers

### Créer `src/Presentation/Web/Controller/VaultSharingController.php`

### Créer `src/Presentation/Api/Controller/SharingApiController.php`

---

## Étape 8 : Domain Layer - Security Value Objects

### Créer `src/Domain/Security/ValueObject/AlertSeverity.php` (Enum)
```php
<?php

namespace App\Domain\Security\ValueObject;

enum AlertSeverity: string
{
    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';

    public function weight(): int
    {
        return match ($this) {
            self::LOW => 1,
            self::MEDIUM => 2,
            self::HIGH => 3,
            self::CRITICAL => 4,
        };
    }
}
```

### Créer `src/Domain/Security/ValueObject/AlertType.php` (Enum)
```php
<?php

namespace App\Domain\Security\ValueObject;

enum AlertType: string
{
    case WEAK_PASSWORD = 'WeakPassword';
    case REUSED_PASSWORD = 'ReusedPassword';
    case BREACHED_PASSWORD = 'BreachedPassword';
    case SUSPICIOUS_LOGIN = 'SuspiciousLogin';
}
```

---

## Étape 9 : Domain Layer - Security Entities

### Créer `src/Domain/Security/Entity/SecurityAlert.php`
```php
<?php

namespace App\Domain\Security\Entity;

use App\Domain\Authentication\Entity\User;
use App\Domain\Security\ValueObject\AlertSeverity;
use App\Domain\Security\ValueObject\AlertType;

readonly class SecurityAlert
{
    private AlertType $type;
    private AlertSeverity $severity;
    private string $message;
    private bool $resolved;
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $resolvedAt;
    private User $user;

    public function __construct(
        AlertType $type,
        AlertSeverity $severity,
        string $message,
        User $user
    ) {
        $this->type = $type;
        $this->severity = $severity;
        $this->message = $message;
        $this->user = $user;
        $this->resolved = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->resolvedAt = null;
    }

    public function resolve(): void
    {
        $this->resolved = true;
        $this->resolvedAt = new \DateTimeImmutable();
    }

    // Getters
    public function type(): AlertType { return $this->type; }
    public function severity(): AlertSeverity { return $this->severity; }
    public function message(): string { return $this->message; }
    public function isResolved(): bool { return $this->resolved; }
    public function user(): User { return $this->user; }
}
```

### Créer `src/Domain/Security/Entity/ActivityLog.php`
```php
<?php

namespace App\Domain\Security\Entity;

use App\Domain\Authentication\Entity\User;

readonly class ActivityLog
{
    private string $action;
    private ?string $ipAddress;
    private ?string $userAgent;
    private \DateTimeImmutable $createdAt;
    private User $user;

    public function __construct(
        string $action,
        User $user,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ) {
        $this->action = $action;
        $this->user = $user;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters
    public function action(): string { return $this->action; }
    public function ipAddress(): ?string { return $this->ipAddress; }
    public function userAgent(): ?string { return $this->userAgent; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function user(): User { return $this->user; }
}
```

### Créer `src/Domain/Security/Entity/LoginAttempt.php`

### Créer `src/Domain/Security/Entity/PasswordHistory.php`

---

## Étape 10 : Domain Layer - Notification

### Créer `src/Domain/Notification/Entity/Notification.php`
```php
<?php

namespace App\Domain\Notification\Entity;

use App\Domain\Authentication\Entity\User;

readonly class Notification
{
    private string $title;
    private string $message;
    private string $type;
    private bool $isRead;
    private bool $isSent;
    private ?\DateTimeImmutable $sentAt;
    private \DateTimeImmutable $createdAt;
    private User $user;

    public function __construct(
        string $title,
        string $message,
        string $type,
        User $user
    ) {
        $this->title = $title;
        $this->message = $message;
        $this->type = $type;
        $this->user = $user;
        $this->isRead = false;
        $this->isSent = false;
        $this->sentAt = null;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function markAsRead(): void
    {
        $this->isRead = true;
    }

    public function markAsSent(): void
    {
        $this->isSent = true;
        $this->sentAt = new \DateTimeImmutable();
    }

    // Getters
    public function title(): string { return $this->title; }
    public function message(): string { return $this->message; }
    public function type(): string { return $this->type; }
    public function isRead(): bool { return $this->isRead; }
    public function isSent(): bool { return $this->isSent; }
    public function sentAt(): ?\DateTimeImmutable { return $this->sentAt; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function user(): User { return $this->user; }
}
```

---

## Étape 11 : Infrastructure Layer - Email Service

### Créer `src/Infrastructure/External/Email/SymfonyMailer.php`
```php
<?php

namespace App\Infrastructure\External\Email;

use App\Domain\Notification\Service\EmailSenderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SymfonyMailer implements EmailSenderInterface
{
    public function __construct(
        private MailerInterface $mailer
    ) {
    }

    public function send(string $to, string $subject, string $htmlContent): void
    {
        $email = (new Email())
            ->from('noreply@securevault.local')
            ->to($to)
            ->subject($subject)
            ->html($htmlContent);

        $this->mailer->send($email);
    }
}
```

### Créer les templates Twig pour les emails

---

## Étape 12 : Presentation Layer - Security Controllers

### Créer `src/Presentation/Web/Controller/NotificationController.php`

### Créer `src/Presentation/Web/Controller/SecurityAlertController.php`

### Créer `src/Presentation/Web/Controller/ActivityLogController.php`

---

## Étape 13 : Application Layer - Admin

### Installer EasyAdminBundle
```bash
composer require easycorp/easyadmin-bundle
```

### Créer `src/Presentation/Admin/Controller/DashboardController.php`

### Configurer les CRUD controllers pour :
- Users
- Roles
- Vaults
- SecurityAlerts
- ActivityLogs

---

## Étape 14 : Tests

### Tests Unitaires Domain
- `tests/Domain/Sharing/ValueObject/SharingPermissionTest.php`
- `tests/Domain/Sharing/Entity/SharedVaultTest.php`
- `tests/Domain/Security/Entity/SecurityAlertTest.php`

### Tests Application
- `tests/Application/Sharing/Handler/ShareVaultHandlerTest.php`

### Tests Infrastructure
- `tests/Infrastructure/External/HaveIBeenPwned/HaveIBeenPwnedServiceTest.php`

---

## Étape 15 : Documentation

### Créer la documentation DDD
- Diagramme des Bounded Contexts
- Diagramme des Aggregates
- Liste des Domain Events

### Créer la documentation API (OpenAPI/Swagger)

---

## Étape 16 : Déploiement

### Configuration Production
- Mettre à jour `.env` pour la production
- Configurer SSL
- Configurer les backups
- Hardening sécurité

### Déploiement sur VPS

---

## Récapitulatif Développeur 3

**Livrables :**
- ✅ Domain Sharing : SharingPermission, SharedVault (Aggregate)
- ✅ Domain Security : AlertSeverity (Enum), AlertType (Enum), SecurityAlert, ActivityLog, LoginAttempt, PasswordHistory
- ✅ Domain Notification : Notification Entity
- ✅ Repository Interfaces et Events
- ✅ Application Layer : Sharing Commands/Handlers, Security Queries
- ✅ Infrastructure : Repositories, Email Service
- ✅ Presentation : Controllers Web/API, Admin Dashboard
- ✅ Tests
- ✅ Documentation
- ✅ Déploiement

---

# Configuration Symfony pour DDD + Hexagonal

## services.yaml

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Domain Services Interfaces → Infrastructure Implementations
    App\Domain\Authentication\Service\PasswordHasherInterface:
        '@App\Infrastructure\Security\PasswordHasher\Argon2idHasher'

    App\Domain\Vault\Service\PasswordEncrypterInterface:
        '@App\Infrastructure\Vault\Service\Aes256GcmEncrypter'

    App\Domain\Vault\Service\PasswordGeneratorInterface:
        '@App\Infrastructure\Vault\Service\RandomPasswordGenerator'

    App\Domain\Security\Service\BreachedPasswordCheckerInterface:
        '@App\Infrastructure\External\HaveIBeenPwned\HaveIBeenPwnedService'

    App\Domain\Notification\Service\EmailSenderInterface:
        '@App\Infrastructure\External\Email\SymfonyMailer'

    # Repository Interfaces → Infrastructure Implementations
    App\Domain\Authentication\Repository\UserRepositoryInterface:
        '@App\Infrastructure\Persistence\Doctrine\Repository\UserRepository'

    App\Domain\Vault\Repository\VaultRepositoryInterface:
        '@App\Infrastructure\Persistence\Doctrine\Repository\VaultRepository'

    App\Domain\Sharing\Repository\SharedVaultRepositoryInterface:
        '@App\Infrastructure\Persistence\Doctrine\Repository\SharedVaultRepository'

    # Event Dispatcher
    App\Domain\Shared\Event\EventDispatcher:
        '@App\Infrastructure\Event\Symfony\SymfonyEventDispatcher'
```

---

# Commandes Utiles

```bash
# Créer la structure DDD
mkdir -p src/Domain/Authentication/{Entity,ValueObject,Repository,Service,Event}
mkdir -p src/Domain/Vault/{Entity,ValueObject,Repository,Service,Event}
mkdir -p src/Domain/Sharing/{Entity,ValueObject,Repository,Service,Event}
mkdir -p src/Domain/Security/{Entity,ValueObject,Repository,Service,Event}
mkdir -p src/Domain/Notification/{Entity,ValueObject,Service}
mkdir -p src/Domain/Shared/{ValueObject,Event}
mkdir -p src/Application/{Authentication,Vault,Sharing,Security,Notification}/{Command,Query,Handler,DTO}
mkdir -p src/Infrastructure/{Persistence/Doctrine,Security,External,Event}
mkdir -p src/Presentation/{Web,Api,Admin}

# Développement
make up
make shell
make composer-install
make migrate
make fixtures
make cc

# Tests
docker compose exec app bin/phpunit
docker compose exec app vendor/bin/phpstan analyse src

# Générer clés JWT
docker compose exec app bin/console lexik:jwt:generate-keypair
```

---

# Dépendances Inter-Contextes

```
Authentication (Dev 1) - FOUNDATION
    ↓
Vault (Dev 2) - Dépend de User
    ↓
Sharing (Dev 3) - Dépend de Vault + User
Security (Dev 3) - Dépend de tous les contextes
Notification (Dev 3) - Dépend de tous les contextes
```

**Ordre recommandé :**
1. Développeur 1 commence (Foundation)
2. Développeur 2 peut commencer après que User est stable
3. Développeur 3 peut commencer après que Vault est stable

---

# Patterns DDD Implémentés

1. **Bounded Contexts** : 5 contextes isolés (Authentication, Vault, Sharing, Security, Notification)
2. **Aggregates** : Vault, SharedVault comme aggregate roots
3. **Value Objects** : Immuables (Email, HashedPassword, VaultName, EncryptedPassword, etc.)
4. **Domain Events** : Communication inter-bounded contexts
5. **Repository Pattern** : Interfaces dans Domain, implémentations dans Infrastructure
6. **Domain Services** : Logique métier complexe
7. **Specification Pattern** : Recherche et filtres
8. **CQRS** : Commands (écriture) et Queries (lecture) séparés

---

# Notes PHP 8.4 / Symfony 8

- **Attributes** pour metadata ORM et Routes
- **readonly classes** pour Value Objects
- **readonly properties** pour immutabilité
- **Constructor property promotion**
- **Typed properties** obligatoires
- **Enums** pour AlertSeverity, AlertType
- **match()** au lieu de switch()
- **str_contains()** et autres nouvelles fonctions string
- **DateTimeImmutable** par défaut