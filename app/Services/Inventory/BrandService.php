<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BrandService
{
    public function all(): Collection
    {
        return Brand::query()->latest()->get();
    }

    public function paginate(): LengthAwarePaginator
    {
        return Brand::query()->latest()->paginate(15);
    }

    public function search(string $name): Collection
    {
        return Brand::query()
            ->where('name', 'like', $name.'%')
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    public function create(array $data): Brand
    {
        return Brand::query()->create($data)->refresh();
    }

    public function update(Brand $brand, array $data): Brand
    {
        $brand->update($data);

        return $brand->refresh();
    }

    public function delete(Brand $brand): void
    {
        $brand->delete();
    }
}
