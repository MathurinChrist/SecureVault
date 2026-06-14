<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class VerifyEmailController extends AbstractController
{
    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        VerifyEmailHelperInterface $verifyEmailHelper,
    ): Response {
        $userId = $request->query->get('id');
        if (!$userId) {
            $this->addFlash('error', 'Lien de vérification invalide.');
            return $this->redirectToRoute('app_login');
        }

        $user = $userRepository->find((int) $userId);
        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isEmailVerified()) {
            $this->addFlash('success', 'Votre e-mail est déjà confirmé.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $verifyEmailHelper->validateEmailConfirmation(
                $request->getUri(),
                (string) $user->getId(),
                $user->getEmail(),
            );
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', 'Le lien de confirmation est invalide ou a expiré. Veuillez en demander un nouveau.');
            return $this->redirectToRoute('app_verify_pending');
        }

        $user->setEmailVerified(true);
        $em->flush();

        $this->addFlash('success', 'Votre adresse e-mail a été confirmée ! Vous pouvez maintenant vous connecter.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/pending', name: 'app_verify_pending')]
    public function verifyPending(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        return $this->render('registration/verify_pending.html.twig');
    }

    #[Route('/verify/resend', name: 'app_resend_verification', methods: ['POST'])]
    public function resendVerification(
        Request $request,
        UserRepository $userRepository,
        EmailVerificationService $emailVerificationService,
    ): Response {
        if (!$this->isCsrfTokenValid('resend_verification', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_verify_pending');
        }

        // Supports both logged-in users and the check_email page (email in POST body)
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            $email = $request->request->get('email', '');
            $user  = $userRepository->findByEmail($email);
        }

        if (!$user || $user->isEmailVerified()) {
            $this->addFlash('error', 'Impossible de renvoyer l\'e-mail.');
            return $this->redirectToRoute('app_login');
        }

        $emailVerificationService->sendVerificationEmail($user);

        $this->addFlash('success', 'Un nouvel e-mail de confirmation a été envoyé.');

        // Redirect back to the appropriate page
        if ($this->getUser()) {
            return $this->redirectToRoute('app_verify_pending');
        }

        return $this->render('registration/check_email.html.twig', [
            'email' => $user->getEmail(),
        ]);
    }
}
