<?php

namespace App\Controller\Api;

use App\Entity\Vault;
use App\Repository\VaultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/vaults', name: 'api_vault_')]
#[IsGranted('ROLE_USER')]
class VaultController extends AbstractController
{
    public function __construct(
        private readonly VaultRepository $vaultRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $vaults = $this->vaultRepository->findBy(['user' => $this->getUser()]);

        return $this->json(array_map(fn(Vault $v) => $this->serialize($v), $vaults));
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Vault $vault): JsonResponse
    {
        if ($vault->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($this->serialize($vault));
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name'])) {
            return $this->json(['error' => 'name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $vault = (new Vault())
            ->setName(trim($data['name']))
            ->setDescription($data['description'] ?? null)
            ->setUser($this->getUser());

        $this->em->persist($vault);
        $this->em->flush();

        return $this->json($this->serialize($vault), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(Vault $vault, Request $request): JsonResponse
    {
        if ($vault->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['name'])) {
            if (empty(trim($data['name']))) {
                return $this->json(['error' => 'name cannot be empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $vault->setName(trim($data['name']));
        }

        if (array_key_exists('description', $data)) {
            $vault->setDescription($data['description']);
        }

        if (isset($data['archived'])) {
            $vault->setArchived((bool) $data['archived']);
        }

        $this->em->flush();

        return $this->json($this->serialize($vault));
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Vault $vault): JsonResponse
    {
        if ($vault->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($vault);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function serialize(Vault $vault): array
    {
        return [
            'id'             => $vault->getId(),
            'name'           => $vault->getName(),
            'description'    => $vault->getDescription(),
            'archived'       => $vault->isArchived(),
            'entries_count'  => $vault->getPasswordEntries()->count(),
            'created_at'     => $vault->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at'     => $vault->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
