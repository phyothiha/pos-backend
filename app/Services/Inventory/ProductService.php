<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function paginate(): LengthAwarePaginator
    {
        return Product::query()->with(['category', 'brand'])->latest()->paginate(15);
    }

    public function search(string $search): Collection
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->where('is_active', true)
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(10)
            ->get();
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
        DB::transaction(function () use ($product): void {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($lockedProduct->invoiceItems()->exists()) {
                throw ValidationException::withMessages(['product' => 'Products used in invoices cannot be deleted. Deactivate the product instead.']);
            }

            $lockedProduct->delete();
        }, 3);
    }
}
