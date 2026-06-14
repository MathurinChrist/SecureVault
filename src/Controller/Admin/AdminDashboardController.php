<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use App\Repository\AlertRepository;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use App\Repository\VaultRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin_dashboard')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly VaultRepository $vaultRepository,
        private readonly AlertRepository $alertRepository,
        private readonly LoginAttemptRepository $loginAttemptRepository,
        private readonly ActivityLogRepository $activityLogRepository,
    ) {}

    public function index(): Response
    {
        $since24h = new \DateTimeImmutable('-24 hours');

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users'          => $this->userRepository->count([]),
                'vaults'         => $this->vaultRepository->count([]),
                'unread_alerts'  => $this->alertRepository->count(['isRead' => false]),
                'failed_logins'  => $this->loginAttemptRepository->countFailedSince24h($since24h),
                'activity_today' => $this->activityLogRepository->countSince($since24h),
            ],
            'recent_alerts'   => $this->alertRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'recent_activity' => $this->activityLogRepository->findBy([], ['createdAt' => 'DESC'], 10),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('SecureVault — Admin')
            ->setFaviconPath('favicon.ico')
            ->renderContentMaximized();
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');
        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fa fa-users');
        yield MenuItem::linkTo(RoleCrudController::class, 'Rôles', 'fa fa-tag');
        yield MenuItem::section('Coffres');
        yield MenuItem::linkTo(VaultCrudController::class, 'Coffres', 'fa fa-lock');
        yield MenuItem::section('Sécurité');
        yield MenuItem::linkTo(AlertCrudController::class, 'Alertes', 'fa fa-bell');
        yield MenuItem::linkTo(LoginAttemptCrudController::class, 'Tentatives de connexion', 'fa fa-shield');
        yield MenuItem::linkTo(ActivityLogCrudController::class, 'Journaux d\'activité', 'fa fa-list');
        yield MenuItem::section('');
        yield MenuItem::linkToUrl('← Application', 'fa fa-arrow-left', '/dashboard');
    }
}
