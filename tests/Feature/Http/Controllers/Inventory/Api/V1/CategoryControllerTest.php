<?php

use App\Models\Inventory\Category;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('listing all categories', function () {
    test('returns every record newest first without pagination', function () {
        $this->freezeTime();
        $categories = Category::factory()->count(16)
            ->sequence(fn (Sequence $sequence) => ['created_at' => now()->subDays($sequence->index === 0 ? 16 : $sequence->index - 1)])
            ->create();

        $this->getJson(route('v1.inventory.categories.all'))
            ->assertOk()
            ->assertExactJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'created_at', 'updated_at'],
                ],
            ])
            ->assertJsonCount(16, 'data')
            ->assertJsonPath('data.0.id', $categories->get(1)->id)
            ->assertJsonPath('data.15.id', $categories->first()->id);
    });

    test('returns an empty collection when no categories exist', function () {
        $this->getJson(route('v1.inventory.categories.all'))
            ->assertOk()
            ->assertExactJson(['data' => []]);
    });
});

describe('listing categories', function () {
    test('returns categories newest first in pages of fifteen', function () {
        $categories = Category::factory()->count(16)
            ->sequence(fn (Sequence $sequence) => ['created_at' => now()->subDays($sequence->index === 0 ? 16 : $sequence->index - 1)])
            ->create();

        $this->getJson(route('v1.inventory.categories.index'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $categories->get(1)->id);

        $response = $this->getJson(route('v1.inventory.categories.index', ['page' => 2]));

        $response->assertOk()
            ->assertExactJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'description', 'created_at', 'updated_at'],
                ],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $categories->first()->id)
            ->assertJsonPath('meta', [
                'current_page' => 2,
                'from' => 16,
                'last_page' => 2,
                'per_page' => 15,
                'to' => 16,
                'total' => 16,
            ]);
    });

    test('returns an empty collection when no categories exist', function () {
        $this->getJson(route('v1.inventory.categories.index'))
            ->assertOk()
            ->assertExactJson([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'from' => null,
                    'last_page' => 1,
                    'per_page' => 15,
                    'to' => null,
                    'total' => 0,
                ],
            ]);
    });
});

describe('creating categories', function () {
    test('creates a category and excludes client controlled identifiers and timestamps', function () {
        $this->freezeTime();

        $response = $this->postJson(route('v1.inventory.categories.store'), [
            'id' => 999,
            'name' => 'Beverages',
            'description' => 'Stock for the shop',
            'created_at' => '2000-01-01 00:00:00',
            'updated_at' => '2000-01-01 00:00:00',
        ]);

        $response->assertCreated();
        $category = Category::query()->sole();

        $response->assertExactJson([
            'data' => [
                'id' => $category->id,
                'name' => 'Beverages',
                'description' => 'Stock for the shop',
                'created_at' => now()->startOfSecond()->toISOString(),
                'updated_at' => now()->startOfSecond()->toISOString(),
            ],
        ]);
        $this->assertDatabaseHas(Category::class, [
            'id' => $category->id,
            'name' => 'Beverages',
            'description' => 'Stock for the shop',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseMissing(Category::class, ['id' => 999]);
        $this->assertDatabaseCount(Category::class, 1);
    });

    test('allows the description to be omitted or null', function (array $description) {
        $response = $this->postJson(route('v1.inventory.categories.store'), [
            'name' => 'Beverages',
            ...$description,
        ]);

        $response->assertCreated()->assertJsonPath('data.description', null);
        $this->assertDatabaseHas(Category::class, ['name' => 'Beverages', 'description' => null]);
    })->with([
        'omitted' => [[]],
        'null' => [['description' => null]],
    ]);

    test('returns 422 for invalid category input', function (array $data, string $field, string $message) {
        $response = $this->postJson(route('v1.inventory.categories.store'), $data);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseEmpty(Category::class);
    })->with([
        'missing name' => [[], 'name', 'The name field is required.'],
        'null name' => [['name' => null], 'name', 'The name field is required.'],
        'blank name' => [['name' => '   '], 'name', 'The name field is required.'],
        'non-string name' => [['name' => 123], 'name', 'The name field must be a string.'],
        'non-string description' => [['name' => 'Beverages', 'description' => []], 'description', 'The description field must be a string.'],
    ]);

    test('returns 422 when a category name is already taken', function () {
        $category = Category::factory()->create();

        $response = $this->postJson(route('v1.inventory.categories.store'), ['name' => $category->name]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
        $this->assertDatabaseCount(Category::class, 1);
    });

});

describe('showing categories', function () {
    test('returns the category resource', function () {
        $category = Category::factory()->create();

        $this->getJson(route('v1.inventory.categories.show', $category))
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'created_at' => $category->created_at->toISOString(),
                'updated_at' => $category->updated_at->toISOString(),
            ]]);
    });
});

describe('updating categories', function () {
    test('updates a category through PUT or PATCH', function (string $method) {
        $category = Category::factory()->create();
        $this->travel(1)->minutes();

        $response = $this->{$method}(route('v1.inventory.categories.update', $category), [
            'name' => 'New name',
            'description' => 'New description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New name')
            ->assertJsonPath('data.description', 'New description')
            ->assertJsonPath('data.created_at', $category->created_at->toISOString())
            ->assertJsonPath('data.updated_at', now()->startOfSecond()->toISOString());
        $this->assertDatabaseHas(Category::class, [
            'id' => $category->id,
            'name' => 'New name',
            'description' => 'New description',
        ]);
    })->with(['putJson', 'patchJson']);

    test('keeps the existing name and clears the description', function () {
        $category = Category::factory()->create();

        $response = $this->patchJson(route('v1.inventory.categories.update', $category), [
            'name' => $category->name,
            'description' => null,
        ]);

        $response->assertOk()->assertJsonPath('data.description', null);
        $this->assertDatabaseHas(Category::class, ['id' => $category->id, 'name' => $category->name, 'description' => null]);
    });

    test('preserves omitted fields in a partial update', function () {
        $category = Category::factory()->create();

        $response = $this->patchJson(route('v1.inventory.categories.update', $category), ['description' => 'Changed']);

        $response->assertOk()->assertJsonPath('data.name', $category->name);
        $this->assertDatabaseHas(Category::class, ['id' => $category->id, 'name' => $category->name, 'description' => 'Changed']);
    });

    test('returns 422 for invalid updates without changing the category', function (array $data, string $field, string $message) {
        $category = Category::factory()->create();

        $response = $this->patchJson(route('v1.inventory.categories.update', $category), $data);

        $response->assertUnprocessable()->assertJsonValidationErrors([$field => $message]);
        $this->assertDatabaseHas(Category::class, ['id' => $category->id, 'name' => $category->name, 'description' => $category->description]);
    })->with([
        'null name' => [['name' => null], 'name', 'The name field is required.'],
        'non-string name' => [['name' => 123], 'name', 'The name field must be a string.'],
        'non-string description' => [['description' => []], 'description', 'The description field must be a string.'],
    ]);

    test('returns 422 when renaming to another category name', function () {
        $category = Category::factory()->create();
        $other = Category::factory()->create();

        $response = $this->patchJson(route('v1.inventory.categories.update', $category), ['name' => $other->name]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name' => 'The name has already been taken.']);
        $this->assertDatabaseHas(Category::class, ['id' => $category->id, 'name' => $category->name]);
    });
});

describe('deleting categories', function () {
    test('deletes the category and returns 204', function () {
        $category = Category::factory()->create();

        $this->deleteJson(route('v1.inventory.categories.destroy', $category))->assertNoContent();

        $this->assertModelMissing($category);
    });
});

test('returns 404 for a missing category', function (string $method, string $action) {
    $this->{$method}(route('v1.inventory.categories.'.$action, 999))->assertNotFound();
})->with([
    'show' => ['getJson', 'show'],
    'update' => ['patchJson', 'update'],
    'delete' => ['deleteJson', 'destroy'],
]);

describe('searching categories', function () {
    test('returns only names starting with the full query', function (string $name, array $expected) {
        Category::factory()->createMany([
            ['name' => 'abc tools'],
            ['name' => 'apple'],
            ['name' => 'zabc'],
            ['name' => 'banana', 'description' => 'abc'],
        ]);

        $response = $this->getJson(route('v1.inventory.categories.search', ['name' => $name]));

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
        $matches = Category::factory()->count(16)->sequence(fn ($sequence) => [
            'name' => 'abc '.$sequence->index,
        ])->create();
        Category::factory()->create(['name' => 'unrelated']);

        $this->getJson(route('v1.inventory.categories.search', ['name' => 'abc', 'page' => 2]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matches->last()->id)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.total', 16);
    });

    test('returns 422 for invalid search input', function (array $query, string $message) {
        $this->getJson(route('v1.inventory.categories.search', $query))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => $message]);
    })->with([
        'missing name' => [[], 'The name field is required.'],
        'empty name' => [['name' => ''], 'The name field is required.'],
        'blank name' => [['name' => '   '], 'The name field is required.'],
        'array name' => [['name' => ['abc']], 'The name field must be a string.'],
    ]);
});
