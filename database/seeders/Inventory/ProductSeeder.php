<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\Brand;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::factory()
            ->recycle(Category::all())
            ->recycle(Brand::all())
            ->count(10)
            ->create();
    }
}
