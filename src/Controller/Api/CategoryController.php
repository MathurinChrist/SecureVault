<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[OA\Tag(name: 'Categories')]
#[Route('/api/v1/categories', name: 'api_category_')]
#[IsGranted('ROLE_USER')]
class CategoryController extends AbstractController
{
    private const READ_CONTEXT = [AbstractNormalizer::GROUPS => ['category:read']];

    public function __construct(
        private readonly CategoryRepository     $categoryRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'List of all categories (global reference data).')]
    public function list(): JsonResponse
    {
        return $this->json($this->categoryRepository->findBy([], ['name' => 'ASC']), Response::HTTP_OK, [], self::READ_CONTEXT);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[OA\Response(response: 200, description: 'A single category.')]
    #[OA\Response(response: 404, description: 'Category not found.')]
    public function show(Category $category): JsonResponse
    {
        return $this->json($category, Response::HTTP_OK, [], self::READ_CONTEXT);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['name'],
        properties: [
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'color', type: 'string', example: '#2f7d5b', nullable: true),
        ],
    ))]
    #[OA\Response(response: 201, description: 'Category created.')]
    #[OA\Response(response: 422, description: 'Validation error.')]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['name']) || trim($data['name']) === '') {
            return $this->json(['error' => 'name is required.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = (new Category())
            ->setName(trim($data['name']))
            ->setColor($data['color'] ?? null);

        $this->em->persist($category);
        $this->em->flush();

        return $this->json($category, Response::HTTP_CREATED, [], self::READ_CONTEXT);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    #[OA\Response(response: 200, description: 'Category updated.')]
    #[OA\Response(response: 422, description: 'Validation error.')]
    public function update(Category $category, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if (isset($data['name'])) {
            if (trim($data['name']) === '') {
                return $this->json(['error' => 'name cannot be empty.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $category->setName(trim($data['name']));
        }

        if (array_key_exists('color', $data)) {
            $category->setColor($data['color']);
        }

        $this->em->flush();

        return $this->json($category, Response::HTTP_OK, [], self::READ_CONTEXT);
    }

    #[Route('/{id}', name: 'delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Category deleted.')]
    public function delete(Category $category): JsonResponse
    {
        $this->em->remove($category);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
