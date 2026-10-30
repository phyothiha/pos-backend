<?php

use App\Http\Controllers\Auth\Api\V1\AuthController;
use App\Http\Controllers\Inventory\Api\V1\BrandController;
use App\Http\Controllers\Inventory\Api\V1\CategoryController;
use App\Http\Controllers\Inventory\Api\V1\InventoryMovementController;
use App\Http\Controllers\Inventory\Api\V1\ProductController;
use App\Http\Controllers\Invoice\Api\V1\InvoiceController;
use App\Http\Controllers\Invoice\Api\V1\InvoiceDeliveryController;
use App\Http\Controllers\Invoice\Api\V1\InvoicePaymentController;
use App\Http\Controllers\Invoice\Api\V1\InvoiceStatusController;
use App\Http\Controllers\Invoice\Api\V1\PaymentPlatformController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('v1.')
    ->group(function () {
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
            Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:30,1')->name('refresh');
            Route::get('me', [AuthController::class, 'me'])->middleware('auth:api')->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:api')->name('logout');
        });

        Route::prefix('inventory')
            ->middleware('auth:api')
            ->name('inventory.')
            ->group(function () {
                Route::get('categories/all', [CategoryController::class, 'all'])->name('categories.all');
                Route::get('brands/all', [BrandController::class, 'all'])->name('brands.all');
                Route::get('payment-platforms/all', [PaymentPlatformController::class, 'all'])->name('payment-platforms.all');

                Route::get('categories/search', [CategoryController::class, 'search'])->name('categories.search');
                Route::get('brands/search', [BrandController::class, 'search'])->name('brands.search');
                Route::get('products/search', [ProductController::class, 'search'])->name('products.search');

                Route::apiResource('categories', CategoryController::class);
                Route::apiResource('brands', BrandController::class);
                Route::apiResource('products', ProductController::class);
                Route::apiResource('movements', InventoryMovementController::class)->only(['index']);
                Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
                Route::apiResource('payment-platforms', PaymentPlatformController::class)->only(['index', 'store']);
                Route::patch('invoices/{invoice}/payment', [InvoicePaymentController::class, 'update'])->name('invoices.payment.update');
                Route::patch('invoices/{invoice}/status', [InvoiceStatusController::class, 'update'])->name('invoices.status.update');
                Route::patch('invoices/{invoice}/delivery', [InvoiceDeliveryController::class, 'update'])->name('invoices.delivery.update');
            });
    });
