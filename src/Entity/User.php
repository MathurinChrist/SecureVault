<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $firstName = null;

    #[ORM\Column(length: 255)]
    private ?string $lastName = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column]
    private bool $emailVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profileImage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $encryptionKey = null;

    private ?string $plainPassword = null;

    #[ORM\Column]
    private bool $is2faEnabled = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $twoFactorSecret = null;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserSession::class, orphanRemoval: true)]
    private Collection $sessions;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Alert::class, orphanRemoval: true)]
    private Collection $alerts;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Vault::class, orphanRemoval: true)]
    private Collection $vaults;

    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    private Collection $roleEntities;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: ActivityLog::class, orphanRemoval: true)]
    private Collection $activityLogs;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: LoginAttempt::class, orphanRemoval: true)]
    private Collection $loginAttempts;

    public function __construct()
    {
        $this->createdAt     = new \DateTimeImmutable();
        $this->sessions      = new ArrayCollection();
        $this->alerts        = new ArrayCollection();
        $this->vaults        = new ArrayCollection();
        $this->roleEntities  = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->activityLogs  = new ArrayCollection();
        $this->loginAttempts = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getEmail(): ?string { return $this->email; }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles   = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string { return $this->password; }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getPlainPassword(): ?string { return $this->plainPassword; }

    public function setPlainPassword(?string $plainPassword): static
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    public function eraseCredentials(): void
    {
        $this->plainPassword = null;
    }

    public function getFirstName(): ?string { return $this->firstName; }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string { return $this->lastName; }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function isActive(): bool { return $this->isActive; }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function isEmailVerified(): bool { return $this->emailVerified; }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;
        return $this;
    }

    public function getProfileImage(): ?string { return $this->profileImage; }

    public function setProfileImage(?string $profileImage): static
    {
        $this->profileImage = $profileImage;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getEncryptionKey(): ?string { return $this->encryptionKey; }

    public function setEncryptionKey(?string $encryptionKey): static
    {
        $this->encryptionKey = $encryptionKey;
        return $this;
    }

    public function is2faEnabled(): bool { return $this->is2faEnabled; }

    public function setIs2faEnabled(bool $is2faEnabled): static
    {
        $this->is2faEnabled = $is2faEnabled;
        return $this;
    }

    public function getTwoFactorSecret(): ?string { return $this->twoFactorSecret; }

    public function setTwoFactorSecret(?string $twoFactorSecret): static
    {
        $this->twoFactorSecret = $twoFactorSecret;
        return $this;
    }

    public function getAlerts(): Collection { return $this->alerts; }

    public function getVaults(): Collection { return $this->vaults; }

    public function getSessions(): Collection { return $this->sessions; }

    public function getRoleEntities(): Collection { return $this->roleEntities; }

    public function addRoleEntity(Role $role): static
    {
        if (!$this->roleEntities->contains($role)) {
            $this->roleEntities->add($role);
        }
        return $this;
    }

    public function removeRoleEntity(Role $role): static
    {
        $this->roleEntities->removeElement($role);
        return $this;
    }

    public function getNotifications(): Collection { return $this->notifications; }

    public function getActivityLogs(): Collection { return $this->activityLogs; }

    public function getLoginAttempts(): Collection { return $this->loginAttempts; }
}
