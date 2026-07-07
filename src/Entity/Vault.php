<?php

namespace App\Entity;

use App\Repository\VaultRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: VaultRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Vault
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['vault:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['vault:read', 'vault:write'])]
    private ?string $name = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['vault:read', 'vault:write'])]
    private ?string $description = null;

    #[ORM\Column]
    #[Groups(['vault:read', 'vault:write'])]
    private bool $archived = false;

    /**
     * The vault's data-encryption key (DEK), randomly generated and stored here wrapped
     * (encrypted) with the server master key. Never stored in plaintext.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $encryptedKey = null;

    /** Which master-key version wrapped {@see $encryptedKey}, so the master can be rotated. */
    #[ORM\Column(options: ['default' => 1])]
    private int $keyEncryptionVersion = 1;

    #[ORM\ManyToOne(inversedBy: 'vaults')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'vault', targetEntity: PasswordEntry::class, orphanRemoval: true)]
    private Collection $passwordEntries;

    #[ORM\OneToMany(mappedBy: 'vault', targetEntity: SharedVault::class, orphanRemoval: true)]
    private Collection $sharedVaults;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'vaults')]
    private Collection $tags;

    #[ORM\Column]
    #[Groups(['vault:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['vault:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->passwordEntries = new ArrayCollection();
        $this->sharedVaults    = new ArrayCollection();
        $this->tags            = new ArrayCollection();
        $this->createdAt       = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

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

    public function isArchived(): bool { return $this->archived; }

    public function setArchived(bool $archived): static
    {
        $this->archived = $archived;
        return $this;
    }

    public function getEncryptedKey(): ?string { return $this->encryptedKey; }

    public function setEncryptedKey(?string $encryptedKey): static
    {
        $this->encryptedKey = $encryptedKey;
        return $this;
    }

    public function getKeyEncryptionVersion(): int { return $this->keyEncryptionVersion; }

    public function setKeyEncryptionVersion(int $keyEncryptionVersion): static
    {
        $this->keyEncryptionVersion = $keyEncryptionVersion;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPasswordEntries(): Collection { return $this->passwordEntries; }

    #[Groups(['vault:read'])]
    public function getEntriesCount(): int
    {
        return $this->passwordEntries->count();
    }

    public function addPasswordEntry(PasswordEntry $entry): static
    {
        if (!$this->passwordEntries->contains($entry)) {
            $this->passwordEntries->add($entry);
            $entry->setVault($this);
        }
        return $this;
    }

    public function removePasswordEntry(PasswordEntry $entry): static
    {
        if ($this->passwordEntries->removeElement($entry)) {
            if ($entry->getVault() === $this) {
                $entry->setVault(null);
            }
        }
        return $this;
    }

    public function getSharedVaults(): Collection { return $this->sharedVaults; }

    public function getTags(): Collection { return $this->tags; }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }
        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
