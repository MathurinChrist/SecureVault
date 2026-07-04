<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\NotificationRepository;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route('/notifications')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly NotificationService    $notificationService,
        private readonly NotificationRepository $repository,
    ) {}

    #[Route('', name: 'app_notifications', methods: ['GET'])]
    public function index(): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('notification/index.html.twig', [
            'notifications' => $this->repository->findAllByUser($user),
        ]);
    }

    #[Route('/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function markRead(Notification $notification, Request $request): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('notif_read_' . $notification->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_notifications');
        }

        $this->notificationService->markAsRead($notification);

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function markAllRead(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('notif_read_all', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_notifications');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $this->notificationService->markAllAsRead($user);
        $this->addFlash('success', 'Toutes les notifications ont été marquées comme lues.');

        return $this->redirectToRoute('app_notifications');
    }

    #[Route('/{id}/dismiss', name: 'app_notification_dismiss', methods: ['POST'])]
    public function dismiss(Notification $notification, Request $request): Response
    {
        if ($notification->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('notif_dismiss_' . $notification->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_notifications');
        }

        $this->notificationService->dismiss($notification);

        return $this->redirectToRoute('app_notifications');
    }
}
