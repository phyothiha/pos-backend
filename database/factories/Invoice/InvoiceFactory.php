<?php

namespace Database\Factories\Invoice;

use App\Models\Invoice\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Invoice> */
class InvoiceFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-'.Str::ulid(),
            'invoice_date' => fake()->date(),
            'customer_name' => fake()->name(),
            'customer_phone' => '09123456789',
            'delivery_phone' => '09987654321',
            'delivery_address' => fake()->address(),
            'payment_type' => 'cod',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'payment_type' => 'paid',
            'payment_status' => 'paid',
            'payment_platform_id' => PaymentPlatformFactory::new(),
        ]);
    }
}
