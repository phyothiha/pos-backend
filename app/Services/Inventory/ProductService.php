<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductService
{
    public function paginate(): LengthAwarePaginator
    {
        return Product::query()->with(['category', 'brand'])->orderBy('id')->paginate(15);
    }

    public function create(array $data): Product
    {
        return Product::query()->create($data)->refresh()->load(['category', 'brand']);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->refresh()->load(['category', 'brand']);
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
