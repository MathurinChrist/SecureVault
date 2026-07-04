<?php

namespace App\Controller\Api;

use App\Entity\PasswordEntry;
use App\Entity\Vault;
use App\Repository\PasswordEntryRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/vaults/{vaultId}/passwords', name: 'api_password_', requirements: ['vaultId' => '\d+'])]
#[IsGranted('ROLE_USER')]
class PasswordController extends AbstractController
{
    public function __construct(
        private readonly PasswordEntryRepository $passwordEntryRepository,
        private readonly EncryptionService $encryptionService,
        private readonly EntityManagerInterface $em,
        #[Autowire(env: 'VAULT_ENCRYPTION_KEY')] private readonly string $encryptionKey,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(int $vaultId): JsonResponse
    {
        $vault = $this->findVaultOrFail($vaultId);
        if ($vault instanceof JsonResponse) {
            return $vault;
        }

        $entries = $this->passwordEntryRepository->findBy(['vault' => $vault]);

        return $this->json(array_map(fn(PasswordEntry $e) => $this->serializeSafe($e), $entries));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(int $vaultId, Request $request): JsonResponse
    {
        $vault = $this->findVaultOrFail($vaultId);
        if ($vault instanceof JsonResponse) {
            return $vault;
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['title']) || empty($data['password'])) {
            return $this->json(['error' => 'title and password are required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $key   = hash('sha256', $this->encryptionKey, true);
        $entry = (new PasswordEntry())
            ->setTitle(trim($data['title']))
            ->setUsername($data['username'] ?? null)
            ->setUrl($data['url'] ?? null)
            ->setNotes($data['notes'] ?? null)
            ->setFavorite((bool) ($data['favorite'] ?? false))
            ->setEncryptedPassword($this->encryptionService->encrypt($data['password'], $key))
            ->setVault($vault)
            ->setUser($this->getUser());

        $this->em->persist($entry);
        $this->em->flush();

        return $this->json($this->serializeSafe($entry), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $vaultId, PasswordEntry $entry): JsonResponse
    {
        $check = $this->checkEntryAccess($vaultId, $entry);
        if ($check instanceof JsonResponse) {
            return $check;
        }

        $key = hash('sha256', $this->encryptionKey, true);

        return $this->json([
            ...$this->serializeSafe($entry),
            'password' => $this->encryptionService->decrypt($entry->getEncryptedPassword(), $key),
        ]);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(int $vaultId, PasswordEntry $entry, Request $request): JsonResponse
    {
        $check = $this->checkEntryAccess($vaultId, $entry);
        if ($check instanceof JsonResponse) {
            return $check;
        }

        $data = json_decode($request->getContent(), true);
        $key  = hash('sha256', $this->encryptionKey, true);

        if (isset($data['title'])) {
            if (empty(trim($data['title']))) {
                return $this->json(['error' => 'title cannot be empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $entry->setTitle(trim($data['title']));
        }

        if (array_key_exists('username', $data)) {
            $entry->setUsername($data['username']);
        }

        if (array_key_exists('url', $data)) {
            $entry->setUrl($data['url']);
        }

        if (array_key_exists('notes', $data)) {
            $entry->setNotes($data['notes']);
        }

        if (isset($data['favorite'])) {
            $entry->setFavorite((bool) $data['favorite']);
        }

        if (isset($data['password'])) {
            if (empty($data['password'])) {
                return $this->json(['error' => 'password cannot be empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $entry->setEncryptedPassword($this->encryptionService->encrypt($data['password'], $key));
        }

        $this->em->flush();

        return $this->json($this->serializeSafe($entry));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(int $vaultId, PasswordEntry $entry): JsonResponse
    {
        $check = $this->checkEntryAccess($vaultId, $entry);
        if ($check instanceof JsonResponse) {
            return $check;
        }

        $this->em->remove($entry);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function findVaultOrFail(int $vaultId): Vault|JsonResponse
    {
        $vault = $this->em->find(Vault::class, $vaultId);

        if (!$vault) {
            return $this->json(['error' => 'Vault not found.'], Response::HTTP_NOT_FOUND);
        }

        if ($vault->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $vault;
    }

    private function checkEntryAccess(int $vaultId, PasswordEntry $entry): ?JsonResponse
    {
        $vault = $this->findVaultOrFail($vaultId);
        if ($vault instanceof JsonResponse) {
            return $vault;
        }

        if ($entry->getVault() !== $vault) {
            return $this->json(['error' => 'Not found.'], Response::HTTP_NOT_FOUND);
        }

        return null;
    }

    private function serializeSafe(PasswordEntry $entry): array
    {
        return [
            'id'         => $entry->getId(),
            'title'      => $entry->getTitle(),
            'username'   => $entry->getUsername(),
            'url'        => $entry->getUrl(),
            'notes'      => $entry->getNotes(),
            'favorite'   => $entry->isFavorite(),
            'created_at' => $entry->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at' => $entry->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
