<?php

namespace Database\Seeders\Invoice;

use App\Models\Invoice\PaymentPlatform;
use Illuminate\Database\Seeder;

class PaymentPlatformSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['KBZPay', 'WavePay', 'AYA Pay', 'Bank Transfer', 'Other'] as $name) {
            PaymentPlatform::query()->firstOrCreate(['name' => $name]);
        }
    }
}
