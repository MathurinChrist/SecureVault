<?php

namespace App\Tests\Service;

use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Repository\LoginAttemptRepository;
use App\Service\AlertService;
use App\Service\LoginAttemptService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class LoginAttemptServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private AlertService $alertService;
    private LoginAttemptRepository $repo;
    private LoginAttemptService $service;

    protected function setUp(): void
    {
        $this->em           = $this->createMock(EntityManagerInterface::class);
        $this->alertService = $this->createMock(AlertService::class);
        $this->repo         = $this->createMock(LoginAttemptRepository::class);
        $this->service      = new LoginAttemptService($this->em, $this->alertService, $this->repo);
    }

    public function testRecordSuccessPersistsAttemptWithSuccessTrue(): void
    {
        $user     = $this->createMock(User::class);
        $captured = null;

        $this->em->expects($this->once())->method('persist')
            ->with($this->callback(function (LoginAttempt $a) use (&$captured) {
                $captured = $a;
                return true;
            }));
        $this->em->expects($this->once())->method('flush');

        $this->service->recordSuccess($user, '10.0.0.1');

        $this->assertTrue($captured->isSuccess());
        $this->assertSame('10.0.0.1', $captured->getIpAddress());
        $this->assertSame($user, $captured->getUser());
    }

    public function testRecordFailurePersistsAttemptWithSuccessFalse(): void
    {
        $user     = $this->createMock(User::class);
        $captured = null;

        $this->em->method('persist')
            ->with($this->callback(function (LoginAttempt $a) use (&$captured) {
                $captured = $a;
                return true;
            }));
        $this->em->method('flush');
        $this->repo->method('countFailedSince')->willReturn(1);

        $this->service->recordFailure($user, '10.0.0.2');

        $this->assertFalse($captured->isSuccess());
        $this->assertSame('10.0.0.2', $captured->getIpAddress());
    }

    public function testNoAlertBelowThreshold(): void
    {
        $user = $this->createMock(User::class);
        $this->em->method('persist');
        $this->em->method('flush');
        $this->repo->method('countFailedSince')->willReturn(4);

        $this->alertService->expects($this->never())->method('createAlert');

        $this->service->recordFailure($user, '1.1.1.1');
    }

    public function testAlertFiredExactlyAtThreshold(): void
    {
        $user = $this->createMock(User::class);
        $this->em->method('persist');
        $this->em->method('flush');
        $this->repo->method('countFailedSince')->willReturn(5);

        $this->alertService->expects($this->once())
            ->method('createAlert')
            ->with(
                $user,
                $this->isType('string'),
                $this->stringContains('5'),
                'warning',
                'security'
            );

        $this->service->recordFailure($user, '1.1.1.1');
    }

    public function testNoAlertAboveThreshold(): void
    {
        // Alert only at exactly 5 to avoid duplicates on further failures
        $user = $this->createMock(User::class);
        $this->em->method('persist');
        $this->em->method('flush');
        $this->repo->method('countFailedSince')->willReturn(8);

        $this->alertService->expects($this->never())->method('createAlert');

        $this->service->recordFailure($user, '1.1.1.1');
    }

    public function testAlertMessageContainsIpAddress(): void
    {
        $user = $this->createMock(User::class);
        $this->em->method('persist');
        $this->em->method('flush');
        $this->repo->method('countFailedSince')->willReturn(5);

        $this->alertService->expects($this->once())
            ->method('createAlert')
            ->with(
                $user,
                $this->anything(),
                $this->stringContains('203.0.113.5'),
                $this->anything(),
                $this->anything()
            );

        $this->service->recordFailure($user, '203.0.113.5');
    }
}
