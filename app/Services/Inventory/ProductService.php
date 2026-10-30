<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(private readonly InventoryMovementService $inventoryMovementService) {}

    public function paginate(): LengthAwarePaginator
    {
        return Product::query()->with(['category', 'brand'])->latest()->paginate(15);
    }

    public function search(string $search): Collection
    {
        return Product::query()
            ->with(['category', 'brand'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->orderBy('id')
            ->limit(10)
            ->get();
    }

    public function create(array $data, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($data, $userId): Product {
            $quantity = (int) ($data['stock_quantity'] ?? 0);
            $product = Product::query()->create([...$data, 'stock_quantity' => 0])->refresh();

            if ($quantity > 0) {
                $this->inventoryMovementService->record($product, $quantity, 'opening', 'Initial product stock', $userId);
            }

            return $product->load(['category', 'brand']);
        }, 3);
    }

    public function update(Product $product, array $data, ?int $userId = null): Product
    {
        return DB::transaction(function () use ($product, $data, $userId): Product {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if (array_key_exists('stock_quantity', $data)) {
                $difference = (int) $data['stock_quantity'] - $lockedProduct->stock_quantity;

                if ($difference !== 0) {
                    $this->inventoryMovementService->record(
                        $lockedProduct, $difference, $difference > 0 ? 'receipt' : 'adjustment',
                        'Product stock updated', $userId,
                    );
                }
            }

            $lockedProduct->update(Arr::except($data, ['stock_quantity']));

            return $lockedProduct->refresh()->load(['category', 'brand']);
        }, 3);
    }

    public function delete(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($lockedProduct->invoiceItems()->exists()) {
                throw ValidationException::withMessages(['product' => 'Products used in invoices cannot be deleted. Deactivate the product instead.']);
            }

            if ($lockedProduct->inventoryMovements()->exists()) {
                throw ValidationException::withMessages(['product' => 'Products with inventory history cannot be deleted. Deactivate the product instead.']);
            }

            $lockedProduct->delete();
        }, 3);
    }
}
