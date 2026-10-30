<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\Inventory\BrandSeeder;
use Database\Seeders\Inventory\CategorySeeder;
use Database\Seeders\Inventory\ProductSeeder;
use Database\Seeders\Invoice\PaymentPlatformSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo Admin',
            'username' => 'admin',
        ]);

        $this->call([
            CategorySeeder::class,
            BrandSeeder::class,
            ProductSeeder::class,
            PaymentPlatformSeeder::class,
        ]);
    }
}
