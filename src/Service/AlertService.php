<?php

namespace App\Service;

use App\Entity\Alert;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AlertService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    public function createAlert(User $user, string $title, string $description, string $type = 'info', string $category = 'general'): Alert
    {
        $alert = new Alert();
        $alert->setUser($user);
        $alert->setTitle($title);
        $alert->setDescription($description);
        $alert->setType($type);
        $alert->setCategory($category);

        $this->entityManager->persist($alert);
        $this->entityManager->flush();

        return $alert;
    }

    public function markAsRead(Alert $alert): void
    {
        $alert->setIsRead(true);
        $this->entityManager->flush();
    }

    public function markAllAsRead(User $user): void
    {
        $alerts = $user->getAlerts();
        foreach ($alerts as $alert) {
            $alert->setIsRead(true);
        }
        $this->entityManager->flush();
    }
}
