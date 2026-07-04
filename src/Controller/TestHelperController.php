<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Test-only helpers. Returns 404 in production.
 */
class TestHelperController extends AbstractController
{
    #[Route('/test/verify-email', name: 'test_verify_email', methods: ['GET'])]
    public function quickVerifyEmail(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        #[Autowire(param: 'kernel.environment')] string $env,
    ): Response {
        if ($env === 'prod') {
            throw $this->createNotFoundException();
        }

        $email = $request->query->get('email', '');
        $user  = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return new Response('user not found', 404);
        }

        $user->setEmailVerified(true);
        $em->flush();

        return new Response('ok');
    }
}
