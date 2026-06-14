<?php

namespace App\Entity;

use App\Repository\SharedVaultRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SharedVaultRepository::class)]
class SharedVault
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private bool $accepted = false;

    #[ORM\Column]
    private ?\DateTimeImmutable $sharedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $recipient = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $sender = null;

    #[ORM\ManyToOne(inversedBy: 'sharedVaults')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vault $vault = null;

    #[ORM\ManyToOne(inversedBy: 'sharedVaults')]
    #[ORM\JoinColumn(nullable: false)]
    private ?VaultPermission $permission = null;

    public function __construct()
    {
        $this->sharedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function isAccepted(): bool { return $this->accepted; }

    public function accept(): static
    {
        $this->accepted   = true;
        $this->acceptedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSharedAt(): ?\DateTimeImmutable { return $this->sharedAt; }

    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }

    public function getRecipient(): ?User { return $this->recipient; }

    public function setRecipient(?User $recipient): static
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getSender(): ?User { return $this->sender; }

    public function setSender(?User $sender): static
    {
        $this->sender = $sender;
        return $this;
    }

    public function getVault(): ?Vault { return $this->vault; }

    public function setVault(?Vault $vault): static
    {
        $this->vault = $vault;
        return $this;
    }

    public function getPermission(): ?VaultPermission { return $this->permission; }

    public function setPermission(?VaultPermission $permission): static
    {
        $this->permission = $permission;
        return $this;
    }
}
