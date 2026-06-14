<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

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
}
