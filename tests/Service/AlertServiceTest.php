<?php

namespace App\Tests\Service;

use App\Entity\Alert;
use App\Entity\User;
use App\Service\AlertService;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AlertServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private LoggerInterface $logger;
    private AlertService $service;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->logger  = $this->createMock(LoggerInterface::class);
        $this->service = new AlertService($this->em, $this->logger);
    }

    public function testCreateAlertPersistsAndFlushes(): void
    {
        $user = $this->makeUser();

        $this->em->expects($this->once())->method('persist')->with($this->isInstanceOf(Alert::class));
        $this->em->expects($this->once())->method('flush');

        $alert = $this->service->createAlert($user, 'Title', 'Desc', 'danger', 'security');

        $this->assertInstanceOf(Alert::class, $alert);
        $this->assertSame('Title', $alert->getTitle());
        $this->assertSame('Desc', $alert->getDescription());
        $this->assertSame('danger', $alert->getType());
        $this->assertSame('security', $alert->getCategory());
        $this->assertSame($user, $alert->getUser());
        $this->assertFalse($alert->isRead());
    }

    public function testCreateAlertDefaultTypeIsInfo(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $alert = $this->service->createAlert($this->makeUser(), 'T', 'D');

        $this->assertSame('info', $alert->getType());
        $this->assertSame('general', $alert->getCategory());
    }

    public function testMarkAsReadSetsIsReadAndFlushes(): void
    {
        $alert = new Alert();

        $this->em->expects($this->once())->method('flush');

        $this->service->markAsRead($alert);

        $this->assertTrue($alert->isRead());
    }

    public function testMarkAllAsReadFlushesOnce(): void
    {
        $user   = $this->makeUser();
        $alert1 = new Alert();
        $alert2 = new Alert();
        $user->method('getAlerts')->willReturn(new ArrayCollection([$alert1, $alert2]));

        $this->em->expects($this->once())->method('flush');

        $this->service->markAllAsRead($user);

        $this->assertTrue($alert1->isRead());
        $this->assertTrue($alert2->isRead());
    }

    public function testCreateAlertLogsWarning(): void
    {
        $this->em->method('persist');
        $this->em->method('flush');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Security alert CREATED'));

        $this->service->createAlert($this->makeUser(), 'Brute force', 'Details');
    }

    private function makeUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getUserIdentifier')->willReturn('test@example.com');

        return $user;
    }
}
