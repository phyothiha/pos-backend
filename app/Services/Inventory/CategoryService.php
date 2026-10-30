<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function all(): Collection
    {
        return Category::query()->latest()->get();
    }

    public function paginate(): LengthAwarePaginator
    {
        return Category::query()->latest()->paginate(15);
    }

    public function search(string $name): Collection
    {
        return Category::query()
            ->where('name', 'like', $name.'%')
            ->orderBy('id')
            ->limit(10)
            ->get();
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
