<?php

use App\Http\Controllers\Inventory\Api\V1\BrandController;
use App\Http\Controllers\Inventory\Api\V1\CategoryController;
use App\Http\Controllers\Inventory\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('v1.')
    ->group(function () {
        Route::prefix('inventory')
            ->name('inventory.')
            ->group(function () {
                Route::get('categories/all', [CategoryController::class, 'all'])->name('categories.all');
                Route::get('brands/all', [BrandController::class, 'all'])->name('brands.all');

                Route::get('categories/search', [CategoryController::class, 'search'])->name('categories.search');
                Route::get('brands/search', [BrandController::class, 'search'])->name('brands.search');

                Route::apiResource('categories', CategoryController::class);
                Route::apiResource('brands', BrandController::class);
                Route::apiResource('products', ProductController::class);
            });
    });
