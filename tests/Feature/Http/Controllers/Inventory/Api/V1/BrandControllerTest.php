<?php

use App\Models\Inventory\Brand;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('listing all brands', function () {
    test('returns every record newest first without pagination', function () {
        $this->freezeTime();
        $brands = Brand::factory()->count(16)
            ->sequence(fn (Sequence $sequence) => ['created_at' => now()->subDays($sequence->index === 0 ? 16 : $sequence->index - 1)])
            ->create();

        $this->getJson(route('v1.inventory.brands.all'))
            ->assertOk()
            ->assertExactJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonCount(16, 'data')
            ->assertJsonPath('data.0.id', $brands->get(1)->id)
            ->assertJsonPath('data.15.id', $brands->first()->id);
    });

    test('returns an empty collection when no brands exist', function () {
        $this->getJson(route('v1.inventory.brands.all'))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    });
});

describe('listing brands', function () {
    test('returns brands newest first in pages of fifteen', function () {
        $brands = Brand::factory()->count(16)
            ->sequence(fn (Sequence $sequence) => ['created_at' => now()->subDays($sequence->index === 0 ? 16 : $sequence->index - 1)])
            ->create();

        $this->getJson(route('v1.inventory.brands.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $brands->get(1)->id);

        $response = $this->getJson(route('v1.inventory.brands.index', ['page' => 2]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $brands->first()->id)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    });

    test('returns an empty collection when no brands exist', function () {
        $this->getJson(route('v1.inventory.brands.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    });
});

describe('creating brands', function () {
    test('creates a brand and excludes client controlled identifiers and timestamps', function () {
        $this->freezeTime();

        $response = $this->postJson(route('v1.inventory.brands.store'), [
            'id' => 999,
            'name' => 'Acme',
            'description' => 'Stock for the shop',
            'created_at' => '2000-01-01 00:00:00',
            'updated_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertCreated();
        $brand = Brand::query()->sole();

        $response->assertExactJson([
            'data' => [
                'id' => $brand->id,
                'name' => 'Acme',
                'description' => 'Stock for the shop',
                'created_at' => now()->startOfSecond()->toISOString(),
                'updated_at' => now()->startOfSecond()->toISOString(),
            ],
        ]);
        $this->assertDatabaseHas(Brand::class, [
            'id' => $brand->id,
            'name' => 'Acme',
            'description' => 'Stock for the shop',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseMissing(Brand::class, ['id' => 999]);
        $this->assertDatabaseCount(Brand::class, 1);
    });

    test('allows the description to be omitted or null', function (array $description) {
        $response = $this->postJson(route('v1.inventory.brands.store'), [
            'name' => 'Acme',
            ...$description,
        ]);

        $response->assertCreated()->assertJsonPath('data.description', null);
        $this->assertDatabaseHas(Brand::class, ['name' => 'Acme', 'description' => null]);
    })->with([
        'omitted' => [[]],
        'null' => [['description' => null]],
    ]);

    test('returns 422 for invalid brand input', function (array $data, string $field, string $message) {
        $response = $this->postJson(route('v1.inventory.brands.store'), $data);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseEmpty(Brand::class);
    })->with([
        'missing name' => [[], 'name', 'The name field is required.'],
        'null name' => [['name' => null], 'name', 'The name field is required.'],
        'blank name' => [['name' => '   '], 'name', 'The name field is required.'],
        'non-string name' => [['name' => 123], 'name', 'The name field must be a string.'],
        'non-string description' => [['name' => 'Acme', 'description' => []], 'description', 'The description field must be a string.'],
    ]);

    test('returns 422 when a brand name is already taken', function () {
        $brand = Brand::factory()->create();

        $response = $this->postJson(route('v1.inventory.brands.store'), ['name' => $brand->name]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
        $this->assertDatabaseCount(Brand::class, 1);
    });

});

describe('showing brands', function () {
    test('returns the brand resource', function () {
        $brand = Brand::factory()->create();

        $this->getJson(route('v1.inventory.brands.show', $brand))
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $brand->id,
                'name' => $brand->name,
                'description' => $brand->description,
                'created_at' => $brand->created_at->toISOString(),
                'updated_at' => $brand->updated_at->toISOString(),
            ]]);
    });
});

describe('updating brands', function () {
    test('updates a brand through PUT or PATCH', function (string $method) {
        $brand = Brand::factory()->create();
        $this->travel(1)->minutes();

        $response = $this->{$method}(route('v1.inventory.brands.update', $brand), [
            'name' => 'New name',
            'description' => 'New description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.description', 'New description')
            ->assertJsonPath('data.created_at', $brand->created_at->toISOString())
            ->assertJsonPath('data.updated_at', now()->startOfSecond()->toISOString());
        $this->assertDatabaseHas(Brand::class, [
            'id' => $brand->id,
            'name' => 'New name',
            'description' => 'New description',
        ]);
    })->with(['putJson', 'patchJson']);

    test('keeps the existing name and clears the description', function () {
        $brand = Brand::factory()->create();

        $response = $this->patchJson(route('v1.inventory.brands.update', $brand), [
            'name' => $brand->name,
            'description' => null,
        ]);

        $response->assertOk()->assertJsonPath('data.description', null);
        $this->assertDatabaseHas(Brand::class, ['id' => $brand->id, 'name' => $brand->name, 'description' => null]);
    });

    test('preserves omitted fields in a partial update', function () {
        $brand = Brand::factory()->create();

        $response = $this->patchJson(route('v1.inventory.brands.update', $brand), ['description' => 'Changed']);

        $response->assertOk()->assertJsonPath('data.name', $brand->name);
        $this->assertDatabaseHas(Brand::class, ['id' => $brand->id, 'name' => $brand->name, 'description' => 'Changed']);
    });

    test('returns 422 for invalid updates without changing the brand', function (array $data, string $field, string $message) {
        $brand = Brand::factory()->create();

        $response = $this->patchJson(route('v1.inventory.brands.update', $brand), $data);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseHas(Brand::class, ['id' => $brand->id, 'name' => $brand->name, 'description' => $brand->description]);
    })->with([
        'null name' => [['name' => null], 'name', 'The name field is required.'],
        'non-string name' => [['name' => 123], 'name', 'The name field must be a string.'],
        'non-string description' => [['description' => []], 'description', 'The description field must be a string.'],
    ]);

    test('returns 422 when renaming to another brand name', function () {
        $brand = Brand::factory()->create();
        $other = Brand::factory()->create();

        $response = $this->patchJson(route('v1.inventory.brands.update', $brand), ['name' => $other->name]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
        $this->assertDatabaseHas(Brand::class, ['id' => $brand->id, 'name' => $brand->name]);
    });
});

describe('deleting brands', function () {
    test('deletes the brand and returns 204', function () {
        $brand = Brand::factory()->create();

        $this->deleteJson(route('v1.inventory.brands.destroy', $brand))->assertNoContent();

        $this->assertModelMissing($brand);
    });
});

test('returns 404 for a missing brand', function (string $method, string $action) {
    $this->{$method}(route('v1.inventory.brands.'.$action, 999))->assertNotFound();
})->with([
    'show' => ['getJson', 'show'],
    'update' => ['patchJson', 'update'],
    'delete' => ['deleteJson', 'destroy'],
]);

describe('searching brands', function () {
    test('returns only names starting with the full query', function (string $name, array $expected) {
        Brand::factory()->createMany([
            ['name' => 'abc tools'],
            ['name' => 'apple'],
            ['name' => 'zabc'],
            ['name' => 'banana', 'description' => 'abc'],
        ]);

        $response = $this->getJson(route('v1.inventory.brands.search', ['name' => $name]));

        $response->assertOk()
            ->assertJsonPath('data.*.name', $expected)
            ->assertJsonPath('meta.total', count($expected));
    })->with([
        'single character' => ['a', ['abc tools', 'apple']],
        'full prefix' => ['abc', ['abc tools']],
        'no matches' => ['missing', []],
        'SQL characters stay in the bound value' => ["' OR 1=1 --", []],
    ]);

    test('paginates matching names in stable pages of fifteen', function () {
        $matches = Brand::factory()->count(16)->sequence(fn ($sequence) => [
            'name' => 'abc '.$sequence->index,
        ])->create();
        Brand::factory()->create(['name' => 'unrelated']);

        $this->getJson(route('v1.inventory.brands.search', ['name' => 'abc', 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matches->last()->id)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    });

    test('returns 422 for invalid search input', function (array $query, string $message) {
        $this->getJson(route('v1.inventory.brands.search', $query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => $message]);
    })->with([
        'missing name' => [[], 'The name field is required.'],
        'empty name' => [['name' => ''], 'The name field is required.'],
        'blank name' => [['name' => '   '], 'The name field is required.'],
        'array name' => [['name' => ['abc']], 'The name field must be a string.'],
    ]);
});
