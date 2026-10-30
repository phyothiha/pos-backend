<?php

namespace App\Http\Controllers\Inventory\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\Api\V1\SearchProductRequest;
use App\Http\Requests\Inventory\Api\V1\StoreProductRequest;
use App\Http\Requests\Inventory\Api\V1\UpdateProductRequest;
use App\Http\Resources\Inventory\Api\V1\ProductResource;
use App\Models\Inventory\Product;
use App\Services\Inventory\ProductService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService) {}

    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection($this->productService->paginate());
    }

    public function search(SearchProductRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->productService->search($request->validated('q')));
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        return new ProductResource($this->productService->create($request->validated()));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(['category', 'brand']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($this->productService->update($product, $request->validated()));
    }

    public function destroy(Product $product): Response
    {
        $this->productService->delete($product);

        return response()->noContent();
    }
}
