<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly NotificationRepository $repository,
    ) {}

    public function create(User $user, string $title, string $message, string $type = Notification::TYPE_INFO): Notification
    {
        $notification = new Notification();
        $notification->setUser($user)
                     ->setTitle($title)
                     ->setMessage($message)
                     ->setType($type);

        $this->em->persist($notification);
        $this->em->flush();

        return $notification;
    }

    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->em->flush();
    }

    public function markAllAsRead(User $user): void
    {
        foreach ($this->repository->findUnreadByUser($user) as $notification) {
            $notification->setIsRead(true);
        }
        $this->em->flush();
    }

    public function dismiss(Notification $notification): void
    {
        $this->em->remove($notification);
        $this->em->flush();
    }

    public function countUnread(User $user): int
    {
        return $this->repository->countUnreadByUser($user);
    }
}
