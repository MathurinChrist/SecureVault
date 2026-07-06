<?php

namespace App\Entity;

use App\Repository\PasswordEntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: PasswordEntryRepository::class)]
#[ORM\HasLifecycleCallbacks]
class PasswordEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['password:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['password:read', 'password:write'])]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['password:read', 'password:write'])]
    private ?string $username = null;

    /** Encrypted (AES-256-GCM) with the owning vault's data-encryption key. */
    #[ORM\Column(type: 'text')]
    private ?string $encryptedPassword = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['password:read', 'password:write'])]
    private ?string $url = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['password:read', 'password:write'])]
    private ?string $notes = null;

    #[ORM\Column]
    #[Groups(['password:read', 'password:write'])]
    private bool $favorite = false;

    #[ORM\Column]
    #[Groups(['password:read'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['password:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'passwordEntries')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vault $vault = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'passwordEntries')]
    private Collection $categories;

    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'passwordEntries')]
    private Collection $tags;

    #[ORM\OneToMany(mappedBy: 'passwordEntry', targetEntity: PasswordHistory::class, orphanRemoval: true)]
    private Collection $passwordHistory;

    public function __construct()
    {
        $this->createdAt       = new \DateTimeImmutable();
        $this->categories      = new ArrayCollection();
        $this->tags            = new ArrayCollection();
        $this->passwordHistory = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getUsername(): ?string { return $this->username; }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getEncryptedPassword(): ?string { return $this->encryptedPassword; }

    public function setEncryptedPassword(string $encryptedPassword): static
    {
        $this->encryptedPassword = $encryptedPassword;
        return $this;
    }

    public function getUrl(): ?string { return $this->url; }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getNotes(): ?string { return $this->notes; }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function isFavorite(): bool { return $this->favorite; }

    public function setFavorite(bool $favorite): static
    {
        $this->favorite = $favorite;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getVault(): ?Vault { return $this->vault; }

    public function setVault(?Vault $vault): static
    {
        $this->vault = $vault;
        return $this;
    }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getCategories(): Collection { return $this->categories; }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }
        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);
        return $this;
    }

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

    public function getPasswordHistory(): Collection { return $this->passwordHistory; }
}
