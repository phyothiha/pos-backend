<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Invoice\Invoice;
use App\Models\Invoice\InvoiceItem;
use App\Models\User;
use App\Services\Invoice\InvoiceService;
use App\Services\Invoice\InvoiceStatusService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

uses(DatabaseMigrations::class);

/** @param array<string, mixed> $connection */
function inventoryStatusTask(int $invoiceId, int $userId, array $connection, string $status): Closure
{
    return static function () use ($invoiceId, $userId, $connection, $status): string {
        config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
        DB::purge('mysql');

        try {
            return app(InvoiceStatusService::class)->update(Invoice::findOrFail($invoiceId), $status, $userId)->status;
        } catch (ValidationException $exception) {
            return array_key_first($exception->errors());
        }
    };
}

test('serializes competing confirmations without overselling on mysql', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires MySQL row locks and a database shared by worker processes.');
    }

    $userId = User::factory()->create()->id;
    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 5]);
    $invoices = Invoice::factory()->count(2)->create();
    foreach ($invoices as $invoice) {
        InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 4]);
    }
    $connection = config('database.connections.mysql');
    $tasks = [];
    foreach ($invoices as $invoice) {
        $tasks[] = inventoryStatusTask($invoice->id, $userId, $connection, 'confirmed');
    }

    $results = Concurrency::driver('process')->run($tasks);

    expect($results)->toContain('confirmed', 'stock_quantity');
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 1]);
    $this->assertDatabaseCount(InventoryMovement::class, 1);
    expect(Invoice::where('status', 'confirmed')->count())->toBe(1);
});

test('deducts and restores stock once for simultaneous retries on mysql', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires MySQL row locks and a database shared by worker processes.');
    }

    $userId = User::factory()->create()->id;
    $invoice = Invoice::factory()->create();
    $invoiceId = $invoice->id;
    $product = Product::factory()->create(['is_active' => true, 'type' => 'in_stock', 'stock_quantity' => 10]);
    InvoiceItem::factory()->for($invoice)->for($product)->create(['quantity' => 3]);
    $connection = config('database.connections.mysql');
    $confirm = inventoryStatusTask($invoiceId, $userId, $connection, 'confirmed');

    expect(Concurrency::driver('process')->run([$confirm, $confirm]))->toBe(['confirmed', 'confirmed']);
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 7]);
    $this->assertDatabaseCount(InventoryMovement::class, 1);

    $cancel = inventoryStatusTask($invoiceId, $userId, $connection, 'cancelled');

    expect(Concurrency::driver('process')->run([$cancel, $cancel]))->toBe(['cancelled', 'cancelled']);
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 10]);
    $this->assertDatabaseCount(InventoryMovement::class, 2);
});

test('creates an empty inventory history without changing existing stock when migrated', function () {
    $product = Product::factory()->create(['stock_quantity' => 8]);
    Schema::drop('inventory_movements');
    $migration = require database_path('migrations/2026_10_30_040102_create_inventory_movements_table.php');

    $migration->up();

    $this->assertDatabaseEmpty(InventoryMovement::class);
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 8]);
});

/** @param array<string, mixed> $connection */
function inventoryCreateInvoiceTask(int $productId, int $userId, array $connection): Closure
{
    return static function () use ($productId, $userId, $connection): string {
        config(['database.default' => 'mysql', 'database.connections.mysql' => $connection]);
        DB::purge('mysql');

        try {
            return app(InvoiceService::class)->create([
                'invoice_date' => '2026-10-30',
                'customer_name' => 'Concurrent Customer',
                'customer_phone' => '09123456789',
                'delivery_phone' => '09987654321',
                'delivery_address' => 'Yangon',
                'payment_type' => 'cod',
                'items' => [['product_id' => $productId, 'quantity' => 4]],
            ], $userId)->status;
        } catch (ValidationException $exception) {
            return array_key_first($exception->errors());
        }
    };
}

test('prevents overselling when two invoices are created simultaneously on mysql', function (string $type) {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Requires MySQL row locks and a database shared by worker processes.');
    }

    $userId = User::factory()->create()->id;
    $product = Product::factory()->create(['is_active' => true, 'type' => $type, 'stock_quantity' => 5]);
    $task = inventoryCreateInvoiceTask($product->id, $userId, config('database.connections.mysql'));

    $results = Concurrency::driver('process')->run([$task, $task]);

    expect($results)->toContain('confirmed', 'stock_quantity');
    $this->assertDatabaseHas(Product::class, ['id' => $product->id, 'stock_quantity' => 1]);
    $this->assertDatabaseCount(Invoice::class, 1);
    $this->assertDatabaseCount(InvoiceItem::class, 1);
    $this->assertDatabaseCount(InventoryMovement::class, 1);
})->with(['in_stock', 'pre_order']);
