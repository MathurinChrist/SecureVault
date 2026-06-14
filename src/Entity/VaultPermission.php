<?php

namespace App\Entity;

use App\Repository\VaultPermissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VaultPermissionRepository::class)]
class VaultPermission
{
    public const READ  = 'READ';
    public const WRITE = 'WRITE';
    public const ADMIN = 'ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\OneToMany(mappedBy: 'permission', targetEntity: SharedVault::class)]
    private Collection $sharedVaults;

    public function __construct()
    {
        $this->sharedVaults = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getCode(): ?string { return $this->code; }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getName(): ?string { return $this->name; }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string { return $this->description; }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSharedVaults(): Collection { return $this->sharedVaults; }

    public function canRead(): bool
    {
        return in_array($this->code, [self::READ, self::WRITE, self::ADMIN], true);
    }

    public function canWrite(): bool
    {
        return in_array($this->code, [self::WRITE, self::ADMIN], true);
    }

    public function canAdmin(): bool
    {
        return $this->code === self::ADMIN;
    }
}
