<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryService
{
    public function paginate(): LengthAwarePaginator
    {
        return Category::query()->orderBy('id')->paginate(15);
    }

    public function create(array $data): Category
    {
        return Category::query()->create($data)->refresh();
    }

    public function update(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function delete(Category $category): void
    {
        $category->delete();
    }
}
