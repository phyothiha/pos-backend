<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Brand;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BrandService
{
    public function paginate(): LengthAwarePaginator
    {
        return Brand::query()->orderBy('id')->paginate(15);
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
