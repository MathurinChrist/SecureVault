<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
    ) {}

    public function log(User $user, string $action): void
    {
        $request = $this->requestStack->getCurrentRequest();

        $entry = new ActivityLog();
        $entry->setUser($user);
        $entry->setAction($action);
        $entry->setIpAddress($request?->getClientIp());
        $entry->setUserAgent($request?->headers->get('User-Agent'));

        $this->em->persist($entry);
        $this->em->flush();
    }
}
