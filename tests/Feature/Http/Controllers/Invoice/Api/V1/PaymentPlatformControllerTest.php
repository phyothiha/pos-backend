<?php

use App\Models\Invoice\PaymentPlatform;
use App\Models\User;
use Database\Seeders\Invoice\PaymentPlatformSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('lists payment platforms with pagination', function () {
    $this->actingAs(User::factory()->create(), 'api');

    PaymentPlatform::factory()->count(16)->create();

    $this->getJson(route('v1.inventory.payment-platforms.index', ['page' => 2]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.total', 16);
});

test('returns the five default platforms without duplicates', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->seed(PaymentPlatformSeeder::class);
    $this->seed(PaymentPlatformSeeder::class);

    $this->getJson(route('v1.inventory.payment-platforms.all'))
        ->assertOk()
        ->assertJsonPath('data.*.name', ['AYA Pay', 'Bank Transfer', 'KBZPay', 'Other', 'WavePay']);
});

test('adds a payment platform', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->postJson(route('v1.inventory.payment-platforms.store'), ['name' => 'New Bank'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'New Bank');

    $this->assertDatabaseHas(PaymentPlatform::class, ['name' => 'New Bank']);
});

test('requires a payment platform name', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $this->postJson(route('v1.inventory.payment-platforms.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseEmpty(PaymentPlatform::class);
});

test('rejects duplicate payment platforms', function () {
    $this->actingAs(User::factory()->create(), 'api');

    $platform = PaymentPlatform::factory()->create();

    $this->postJson(route('v1.inventory.payment-platforms.store'), ['name' => $platform->name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    $this->assertDatabaseCount(PaymentPlatform::class, 1);
});
