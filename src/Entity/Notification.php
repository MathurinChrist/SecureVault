<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    private ?string $message = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column]
    private bool $isRead = false;

    #[ORM\Column]
    private bool $isSent = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): ?string { return $this->message; }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getType(): ?string { return $this->type; }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isRead(): bool { return $this->isRead; }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;
        return $this;
    }

    public function isSent(): bool { return $this->isSent; }

    public function markAsSent(): static
    {
        $this->isSent  = true;
        $this->sentAt  = new \DateTimeImmutable();
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }
}
