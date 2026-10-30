<?php

namespace App\Models\Invoice;

use Database\Factories\Invoice\PaymentPlatformFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class PaymentPlatform extends Model
{
    /** @use HasFactory<PaymentPlatformFactory> */
    use HasFactory;

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
