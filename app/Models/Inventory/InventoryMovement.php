<?php

namespace App\Models\Inventory;

use App\Models\Invoice\InvoiceItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'invoice_item_id', 'user_id', 'type', 'quantity', 'balance_after', 'reason'])]
class InventoryMovement extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'balance_after' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
