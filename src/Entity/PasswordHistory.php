<?php

namespace App\Entity;

use App\Repository\PasswordHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PasswordHistoryRepository::class)]
class PasswordHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $previousPasswordHash = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $changedAt = null;

    #[ORM\ManyToOne(inversedBy: 'passwordHistory')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PasswordEntry $passwordEntry = null;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getPreviousPasswordHash(): ?string { return $this->previousPasswordHash; }

    public function setPreviousPasswordHash(string $previousPasswordHash): static
    {
        $this->previousPasswordHash = $previousPasswordHash;
        return $this;
    }

    public function getChangedAt(): ?\DateTimeImmutable { return $this->changedAt; }

    public function getPasswordEntry(): ?PasswordEntry { return $this->passwordEntry; }

    public function setPasswordEntry(?PasswordEntry $passwordEntry): static
    {
        $this->passwordEntry = $passwordEntry;
        return $this;
    }
}
