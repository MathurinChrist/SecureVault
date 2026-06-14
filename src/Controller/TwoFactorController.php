<?php

namespace App\Controller;

use App\Service\TwoFactorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
class TwoFactorController extends AbstractController
{
    #[Route('/2fa/verify', name: 'app_2fa_verify', methods: ['GET', 'POST'])]
    public function verify(Request $request, TwoFactorService $twoFactorService): Response
    {
        if (!$twoFactorService->isPending($request)) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('2fa_verify', $request->request->get('_token'))) {
                $this->addFlash('error', 'Token CSRF invalide.');
                return $this->redirectToRoute('app_2fa_verify');
            }

            $code = trim((string) $request->request->get('code', ''));

            if ($twoFactorService->verifyCode($request, $code)) {
                $twoFactorService->clearPending($request);
                return $this->redirectToRoute('app_dashboard');
            }

            $this->addFlash('error', 'Code incorrect ou expiré. Vérifiez votre boîte e-mail.');
        }

        return $this->render('security/2fa_verify.html.twig', [
            'email' => $this->getUser()?->getUserIdentifier(),
        ]);
    }

    #[Route('/2fa/resend', name: 'app_2fa_resend', methods: ['POST'])]
    public function resend(Request $request, TwoFactorService $twoFactorService): Response
    {
        if (!$this->isCsrfTokenValid('2fa_resend', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_2fa_verify');
        }

        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        if (!$twoFactorService->isPending($request)) {
            return $this->redirectToRoute('app_dashboard');
        }

        $twoFactorService->generateAndSendCode($user, $request);
        $this->addFlash('success', 'Un nouveau code a été envoyé à ' . $user->getEmail() . '.');

        return $this->redirectToRoute('app_2fa_verify');
    }
}
