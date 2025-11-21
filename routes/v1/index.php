<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\PromoCodeController;
use Illuminate\Support\Facades\Route;

// Promo Code Routes
Route::prefix('promo-codes')->group(function () {
    Route::post('/generate', [PromoCodeController::class, 'generate']);
});

// Event Routes
Route::prefix('events')->group(function () {
    // Product Catalog Events
    Route::post('/product-catalog/created', [EventController::class, 'productCatalogCreated']);

    // Inventory Events
    Route::prefix('inventory/items')->group(function () {
        Route::post('/added', [EventController::class, 'inventoryItemsAdded']);
        Route::post('/removed', [EventController::class, 'inventoryItemsRemoved']);
    });

    // Promo Code Events
    Route::post('/promo-codes/cancelled', [EventController::class, 'promoCodeCancelled']);
});
