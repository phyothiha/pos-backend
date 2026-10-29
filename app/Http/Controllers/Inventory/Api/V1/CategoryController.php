<?php

namespace App\Http\Controllers\Inventory\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\Api\V1\StoreCategoryRequest;
use App\Http\Requests\Inventory\Api\V1\UpdateCategoryRequest;
use App\Http\Resources\Inventory\Api\V1\CategoryCollection;
use App\Http\Resources\Inventory\Api\V1\CategoryResource;
use App\Models\Inventory\Category;
use App\Services\Inventory\CategoryService;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function index(): CategoryCollection
    {
        return new CategoryCollection($this->categoryService->paginate());
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        return new CategoryResource($this->categoryService->create($request->validated()));
    }

    public function show(Category $category): CategoryResource
    {
        return new CategoryResource($category);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        return new CategoryResource($this->categoryService->update($category, $request->validated()));
    }

    public function destroy(Category $category): Response
    {
        $this->categoryService->delete($category);

        return response()->noContent();
    }
}
