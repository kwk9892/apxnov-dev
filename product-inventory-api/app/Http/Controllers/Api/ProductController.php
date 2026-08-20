<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    #[OA\Get(
        path: '/products',
        summary: 'List products',
        description: 'Returns a paginated list of products, optionally filtered by category, price range, and stock level.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'category_id', in: 'query', schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'min_price', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'max_price', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'stock_level', in: 'query', schema: new OA\Schema(type: 'string', enum: ['out_of_stock', 'low_stock', 'in_stock'])),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A paginated list of products'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->with(['category', 'suppliers']);

        if ($request->filled('category_id')) {
            $query->category((int) $request->integer('category_id'));
        }

        if ($request->filled('min_price') || $request->filled('max_price')) {
            $query->priceBetween(
                $request->filled('min_price') ? (float) $request->input('min_price') : null,
                $request->filled('max_price') ? (float) $request->input('max_price') : null,
            );
        }

        if ($request->filled('stock_level')) {
            $query->stockLevel($request->string('stock_level')->toString());
        }

        $perPage = (int) $request->integer('per_page', 15);
        $perPage = max(1, min($perPage, 100));

        return ProductResource::collection($query->paginate($perPage));
    }

    #[OA\Post(
        path: '/products',
        summary: 'Create a product',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['category_id', 'name', 'sku', 'price', 'stock'],
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'sku', type: 'string'),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'stock', type: 'integer'),
                    new OA\Property(property: 'supplier_ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreProductRequest $request): ProductResource
    {
        $product = Product::create($request->safe()->except('supplier_ids'));

        if ($request->filled('supplier_ids')) {
            $product->suppliers()->sync($request->input('supplier_ids'));
            $product->load(['category', 'suppliers']);
        } else {
            // A newly created product has no suppliers yet - skip the pivot
            // query and set the relation to an empty collection directly.
            $product->setRelation('suppliers', $product->suppliers()->getRelated()->newCollection());
            $product->load('category');
        }

        return new ProductResource($product);
    }

    #[OA\Get(
        path: '/products/{product}',
        summary: 'Get a single product',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'The product'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'suppliers']));
    }

    #[OA\Put(
        path: '/products/{product}',
        summary: 'Update a product',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product updated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->safe()->except('supplier_ids'));

        if ($request->has('supplier_ids')) {
            $product->suppliers()->sync($request->input('supplier_ids'));
        }

        return new ProductResource($product->load(['category', 'suppliers']));
    }

    #[OA\Delete(
        path: '/products/{product}',
        summary: 'Soft-delete a product',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Product deleted'),
        ]
    )]
    public function destroy(Product $product): Response
    {
        $product->delete();

        return response()->noContent();
    }
}
