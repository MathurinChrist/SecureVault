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
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/v1/vaults', name: 'api_vault_')]
#[IsGranted('ROLE_USER')]
class VaultController extends AbstractController
{
    private const READ_CONTEXT  = [AbstractNormalizer::GROUPS => ['vault:read']];
    private const WRITE_CONTEXT = [AbstractNormalizer::GROUPS => ['vault:write']];

    public function __construct(
        private readonly VaultRepository $vaultRepository,
        private readonly EntityManagerInterface $em,
        private readonly SerializerInterface $serializer,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $vaults = $this->vaultRepository->findBy(['user' => $this->getUser()]);

        return $this->json($vaults, Response::HTTP_OK, [], self::READ_CONTEXT);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Vault $vault): JsonResponse
    {
        if ($vault->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        return $this->json($vault, Response::HTTP_OK, [], self::READ_CONTEXT);
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

        return $this->json($vault, Response::HTTP_CREATED, [], self::READ_CONTEXT);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(Vault $vault, Request $request): JsonResponse
    {
        if ($vault->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Access denied.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

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

        return $this->json($vault, Response::HTTP_OK, [], self::READ_CONTEXT);
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
}
