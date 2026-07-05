<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification extends BaseNotification
{
    public const TYPE_INFO     = 'info';
    public const TYPE_SUCCESS  = 'success';
    public const TYPE_WARNING  = 'warning';
    public const TYPE_SHARE    = 'share';
    public const TYPE_SECURITY = 'security';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column]
    private bool $isSent = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function getMessage(): ?string { return $this->message; }

    public function setMessage(string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function isSent(): bool { return $this->isSent; }

    public function markAsSent(): static
    {
        $this->isSent = true;
        $this->sentAt = new \DateTimeImmutable();
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable { return $this->sentAt; }
}
