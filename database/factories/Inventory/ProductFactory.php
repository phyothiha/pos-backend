<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Brand;
use App\Models\Inventory\Category;
use App\Models\Inventory\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'brand_id' => Brand::factory(),
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-########'),
            'selling_price' => fake()->numberBetween(0, 100000),
            'stock_quantity' => fake()->numberBetween(0, 100),
            'type' => fake()->randomElement(['in_stock', 'pre_order']),
            'is_active' => fake()->boolean(80),
        ];
    }
}
