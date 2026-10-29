<?php

use App\Http\Controllers\Inventory\Api\V1\BrandController;
use App\Http\Controllers\Inventory\Api\V1\CategoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->name('v1.')
    ->group(function () {
        Route::prefix('inventory')
            ->name('inventory.')
            ->group(function () {
                Route::apiResource('categories', CategoryController::class);
                Route::apiResource('brands', BrandController::class);
            });
    });
