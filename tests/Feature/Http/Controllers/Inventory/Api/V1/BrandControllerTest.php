<?php

use App\Models\Inventory\Brand;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('lists brands with pagination', function () {
    $this->actingAs(User::factory()->create(), 'api');

    Brand::factory()->count(16)->create();

    $this->getJson(route('v1.inventory.brands.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 16)
        ->assertJsonPath('meta.per_page', 15);
});

test('returns all brands for dropdowns', function () {
    $this->actingAs(User::factory()->create(), 'api');

    Brand::factory()->count(16)->create();

    $this->getJson(route('v1.inventory.brands.all'))
        ->assertOk()
        ->assertJsonCount(16, 'data')
        ->assertJsonMissingPath('meta');
});

test('creates a brand', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->postJson(route('v1.inventory.brands.store'), [
        'name' => 'Sample Brand',
        'description' => 'Sample description',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Sample Brand');

    $this->assertDatabaseHas(Brand::class, ['name' => 'Sample Brand']);
});

test('shows a brand', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $brand = Brand::factory()->create();

    $this->getJson(route('v1.inventory.brands.show', $brand))
        ->assertOk()
        ->assertJsonPath('data.id', $brand->id)
        ->assertJsonPath('data.name', $brand->name);
});

test('updates a brand', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $brand = Brand::factory()->create();

    $this->patchJson(route('v1.inventory.brands.update', $brand), ['name' => 'Updated Brand'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Brand');

    $this->assertDatabaseHas(Brand::class, ['id' => $brand->id, 'name' => 'Updated Brand']);
});

test('deletes a brand', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $brand = Brand::factory()->create();

    $this->deleteJson(route('v1.inventory.brands.destroy', $brand))->assertNoContent();

    $this->assertModelMissing($brand);
});

test('requires a brand name', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->postJson(route('v1.inventory.brands.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseEmpty(Brand::class);
});

test('rejects duplicate brand names', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $brand = Brand::factory()->create();

    $this->postJson(route('v1.inventory.brands.store'), ['name' => $brand->name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseCount(Brand::class, 1);
});

test('searches brands by name prefix', function () {
    $this->actingAs(User::factory()->create(), 'api');

    Brand::factory()->create(['name' => 'Apple']);
    Brand::factory()->create(['name' => 'Banana']);

    $this->getJson(route('v1.inventory.brands.search', ['name' => 'App']))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['Apple']);
});

test('returns 404 for an unknown brand', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->getJson(route('v1.inventory.brands.show', 999999))->assertNotFound();
});

test('returns at most ten brands search results without pagination', function () {
    $this->actingAs(User::factory()->create(), 'api');

    Brand::factory()->count(12)->sequence(fn ($sequence) => ['name' => 'Sample '.$sequence->index])->create();

    $this->getJson(route('v1.inventory.brands.search', ['name' => 'Sample']))
        ->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonMissingPath('meta')
        ->assertJsonMissingPath('links');
});

test('requires a name for brands search', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->getJson(route('v1.inventory.brands.search'))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});
