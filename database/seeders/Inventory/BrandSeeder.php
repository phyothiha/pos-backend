<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\Brand;
use Illuminate\Database\Seeder;

class BrandSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Brand::factory()->count(10)->create();
    }
}
