<?php

namespace App\Controller;

use App\Entity\SharedVault;
use App\Entity\Vault;
use App\Repository\SharedVaultRepository;
use App\Repository\UserRepository;
use App\Repository\VaultPermissionRepository;
use App\Repository\VaultRepository;
use App\Entity\Notification;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ShareController extends AbstractController
{
    public function __construct(
        private readonly ActivityLogService  $activityLogService,
        private readonly NotificationService $notificationService,
    ) {}
    #[Route('/shares', name: 'app_shares', methods: ['GET'])]
    public function index(SharedVaultRepository $sharedVaultRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        return $this->render('shares/index.html.twig', [
            'pending'  => $sharedVaultRepository->findPendingForUser($user),
            'accepted' => $sharedVaultRepository->findAcceptedForUser($user),
            'sent'     => $sharedVaultRepository->findSentByUser($user),
        ]);
    }

    #[Route('/vaults/{id}/shares', name: 'app_vault_shares', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function vaultShares(
        Vault $vault,
        VaultPermissionRepository $permissionRepository,
    ): Response {
        $this->denyAccessUnlessGranted('SHARE', $vault);

        return $this->render('vault/shares.html.twig', [
            'vault'       => $vault,
            'shares'      => $vault->getSharedVaults(),
            'permissions' => $permissionRepository->findAll(),
        ]);
    }

    #[Route('/vaults/{id}/share', name: 'app_vault_share_new', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function share(
        Vault $vault,
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        VaultPermissionRepository $permissionRepository,
        SharedVaultRepository $sharedVaultRepository,
    ): Response {
        $this->denyAccessUnlessGranted('SHARE', $vault);

        if (!$this->isCsrfTokenValid('vault_share_' . $vault->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
        }

        /** @var \App\Entity\User $sender */
        $sender = $this->getUser();
        $email  = trim((string) $request->request->get('email', ''));
        $code   = $request->request->get('permission', 'READ');

        if ($email === '') {
            $this->addFlash('error', 'L\'adresse email est obligatoire.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
        }

        $recipient = $userRepository->findByEmail($email);
        if ($recipient === null) {
            $this->addFlash('error', 'Aucun utilisateur trouvé avec cet email.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
        }

        if ($recipient === $vault->getUser()) {
            $this->addFlash('error', 'Vous ne pouvez pas partager un coffre avec son propriétaire.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
        }

        if ($recipient === $sender) {
            $this->addFlash('error', 'Vous ne pouvez pas vous inviter vous-même.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
        }

        $permission = $permissionRepository->findByCode($code);
        if ($permission === null) {
            $this->addFlash('error', 'Niveau d\'accès invalide.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
        }

        $existing = $sharedVaultRepository->findByVaultAndRecipient($vault, $recipient);
        if ($existing !== null) {
            $existing->setPermission($permission);
            $this->activityLogService->log($sender, 'Accès mis à jour pour ' . $email . ' sur "' . $vault->getName() . '"');
            $em->flush();
            $this->addFlash('success', 'Niveau d\'accès mis à jour pour ' . $email . '.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
        }

        $share = new SharedVault();
        $share->setVault($vault);
        $share->setSender($sender);
        $share->setRecipient($recipient);
        $share->setPermission($permission);
        $em->persist($share);
        $this->activityLogService->log($sender, 'Coffre partagé avec ' . $email . ' : "' . $vault->getName() . '"');
        $em->flush();

        $this->notificationService->create(
            $recipient,
            'Invitation à un coffre',
            sprintf('%s %s vous a invité à accéder au coffre « %s » avec le niveau %s.',
                $sender->getFirstName(), $sender->getLastName(),
                $vault->getName(), $permission->getName()),
            Notification::TYPE_SHARE,
        );

        $this->addFlash('success', 'Invitation envoyée à ' . $email . '.');

        return $this->redirectToRoute('app_vault_shares', ['id' => $vault->getId()]);
    }

    #[Route('/shares/{id}/accept', name: 'app_share_accept', methods: ['POST'])]
    public function accept(
        SharedVault $share,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($share->getRecipient() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('share_accept_' . $share->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_shares');
        }

        $vaultName = $share->getVault()->getName();
        $share->accept();
        $this->activityLogService->log($user, 'Accès accepté : coffre "' . $vaultName . '"');
        $em->flush();

        $this->notificationService->create(
            $share->getSender(),
            'Invitation acceptée',
            sprintf('%s %s a accepté votre invitation et peut maintenant accéder au coffre « %s ».',
                $user->getFirstName(), $user->getLastName(), $vaultName),
            Notification::TYPE_SUCCESS,
        );

        $this->addFlash('success', 'Accès au coffre "' . $vaultName . '" accepté.');

        return $this->redirectToRoute('app_shares');
    }

    #[Route('/shares/{id}/decline', name: 'app_share_decline', methods: ['POST'])]
    public function decline(
        SharedVault $share,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if ($share->getRecipient() !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('share_decline_' . $share->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_shares');
        }

        $vaultName = $share->getVault()->getName();
        $sender    = $share->getSender();
        $em->remove($share);
        $this->activityLogService->log($user, 'Invitation refusée : coffre "' . $vaultName . '"');
        $em->flush();

        $this->notificationService->create(
            $sender,
            'Invitation refusée',
            sprintf('%s %s a refusé votre invitation pour le coffre « %s ».',
                $user->getFirstName(), $user->getLastName(), $vaultName),
            Notification::TYPE_WARNING,
        );

        $this->addFlash('success', 'Invitation refusée.');

        return $this->redirectToRoute('app_shares');
    }

    #[Route('/shares/{id}/revoke', name: 'app_share_revoke', methods: ['POST'])]
    public function revoke(
        SharedVault $share,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $vaultOwner = $share->getVault()->getUser();

        if ($share->getSender() !== $user && $vaultOwner !== $user) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('share_revoke_' . $share->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_vault_shares', ['id' => $share->getVault()->getId()]);
        }

        $recipient      = $share->getRecipient();
        $recipientEmail = $recipient->getEmail();
        $vaultName      = $share->getVault()->getName();
        $em->remove($share);
        $this->activityLogService->log($user, 'Accès révoqué pour ' . $recipientEmail . ' sur "' . $vaultName . '"');
        $em->flush();

        $this->notificationService->create(
            $recipient,
            'Accès révoqué',
            sprintf('Votre accès au coffre « %s » a été révoqué.', $vaultName),
            Notification::TYPE_WARNING,
        );

        $this->addFlash('success', 'Accès révoqué pour ' . $recipientEmail . '.');

        return $this->redirectToRoute('app_vault_shares', ['id' => $share->getVault()->getId()]);
    }
}
