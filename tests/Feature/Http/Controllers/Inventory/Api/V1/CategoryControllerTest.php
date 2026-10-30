<?php

use App\Models\Inventory\Category;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('lists categories with pagination', function () {
    Category::factory()->count(16)->create();

    $this->getJson(route('v1.inventory.categories.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.per_page', 15);
});

test('returns all categories for dropdowns', function () {
    Category::factory()->count(16)->create();

    $this->getJson(route('v1.inventory.categories.all'))
        ->assertOk()
        ->assertJsonCount(16, 'data')
        ->assertJsonMissingPath('meta');
});

test('creates a category', function () {
    $this->postJson(route('v1.inventory.categories.store'), [
        'name' => 'Sample Category',
        'description' => 'Sample description',
    ])->assertCreated()->assertJsonPath('data.name', 'Sample Category');

    $this->assertDatabaseHas(Category::class, ['name' => 'Sample Category']);
});

test('shows a category', function () {
    $category = Category::factory()->create();

    $this->getJson(route('v1.inventory.categories.show', $category))
        ->assertOk()
        ->assertJsonPath('data.id', $category->id)
        ->assertJsonPath('data.name', $category->name);
});

test('updates a category', function () {
    $category = Category::factory()->create();

    $this->patchJson(route('v1.inventory.categories.update', $category), ['name' => 'Updated Category'])
        ->assertOk()->assertJsonPath('data.name', 'Updated Category');

    $this->assertDatabaseHas(Category::class, ['id' => $category->id, 'name' => 'Updated Category']);
});

test('deletes a category', function () {
    $category = Category::factory()->create();

    $this->deleteJson(route('v1.inventory.categories.destroy', $category))->assertNoContent();

    $this->assertModelMissing($category);
});

test('requires a category name', function () {
    $this->postJson(route('v1.inventory.categories.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->assertDatabaseEmpty(Category::class);
});

test('rejects duplicate category names', function () {
    $category = Category::factory()->create();

    $this->postJson(route('v1.inventory.categories.store'), ['name' => $category->name])
        ->assertUnprocessable()->assertJsonValidationErrors('name');

    $this->assertDatabaseCount(Category::class, 1);
});

test('searches categories by name prefix', function () {
    Category::factory()->create(['name' => 'Apple']);
    Category::factory()->create(['name' => 'Banana']);

    $this->getJson(route('v1.inventory.categories.search', ['name' => 'App']))
        ->assertOk()->assertJsonPath('data.*.name', ['Apple']);
});

test('returns 404 for an unknown category', function () {
    $this->getJson(route('v1.inventory.categories.show', 999999))->assertNotFound();
});

test('returns at most ten categories search results without pagination', function () {
    Category::factory()->count(12)->sequence(fn ($sequence) => ['name' => 'Sample '.$sequence->index])->create();

    $this->getJson(route('v1.inventory.categories.search', ['name' => 'Sample']))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('links');
});

test('requires a name for categories search', function () {
    $this->getJson(route('v1.inventory.categories.search'))
        ->assertUnprocessable()->assertJsonValidationErrors('name');
});
