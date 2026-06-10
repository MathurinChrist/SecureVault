<?php

namespace App\Controller;

use App\Entity\PasswordEntry;
use App\Entity\Vault;
use App\Form\PasswordEntryType;
use App\Form\VaultType;
use App\Repository\VaultRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
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

    #[Route('/password/{id}/edit', name: 'app_password_edit', methods: ['POST'])]
    public function editPassword(
        PasswordEntry $passwordEntry,
        Request $request,
        EntityManagerInterface $em,
        VaultRepository $vaultRepository,
        EncryptionService $encryptionService,
        FormFactoryInterface $formFactory,
    ): Response {
        $vault = $passwordEntry->getVault();
        if ($vault === null || $vault->getOwner()->getUserIdentifier() !== $this->getUser()->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $vaults = $vaultRepository->findBy(['owner' => $this->getUser(), 'archived' => false]);

        $form = $formFactory->createNamed('edit_password_entry', PasswordEntryType::class, $passwordEntry, [
            'vaults'           => $vaults,
            'require_password' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            if ($plain !== null && $plain !== '') {
                $key = hash('sha256', $this->encryptionKey, true);
                $passwordEntry->setEncryptedPassword($encryptionService->encrypt($plain, $key));
            }

            $em->flush();
            $this->addFlash('success', '"' . $passwordEntry->getTitle() . '" mis à jour avec succès.');
        } else {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $this->addFlash('error', $errors ? implode(' ', $errors) : 'Formulaire invalide.');
        }

        return $this->redirectToRoute('app_dashboard');
    }
}
