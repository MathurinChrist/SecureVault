<?php

namespace App\Controller;

use App\Entity\PasswordEntry;
use App\Form\PasswordEntryType;
use App\Repository\AlertRepository;
use App\Repository\PasswordEntryRepository;
use App\Repository\VaultRepository;
use App\Service\AlertService;
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
    public function __construct(
        #[Autowire(env: 'VAULT_ENCRYPTION_KEY')]
        private readonly string $encryptionKey,
    ) {}

    #[Route('/vaults', name: 'app_vaults')]
    public function index(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'title' => 'Mes Coffres',
        ]);
    }

    #[Route('/passwords', name: 'app_passwords')]
    public function passwords(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'title' => 'Mots de passe',
        ]);
    }

    #[Route('/shares', name: 'app_shares')]
    public function shares(): Response
    {
        return $this->render('dashboard/placeholder.html.twig', [
            'title' => 'Partages',
        ]);
    }

    #[Route('/alerts', name: 'app_alerts')]
    public function alerts(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        $alerts = $entityManager->getRepository(\App\Entity\Alert::class)->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('alerts/index.html.twig', [
            'alerts' => $alerts,
        ]);
    }

    #[Route('/alerts/mark-as-read/{id}', name: 'app_alerts_mark_read')]
    public function markRead(int $id, AlertRepository $alertRepository, AlertService $alertService): Response
    {
        $alert = $alertRepository->find($id);
        if ($alert && $alert->getUser() === $this->getUser()) {
            $alertService->markAsRead($alert);
            $this->addFlash('success', 'Alerte marquée comme lue.');
        }

        return $this->redirectToRoute('app_alerts');
    }

    #[Route('/alerts/mark-all-read', name: 'app_alerts_mark_all_read')]
    public function markAllRead(AlertService $alertService): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $alertService->markAllAsRead($user);
        $this->addFlash('success', 'Toutes les alertes ont été marquées comme lues.');

        return $this->redirectToRoute('app_alerts');
    }

    #[Route('/alerts/dismiss/{id}', name: 'app_alerts_dismiss')]
    public function dismiss(int $id, AlertRepository $alertRepository, EntityManagerInterface $entityManager): Response
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
    public function setup2fa(): Response
    {
        $this->addFlash('info', 'La configuration 2FA n\'est pas encore disponible.');
        return $this->redirectToRoute('app_profile');
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
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $vault = $passwordEntry->getVault();

        if ($vault === null || $vault->getUser()->getUserIdentifier() !== $user->getUserIdentifier()) {
            throw $this->createAccessDeniedException();
        }

        $vaults = $vaultRepository->findByUser($user);

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
