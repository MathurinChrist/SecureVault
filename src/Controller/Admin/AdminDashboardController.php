<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use App\Repository\AlertRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use App\Repository\VaultRepository;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/easyadmin', routeName: 'easyadmin_dashboard')]
#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly VaultRepository $vaultRepository,
        private readonly AlertRepository $alertRepository,
        private readonly LoginAttemptRepository $loginAttemptRepository,
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly ContactMessageRepository $contactMessageRepository,
    ) {}

    public function index(): Response
    {
        $since24h = new \DateTimeImmutable('-24 hours');

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users'           => $this->userRepository->count([]),
                'vaults'          => $this->vaultRepository->count([]),
                'unread_alerts'   => $this->alertRepository->count(['isRead' => false]),
                'failed_logins'   => $this->loginAttemptRepository->countFailedSince24h($since24h),
                'activity_today'  => $this->activityLogRepository->countSince($since24h),
                'unread_contacts' => $this->contactMessageRepository->countUnread(),
            ],
            'recent_alerts'   => $this->alertRepository->findBy([], ['createdAt' => 'DESC'], 5),
            'recent_activity' => $this->activityLogRepository->findBy([], ['createdAt' => 'DESC'], 10),
            'recent_contacts' => $this->contactMessageRepository->findBy(['isRead' => false], ['createdAt' => 'DESC'], 5),
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        $logo = <<<'SVG'
            <svg width="26" height="26" viewBox="0 0 32 32" fill="none" style="vertical-align:middle;margin-right:8px">
                <circle cx="16" cy="16" r="13" stroke="#E3FFCC" stroke-width="5" stroke-dasharray="56 26" stroke-linecap="round"/>
                <circle cx="16" cy="16" r="4.5" fill="#2f7d5b"/>
            </svg>
            SVG;

        return Dashboard::new()
            ->setTitle($logo . 'SecureVault')
            ->setFaviconPath('favicon.svg')
            ->renderContentMaximized();
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('styles/admin-theme.css');
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
        yield MenuItem::section('Support');
        yield MenuItem::linkTo(ContactMessageCrudController::class, 'Messages de contact', 'fa fa-envelope');
        yield MenuItem::section('');
        yield MenuItem::linkToUrl('Application', 'fa fa-arrow-left', '/dashboard');
    }
}
