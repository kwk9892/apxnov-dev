<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    private const CACHE_KEY = 'categories.index.page1';

    /**
     * Display a listing of the resource.
     *
     * Only the first page is cached (the common case - category lists are
     * short and rarely paginated past page 1). Caching every page under one
     * shared key would let a request for page 2 silently overwrite what page
     * 1 callers see; caching every page under its own key would need the
     * invalidation below to track and clear every page number that has ever
     * been cached, which the database cache driver has no tag support for.
     * Any request for page 2+ bypasses the cache and hits the database.
     */
    #[OA\Get(
        path: '/categories',
        summary: 'List categories',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A paginated list of categories'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $page = (int) $request->integer('page', 1);

        if ($page !== 1) {
            return CategoryResource::collection(Category::query()->paginate(15, page: $page));
        }

        $categories = Cache::remember(self::CACHE_KEY, now()->addHour(), function () {
            return Category::query()->paginate(15, page: 1);
        });

        return CategoryResource::collection($categories);
    }

    #[OA\Post(
        path: '/categories',
        summary: 'Create a category',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Category created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): CategoryResource
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = Category::create($validated);

        Cache::forget(self::CACHE_KEY);

        return new CategoryResource($category);
    }

    #[OA\Get(
        path: '/categories/{category}',
        summary: 'Get a single category',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The category'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    #[OA\Put(
        path: '/categories/{category}',
        summary: 'Update a category',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Category updated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(Request $request, Category $category): CategoryResource
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        Cache::forget(self::CACHE_KEY);

        return new CategoryResource($category);
    }

    #[OA\Delete(
        path: '/categories/{category}',
        summary: 'Delete a category',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Category deleted'),
        ]
    )]
    public function destroy(Category $category): Response
    {
        $category->delete();

        Cache::forget(self::CACHE_KEY);

        return response()->noContent();
    }
}
