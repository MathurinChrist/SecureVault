<?php

namespace App\Controller\Api;

use App\Entity\Notification;
use App\Entity\SharedVault;
use App\Entity\User;
use App\Entity\Vault;
use App\Repository\SharedVaultRepository;
use App\Repository\UserRepository;
use App\Repository\VaultPermissionRepository;
use App\Security\Voter\VaultVoter;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Shares')]
#[Route('/api/v1', name: 'api_share_')]
#[IsGranted('ROLE_USER')]
class ShareController extends AbstractController
{
    public function __construct(
        private readonly SharedVaultRepository     $sharedVaultRepository,
        private readonly UserRepository            $userRepository,
        private readonly VaultPermissionRepository $permissionRepository,
        private readonly EntityManagerInterface    $em,
        private readonly NotificationService       $notificationService,
        private readonly ActivityLogService        $activityLogService,
    ) {}

    #[Route('/shares', name: 'list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Shares grouped as pending (received, not yet accepted), accepted (received) and sent (on the user\'s own vaults).')]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'pending'  => array_map($this->serializeShare(...), $this->sharedVaultRepository->findPendingForUser($user)),
            'accepted' => array_map($this->serializeShare(...), $this->sharedVaultRepository->findAcceptedForUser($user)),
            'sent'     => array_map($this->serializeShare(...), $this->sharedVaultRepository->findSentByUser($user)),
        ]);
    }

    #[Route('/vaults/{id}/share', name: 'create', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email'],
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'permission', type: 'string', default: 'READ', enum: ['READ', 'WRITE', 'ADMIN']),
        ],
    ))]
    #[OA\Response(response: 201, description: 'Share created.')]
    #[OA\Response(response: 200, description: 'Existing share updated with the new permission.')]
    #[OA\Response(response: 403, description: 'Caller lacks SHARE rights on the vault.')]
    #[OA\Response(response: 404, description: 'No user matches the given email.')]
    #[OA\Response(response: 422, description: 'Validation error.')]
    public function create(Vault $vault, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(VaultVoter::SHARE, $vault);

        /** @var User $sender */
        $sender = $this->getUser();

        $data  = json_decode($request->getContent(), true);
        $email = trim((string) ($data['email'] ?? ''));
        $code  = $data['permission'] ?? 'READ';

        if ($email === '') {
            return $this->json(['error' => 'email is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $recipient = $this->userRepository->findByEmail($email);
        if ($recipient === null) {
            return $this->json(['error' => 'No user found with this email.'], Response::HTTP_NOT_FOUND);
        }

        if ($recipient === $vault->getUser()) {
            return $this->json(['error' => 'Cannot share a vault with its owner.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($recipient === $sender) {
            return $this->json(['error' => 'Cannot share a vault with yourself.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $permission = $this->permissionRepository->findByCode($code);
        if ($permission === null) {
            return $this->json(['error' => 'Invalid permission code.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $this->sharedVaultRepository->findByVaultAndRecipient($vault, $recipient);
        if ($existing !== null) {
            $existing->setPermission($permission);
            $this->activityLogService->log($sender, 'Accès mis à jour pour ' . $email . ' sur "' . $vault->getName() . '"');
            $this->em->flush();

            return $this->json($this->serializeShare($existing), Response::HTTP_OK);
        }

        $share = (new SharedVault())
            ->setVault($vault)
            ->setSender($sender)
            ->setRecipient($recipient)
            ->setPermission($permission);
        $this->em->persist($share);
        $this->activityLogService->log($sender, 'Coffre partagé avec ' . $email . ' : "' . $vault->getName() . '"');
        $this->em->flush();

        $this->notificationService->create(
            $recipient,
            'Invitation à un coffre',
            sprintf('%s %s vous a invité à accéder au coffre « %s » avec le niveau %s.',
                $sender->getFirstName(), $sender->getLastName(),
                $vault->getName(), $permission->getName()),
            Notification::TYPE_SHARE,
        );

        return $this->json($this->serializeShare($share), Response::HTTP_CREATED);
    }

    #[Route('/shares/{id}/accept', name: 'accept', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[OA\Response(response: 200, description: 'Invitation accepted.')]
    #[OA\Response(response: 403, description: 'Caller is not the recipient.')]
    public function accept(SharedVault $share): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($share->getRecipient() !== $user) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $vaultName = $share->getVault()->getName();
        $share->accept();
        $this->activityLogService->log($user, 'Accès accepté : coffre "' . $vaultName . '"');
        $this->em->flush();

        $this->notificationService->create(
            $share->getSender(),
            'Invitation acceptée',
            sprintf('%s %s a accepté votre invitation et peut maintenant accéder au coffre « %s ».',
                $user->getFirstName(), $user->getLastName(), $vaultName),
            Notification::TYPE_SUCCESS,
        );

        return $this->json($this->serializeShare($share), Response::HTTP_OK);
    }

    #[Route('/shares/{id}/decline', name: 'decline', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[OA\Response(response: 204, description: 'Invitation declined and removed.')]
    #[OA\Response(response: 403, description: 'Caller is not the recipient.')]
    public function decline(SharedVault $share): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($share->getRecipient() !== $user) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $vaultName = $share->getVault()->getName();
        $sender    = $share->getSender();
        $this->em->remove($share);
        $this->activityLogService->log($user, 'Invitation refusée : coffre "' . $vaultName . '"');
        $this->em->flush();

        $this->notificationService->create(
            $sender,
            'Invitation refusée',
            sprintf('%s %s a refusé votre invitation pour le coffre « %s ».',
                $user->getFirstName(), $user->getLastName(), $vaultName),
            Notification::TYPE_WARNING,
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/shares/{id}', name: 'revoke', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Share revoked.')]
    #[OA\Response(response: 403, description: 'Caller is neither the sender nor the vault owner.')]
    public function revoke(SharedVault $share): JsonResponse
    {
        /** @var User $user */
        $user       = $this->getUser();
        $vaultOwner = $share->getVault()->getUser();

        if ($share->getSender() !== $user && $vaultOwner !== $user) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $recipient = $share->getRecipient();
        $vaultName = $share->getVault()->getName();
        $this->em->remove($share);
        $this->activityLogService->log($user, 'Accès révoqué pour ' . $recipient->getEmail() . ' sur "' . $vaultName . '"');
        $this->em->flush();

        $this->notificationService->create(
            $recipient,
            'Accès révoqué',
            sprintf('Votre accès au coffre « %s » a été révoqué.', $vaultName),
            Notification::TYPE_WARNING,
        );

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serializeShare(SharedVault $share): array
    {
        return [
            'id'         => $share->getId(),
            'accepted'   => $share->isAccepted(),
            'sharedAt'   => $share->getSharedAt()?->format(\DateTimeInterface::ATOM),
            'acceptedAt' => $share->getAcceptedAt()?->format(\DateTimeInterface::ATOM),
            'vault'      => [
                'id'   => $share->getVault()->getId(),
                'name' => $share->getVault()->getName(),
            ],
            'sender'     => $this->serializeUser($share->getSender()),
            'recipient'  => $this->serializeUser($share->getRecipient()),
            'permission' => [
                'code' => $share->getPermission()->getCode(),
                'name' => $share->getPermission()->getName(),
            ],
        ];
    }

    private function serializeUser(User $user): array
    {
        return [
            'id'        => $user->getId(),
            'email'     => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName'  => $user->getLastName(),
        ];
    }
}
