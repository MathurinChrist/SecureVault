<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class VaultController extends AbstractController
{
    #[Route('/vaults', name: 'app_vaults')]
    public function index(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'title' => 'Mes Coffres'
        ]);
    }

    #[Route('/passwords', name: 'app_passwords')]
    public function passwords(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'title' => 'Mots de passe'
        ]);
    }

    #[Route('/shares', name: 'app_shares')]
    public function shares(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'title' => 'Partages'
        ]);
    }

    #[Route('/alerts', name: 'app_alerts')]
    public function alerts(\Doctrine\ORM\EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $alerts = $entityManager->getRepository(\App\Entity\Alert::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('alerts/index.html.twig', [
            'alerts' => $alerts
        ]);
    }

    #[Route('/alerts/mark-as-read/{id}', name: 'app_alerts_mark_read')]
    public function markRead(int $id, \App\Repository\AlertRepository $alertRepository, \App\Service\AlertService $alertService): Response
    {
        $alert = $alertRepository->find($id);
        if ($alert && $alert->getUser() === $this->getUser()) {
            $alertService->markAsRead($alert);
            $this->addFlash('success', 'Alerte marquée comme lue.');
        }

        return $this->redirectToRoute('app_alerts');
    }

    #[Route('/alerts/mark-all-read', name: 'app_alerts_mark_all_read')]
    public function markAllRead(\App\Service\AlertService $alertService): Response
    {
        $user = $this->getUser();
        $alertService->markAllAsRead($user);
        $this->addFlash('success', 'Toutes les alertes ont été marquées comme lues.');

        return $this->redirectToRoute('app_alerts');
    }

    #[Route('/alerts/dismiss/{id}', name: 'app_alerts_dismiss')]
    public function dismiss(int $id, \App\Repository\AlertRepository $alertRepository, \Doctrine\ORM\EntityManagerInterface $entityManager): Response
    {
        $alert = $alertRepository->find($id);
        if ($alert && $alert->getUser() === $this->getUser()) {
            $entityManager->remove($alert);
            $entityManager->flush();
            $this->addFlash('success', 'Alerte supprimée.');
        }

        return $this->redirectToRoute('app_alerts');
    }

    #[Route('/security/2fa-setup', name: 'app_2fa_setup')]
    public function setup2fa(\Doctrine\ORM\EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        
        $user->setIs2faEnabled(true);
        $user->setTwoFactorSecret('MOCKED_TOTP_SECRET');
        $entityManager->flush();
        
        $this->addFlash('success', 'Authentification à deux facteurs activée !');
        return $this->redirectToRoute('app_alerts');
    }
}
