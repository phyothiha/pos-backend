<?php

namespace App\Models\Invoice;

use Database\Factories\Invoice\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['invoice_number', 'invoice_date', 'customer_name', 'customer_phone', 'delivery_phone', 'delivery_address', 'status', 'payment_type', 'payment_status', 'payment_platform_id', 'delivery_status', 'subtotal', 'discount', 'delivery_fee', 'grand_total'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'delivery_fee' => 'integer',
            'grand_total' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('id');
    }

    public function paymentPlatform(): BelongsTo
    {
        return $this->belongsTo(PaymentPlatform::class);
    }
}
