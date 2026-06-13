<?php

namespace App\Controller;

use App\Entity\PasswordEntry;


use App\Entity\Vault;
use App\Form\PasswordEntryType;
use App\Form\VaultType;
use App\Repository\AlertRepository;
use App\Repository\PasswordEntryRepository;
use App\Repository\VaultRepository;
use App\Service\ActivityLogService;
use App\Service\AlertService;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
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
        private readonly ActivityLogService $activityLogService,
    ) {}

    // ============================= VAULTS =============================

    #[Route('/vaults', name: 'app_vaults', methods: ['GET'])]
    public function index(VaultRepository $vaultRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $vaults = $vaultRepository->findByUser($user);

        $createForm = $this->createForm(VaultType::class, new Vault(), [
            'action' => $this->generateUrl('app_vault_new'),
            'method' => 'POST',
        ]);

        return $this->render('vault/index.html.twig', [
            'vaults'      => $vaults,
            'create_form' => $createForm->createView(),
            'open_modal'  => false,
        ]);
    }

    #[Route('/vaults/new', name: 'app_vault_new', methods: ['POST'])]
    public function newVault(Request $request, EntityManagerInterface $em, VaultRepository $vaultRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user  = $this->getUser();
        $vault = new Vault();
        $form  = $this->createForm(VaultType::class, $vault, [
            'action' => $this->generateUrl('app_vault_new'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $vault->setUser($user);
            $em->persist($vault);
            $this->activityLogService->log($user, 'Coffre créé : ' . $vault->getName());
            $em->flush();
            $this->addFlash('success', 'Coffre "' . $vault->getName() . '" créé.');
            return $this->redirectToRoute('app_vaults');
        }

        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }
        $this->addFlash('error', $errors ? implode(' ', $errors) : 'Formulaire invalide.');

        return $this->redirectToRoute('app_vaults');
    }

    #[Route('/vaults/{id}', name: 'app_vault_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Vault $vault, Request $request, FormFactoryInterface $formFactory, VaultRepository $vaultRepository): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $vault);

        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $vaults = $vaultRepository->findByUser($user);

        $newEntry = new PasswordEntry();
        $newEntry->setVault($vault);

        $addForm = $formFactory->createNamed('add_password_entry', PasswordEntryType::class, $newEntry, [
            'vaults'           => $vaults,
            'require_password' => true,
        ]);

        $editForm = $formFactory->createNamed('edit_password_entry', PasswordEntryType::class, new PasswordEntry(), [
            'vaults'           => $vaults,
            'require_password' => false,
        ]);

        return $this->render('vault/show.html.twig', [
            'vault'         => $vault,
            'password_form' => $addForm->createView(),
            'edit_form'     => $editForm->createView(),
            'open_add_modal' => $request->query->get('modal') === 'add',
        ]);
    }

    #[Route('/vaults/{id}/edit', name: 'app_vault_edit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function editVault(Vault $vault, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $vault);

        if (!$this->isCsrfTokenValid('vault_edit_' . $vault->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vaults');
        }

        $name = trim((string) $request->request->get('name', ''));
        if ($name === '') {
            $this->addFlash('error', 'Le nom du coffre est obligatoire.');
            return $this->redirectToRoute('app_vaults');
        }

        $vault->setName($name);
        $vault->setDescription($request->request->get('description') ?: null);
        $em->flush();
        $this->addFlash('success', 'Coffre mis à jour.');

        return $this->redirectToRoute('app_vaults');
    }

    #[Route('/vaults/{id}/archive', name: 'app_vault_archive', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function archiveVault(Vault $vault, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('EDIT', $vault);

        if (!$this->isCsrfTokenValid('vault_archive_' . $vault->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vaults');
        }

        $vault->setArchived(!$vault->isArchived());
        $em->flush();

        $this->addFlash('success', $vault->isArchived() ? 'Coffre archivé.' : 'Coffre restauré.');

        $referer = $request->headers->get('referer', '');
        if (str_contains($referer, '/vaults/' . $vault->getId())) {
            return $this->redirectToRoute('app_vault_show', ['id' => $vault->getId()]);
        }

        return $this->redirectToRoute('app_vaults');
    }

    #[Route('/vaults/{id}/delete', name: 'app_vault_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteVault(Vault $vault, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('DELETE', $vault);

        if (!$this->isCsrfTokenValid('delete_vault_' . $vault->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vaults');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $name = $vault->getName();
        $em->remove($vault);
        $this->activityLogService->log($user, 'Coffre supprimé : ' . $name);
        $em->flush();
        $this->addFlash('success', 'Coffre "' . $name . '" supprimé.');

        return $this->redirectToRoute('app_vaults');
    }

    // ============================= PASSWORDS =============================

    #[Route('/passwords', name: 'app_passwords', methods: ['GET'])]
    public function passwords(
        PasswordEntryRepository $passwordEntryRepository,
        VaultRepository $vaultRepository,
        FormFactoryInterface $formFactory,
        Request $request,
    ): Response {
        /** @var \App\Entity\User $user */
        $user      = $this->getUser();
        $passwords = $passwordEntryRepository->findByUser($user);
        $vaults    = $vaultRepository->findByUser($user);

        $addForm = $formFactory->createNamed('add_password_entry', PasswordEntryType::class, new PasswordEntry(), [
            'vaults'           => $vaults,
            'require_password' => true,
        ]);

        $editForm = $formFactory->createNamed('edit_password_entry', PasswordEntryType::class, new PasswordEntry(), [
            'vaults'           => $vaults,
            'require_password' => false,
        ]);

        return $this->render('passwords/index.html.twig', [
            'passwords'      => $passwords,
            'vaults'         => $vaults,
            'password_form'  => $addForm->createView(),
            'edit_form'      => $editForm->createView(),
            'open_add_modal' => $request->query->get('modal') === 'add',
        ]);
    }

    #[Route('/passwords/new', name: 'app_password_new', methods: ['POST'])]
    public function newPassword(
        Request $request,
        EntityManagerInterface $em,
        VaultRepository $vaultRepository,
        EncryptionService $encryptionService,
        FormFactoryInterface $formFactory,
    ): Response {
        /** @var \App\Entity\User $user */
        $user   = $this->getUser();
        $vaults = $vaultRepository->findByUser($user);
        $entry  = new PasswordEntry();

        $form = $formFactory->createNamed('add_password_entry', PasswordEntryType::class, $entry, [
            'vaults'           => $vaults,
            'require_password' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $key   = hash('sha256', $this->encryptionKey, true);
            $entry->setEncryptedPassword($encryptionService->encrypt($plain, $key));
            $entry->setUser($user);
            $em->persist($entry);
            $this->activityLogService->log($user, 'Mot de passe ajouté : ' . $entry->getTitle());
            $em->flush();
            $this->addFlash('success', '"' . $entry->getTitle() . '" ajouté.');
        } else {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $this->addFlash('error', $errors ? implode(' ', $errors) : 'Formulaire invalide.');

            $referer = $request->headers->get('referer', '');
            if (preg_match('#/vaults/(\d+)#', $referer, $m)) {
                return $this->redirectToRoute('app_vault_show', ['id' => $m[1], 'modal' => 'add']);
            }
            if (str_contains($referer, '/passwords')) {
                return $this->redirectToRoute('app_passwords', ['modal' => 'add']);
            }

            return $this->redirectToRoute('app_dashboard');
        }

        $referer = $request->headers->get('referer', '');
        if (preg_match('#/vaults/(\d+)#', $referer, $m)) {
            return $this->redirectToRoute('app_vault_show', ['id' => $m[1]]);
        }
        if (str_contains($referer, '/passwords')) {
            return $this->redirectToRoute('app_passwords');
        }

        return $this->redirectToRoute('app_dashboard');
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
        $user  = $this->getUser();
        $vault = $passwordEntry->getVault();

        if ($vault === null || !$this->isGranted('EDIT', $vault)) {
            $this->addFlash('error', 'Vous n\'avez pas la permission de modifier les mots de passe de ce coffre.');
            return $this->redirectToRoute('app_vault_show', ['id' => $vault?->getId()]);
        }

        $vaults = $vaultRepository->findByUser($user);
        $form   = $formFactory->createNamed('edit_password_entry', PasswordEntryType::class, $passwordEntry, [
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
            $this->activityLogService->log($user, 'Mot de passe modifié : ' . $passwordEntry->getTitle());
            $em->flush();
            $this->addFlash('success', '"' . $passwordEntry->getTitle() . '" mis à jour.');
        } else {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            $this->addFlash('error', $errors ? implode(' ', $errors) : 'Formulaire invalide.');
        }

        $referer = $request->headers->get('referer', '');
        if (preg_match('#/vaults/(\d+)#', $referer, $m)) {
            return $this->redirectToRoute('app_vault_show', ['id' => $m[1]]);
        }
        if (str_contains($referer, '/passwords')) {
            return $this->redirectToRoute('app_passwords');
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/passwords/{id}/delete', name: 'app_password_delete', methods: ['POST'])]
    public function deletePassword(
        PasswordEntry $passwordEntry,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var \App\Entity\User $user */
        $user  = $this->getUser();
        $vault = $passwordEntry->getVault();

        if ($vault === null || !$this->isGranted('EDIT', $vault)) {
            $this->addFlash('error', 'Vous n\'avez pas la permission de supprimer les mots de passe de ce coffre.');
            return $this->redirectToRoute('app_vault_show', ['id' => $vault?->getId()]);
        }

        if (!$this->isCsrfTokenValid('delete_password_' . $passwordEntry->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_dashboard');
        }

        $title = $passwordEntry->getTitle();
        $em->remove($passwordEntry);
        $this->activityLogService->log($user, 'Mot de passe supprimé : ' . $title);
        $em->flush();
        $this->addFlash('success', '"' . $title . '" supprimé.');

        $referer = $request->headers->get('referer', '');
        if (preg_match('#/vaults/(\d+)#', $referer, $m)) {
            return $this->redirectToRoute('app_vault_show', ['id' => $m[1]]);
        }
        if (str_contains($referer, '/passwords')) {
            return $this->redirectToRoute('app_passwords');
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/passwords/{id}/decrypt', name: 'app_password_decrypt', methods: ['GET'])]
    public function decryptPassword(
        PasswordEntry $passwordEntry,
        EncryptionService $encryptionService,
    ): JsonResponse {
        /** @var \App\Entity\User $user */
        $user  = $this->getUser();
        $vault = $passwordEntry->getVault();

        if ($vault === null || !$this->isGranted('VIEW', $vault)) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        $key   = hash('sha256', $this->encryptionKey, true);
        $plain = $encryptionService->decrypt($passwordEntry->getEncryptedPassword(), $key);

        $this->activityLogService->log($user, 'Mot de passe consulté : ' . $passwordEntry->getTitle());

        return $this->json(['password' => $plain]);
    }

    // ============================= ALERTS =============================

    #[Route('/alerts', name: 'app_alerts')]
    public function alerts(EntityManagerInterface $entityManager): Response
    {
        $user   = $this->getUser();
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

}
