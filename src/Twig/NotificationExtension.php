<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFilter;

class NotificationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly Security $security,
    ) {}

    public function getGlobals(): array
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return ['unread_notification_count' => 0];
        }

        return [
            'unread_notification_count' => $this->notificationService->countUnread($user),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('time_ago', [$this, 'timeAgo']),
            new TwigFilter('password_strength', [$this, 'passwordStrength']),
        ];
    }

    /**
     * Returns a human-readable "time ago" string from a DateTimeInterface.
     * Usage in Twig: {{ entity.createdAt | time_ago }}
     */
    public function timeAgo(\DateTimeInterface|\DateTimeImmutable|null $date): string
    {
        if ($date === null) {
            return 'inconnu';
        }

        $now  = new \DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();

        return match (true) {
            $diff < 60     => 'à l\'instant',
            $diff < 3600   => sprintf('il y a %d min', (int) ($diff / 60)),
            $diff < 86400  => sprintf('il y a %dh', (int) ($diff / 3600)),
            $diff < 604800 => sprintf('il y a %d j', (int) ($diff / 86400)),
            $diff < 2592000 => sprintf('il y a %d sem.', (int) ($diff / 604800)),
            $diff < 31536000 => sprintf('il y a %d mois', (int) ($diff / 2592000)),
            default        => sprintf('il y a %d an(s)', (int) ($diff / 31536000)),
        };
    }

    /**
     * Returns a strength label for a plaintext password.
     * Usage in Twig: {{ 'mypassword' | password_strength }}
     */
    public function passwordStrength(string $password): string
    {
        $score = 0;
        if (\strlen($password) >= 8)  { $score++; }
        if (\strlen($password) >= 12) { $score++; }
        if (preg_match('/[A-Z]/', $password)) { $score++; }
        if (preg_match('/[0-9]/', $password)) { $score++; }
        if (preg_match('/[^A-Za-z0-9]/', $password)) { $score++; }

        return match (true) {
            $score <= 1 => 'Très faible',
            $score === 2 => 'Faible',
            $score === 3 => 'Moyen',
            $score === 4 => 'Fort',
            default      => 'Très fort',
        };
    }
}
