<?php

namespace Database\Factories\Invoice;

use App\Models\Invoice\PaymentPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentPlatform> */
class PaymentPlatformFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['name' => fake()->unique()->bothify('Payment-########')];
    }
}
