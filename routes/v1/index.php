<?php

use App\Http\Controllers\EventController;
use App\Http\Controllers\PromoCodeController;
use App\Http\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

// POS API Routes - All routes under /api/v1/pos/*
Route::prefix('pos')->middleware(ValidateSignature::class)->group(function () {
    // Promo Code Routes
    Route::prefix('promo-codes')->group(function () {
        Route::post('/generate', [PromoCodeController::class, 'generate']);
    });

    // Event Routes
    Route::prefix('events')->group(function () {
        // Product Catalog Events
        Route::post('/product-catalog/created', [EventController::class, 'productCatalogCreated']);
        Route::post('/product-catalog/updated', [EventController::class, 'productCatalogUpdated']);

        // Inventory Events
        Route::prefix('inventory/items')->group(function () {
            Route::post('/added', [EventController::class, 'inventoryItemsAdded']);
            Route::post('/removed', [EventController::class, 'inventoryItemsRemoved']);
        });

        // Promo Code Events
        Route::post('/promo-codes/cancelled', [EventController::class, 'promoCodeCancelled']);
    });
});
