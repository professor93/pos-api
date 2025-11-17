<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\InventoryHistory;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    /**
     * Handle product catalog created event
     * POST /api/v1/events/product-catalog/created
     */
    public function productCatalogCreated(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string',
            'products.*.barcode' => 'required|string|unique:products,barcode',
            'products.*.description' => 'nullable|string',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.unit' => 'required|string',
            'products.*.category' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::make(
                false,
                400,
                'Validation failed',
                null,
                ['errors' => $validator->errors()]
            );
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $createdProducts = [];

            foreach ($data['products'] as $productData) {
                $product = Product::create([
                    'name' => $productData['name'],
                    'barcode' => $productData['barcode'],
                    'description' => $productData['description'] ?? null,
                    'price' => $productData['price'],
                    'unit' => $productData['unit'],
                    'category' => $productData['category'] ?? null,
                    'is_active' => true,
                ]);

                $createdProducts[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                ];
            }

            DB::commit();

            return ApiResponse::make(
                true,
                201,
                'Product catalog created successfully',
                [
                    'products' => $createdProducts,
                    'count' => count($createdProducts),
                ],
                [
                    'timestamp' => now()->toISOString(),
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::make(
                false,
                500,
                'Failed to create product catalog',
                null,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Handle inventory items added event
     * POST /api/v1/events/inventory/items/added
     */
    public function inventoryItemsAdded(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.branch_id' => 'required|integer|exists:branches,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.previous_quantity' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::make(
                false,
                400,
                'Validation failed',
                null,
                ['errors' => $validator->errors()]
            );
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $inventoryRecords = [];

            foreach ($data['items'] as $item) {
                $newQuantity = $item['previous_quantity'] + $item['quantity'];

                $record = InventoryHistory::create([
                    'product_id' => $item['product_id'],
                    'branch_id' => $item['branch_id'],
                    'type' => 'added',
                    'quantity' => $item['quantity'],
                    'previous_quantity' => $item['previous_quantity'],
                    'new_quantity' => $newQuantity,
                    'reason' => $item['reason'] ?? 'Stock replenishment',
                    'notes' => $item['notes'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                ]);

                $inventoryRecords[] = [
                    'id' => $record->id,
                    'product_id' => $record->product_id,
                    'branch_id' => $record->branch_id,
                    'quantity_added' => $record->quantity,
                    'new_quantity' => $record->new_quantity,
                ];
            }

            DB::commit();

            return ApiResponse::make(
                true,
                201,
                'Inventory items added successfully',
                [
                    'inventory_records' => $inventoryRecords,
                    'count' => count($inventoryRecords),
                ],
                [
                    'timestamp' => now()->toISOString(),
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::make(
                false,
                500,
                'Failed to add inventory items',
                null,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Handle inventory items removed event
     * POST /api/v1/events/inventory/items/removed
     */
    public function inventoryItemsRemoved(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.branch_id' => 'required|integer|exists:branches,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.previous_quantity' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'user_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::make(
                false,
                400,
                'Validation failed',
                null,
                ['errors' => $validator->errors()]
            );
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $inventoryRecords = [];

            foreach ($data['items'] as $item) {
                $newQuantity = max(0, $item['previous_quantity'] - $item['quantity']);

                $record = InventoryHistory::create([
                    'product_id' => $item['product_id'],
                    'branch_id' => $item['branch_id'],
                    'type' => 'removed',
                    'quantity' => $item['quantity'],
                    'previous_quantity' => $item['previous_quantity'],
                    'new_quantity' => $newQuantity,
                    'reason' => $item['reason'] ?? 'Stock depletion',
                    'notes' => $item['notes'] ?? null,
                    'user_id' => $data['user_id'] ?? null,
                ]);

                $inventoryRecords[] = [
                    'id' => $record->id,
                    'product_id' => $record->product_id,
                    'branch_id' => $record->branch_id,
                    'quantity_removed' => $record->quantity,
                    'new_quantity' => $record->new_quantity,
                ];
            }

            DB::commit();

            return ApiResponse::make(
                true,
                201,
                'Inventory items removed successfully',
                [
                    'inventory_records' => $inventoryRecords,
                    'count' => count($inventoryRecords),
                ],
                [
                    'timestamp' => now()->toISOString(),
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::make(
                false,
                500,
                'Failed to remove inventory items',
                null,
                ['error' => $e->getMessage()]
            );
        }
    }
}
