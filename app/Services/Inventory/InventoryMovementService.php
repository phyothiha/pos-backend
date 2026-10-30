<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use Illuminate\Validation\ValidationException;

class InventoryMovementService
{
    /** Call inside a transaction with the product row locked. */
    public function record(Product $product, int $quantity, string $type, string $reason, ?int $userId, ?int $invoiceItemId = null): InventoryMovement
    {
        $balance = $product->stock_quantity + $quantity;

        if ($balance < 0 || $balance > 2147483647) {
            throw ValidationException::withMessages(['stock_quantity' => 'Stock must be between 0 and 2147483647.']);
        }

        $product->update(['stock_quantity' => $balance]);

        return $product->inventoryMovements()->create([
            'invoice_item_id' => $invoiceItemId,
            'user_id' => $userId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'reason' => $reason,
        ]);
    }
}
