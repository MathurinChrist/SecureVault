<?php

namespace App\Controller\Admin;

use App\Repository\ActivityLogRepository;
use App\Repository\AlertRepository;
use App\Repository\ContactMessageRepository;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use App\Repository\VaultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class CustomAdminController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly VaultRepository $vaultRepository,
        private readonly AlertRepository $alertRepository,
        private readonly LoginAttemptRepository $loginAttemptRepository,
        private readonly ActivityLogRepository $activityLogRepository,
        private readonly ContactMessageRepository $contactMessageRepository,
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $since24h = new \DateTimeImmutable('-24 hours');

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'users'          => $this->userRepository->count([]),
                'vaults'         => $this->vaultRepository->count([]),
                'unread_alerts'  => $this->alertRepository->count(['isRead' => false]),
                'failed_logins'  => $this->loginAttemptRepository->countFailedSince24h($since24h),
                'activity_today' => $this->activityLogRepository->countSince($since24h),
                'unread_contacts' => $this->contactMessageRepository->countUnread(),
            ],
            'recent_activity' => $this->activityLogRepository->findBy([], ['createdAt' => 'DESC'], 8),
            'recent_contacts' => $this->contactMessageRepository->findBy(['isRead' => false], ['createdAt' => 'DESC'], 5),
        ]);
    }

    #[Route('/contacts', name: 'contacts')]
    public function contacts(Request $request): Response
    {
        $filter = $request->query->get('filter', 'all');

        $criteria = match($filter) {
            'unread' => ['isRead' => false],
            'read'   => ['isRead' => true],
            default  => [],
        };

        return $this->render('admin/contacts/index.html.twig', [
            'messages' => $this->contactMessageRepository->findBy($criteria, ['createdAt' => 'DESC']),
            'unread_count' => $this->contactMessageRepository->countUnread(),
            'filter' => $filter,
        ]);
    }

    #[Route('/contacts/{id}', name: 'contacts_show')]
    public function contactShow(int $id, EntityManagerInterface $em): Response
    {
        $message = $this->contactMessageRepository->find($id);
        if (!$message) {
            throw $this->createNotFoundException();
        }

        if (!$message->isRead()) {
            $message->setIsRead(true);
            $em->flush();
        }

        return $this->render('admin/contacts/show.html.twig', [
            'message' => $message,
            'unread_count' => $this->contactMessageRepository->countUnread(),
        ]);
    }

    #[Route('/users', name: 'users')]
    public function users(): Response
    {
        return $this->render('admin/users/index.html.twig', [
            'users' => $this->userRepository->findBy([], ['id' => 'DESC']),
            'unread_count' => $this->contactMessageRepository->countUnread(),
        ]);
    }
}
