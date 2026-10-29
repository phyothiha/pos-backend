<?php

namespace App\Http\Controllers\Inventory\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\Api\V1\SearchInventoryRequest;
use App\Http\Requests\Inventory\Api\V1\StoreBrandRequest;
use App\Http\Requests\Inventory\Api\V1\UpdateBrandRequest;
use App\Http\Resources\Inventory\Api\V1\BrandResource;
use App\Models\Inventory\Brand;
use App\Services\Inventory\BrandService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BrandController extends Controller
{
    public function __construct(private readonly BrandService $brandService) {}

    public function all(): AnonymousResourceCollection
    {
        return BrandResource::collection($this->brandService->all());
    }

    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection($this->brandService->paginate());
    }

    public function search(SearchInventoryRequest $request): AnonymousResourceCollection
    {
        return BrandResource::collection($this->brandService->search($request->validated('name')));
    }

    public function store(StoreBrandRequest $request): BrandResource
    {
        return new BrandResource($this->brandService->create($request->validated()));
    }

    public function show(Brand $brand): BrandResource
    {
        return new BrandResource($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        return new BrandResource($this->brandService->update($brand, $request->validated()));
    }

    public function destroy(Brand $brand): Response
    {
        $this->brandService->delete($brand);

        return response()->noContent();
    }
}
