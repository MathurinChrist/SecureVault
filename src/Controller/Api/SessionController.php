<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Entity\UserSession;
use App\Repository\UserSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[OA\Tag(name: 'Sessions')]
#[Route('/api/v1/sessions', name: 'api_session_')]
#[IsGranted('ROLE_USER')]
class SessionController extends AbstractController
{
    private const READ_CONTEXT = [AbstractNormalizer::GROUPS => ['session:read']];

    public function __construct(
        private readonly UserSessionRepository  $sessionRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(response: 200, description: "The current user's active sessions, most recently used first.")]
    public function list(): JsonResponse
    {
        $sessions = $this->sessionRepository->findActiveSessionsByUser($this->getUser());

        return $this->json($sessions, Response::HTTP_OK, [], self::READ_CONTEXT);
    }

    #[Route('/{id}', name: 'revoke', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Session revoked (marked inactive).')]
    #[OA\Response(response: 403, description: 'The session does not belong to the current user.')]
    public function revoke(UserSession $session): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($session->getUser() !== $user) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $session->setIsActive(false);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
