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
     *
     * This endpoint processes product catalog events from external systems.
     * Products are created with status='new' for later processing.
     * Duplicate barcodes are gracefully handled and reported in the response.
     *
     * @tags Events
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 201 {
     *   "ok": true,
     *   "code": 201,
     *   "message": "Product catalog event processed",
     *   "result": {
     *     "products": [
     *       {
     *         "id": 1,
     *         "name": "Product A",
     *         "barcode": "123456",
     *         "status": "new"
     *       }
     *     ],
     *     "created_count": 1,
     *     "skipped_count": 0,
     *     "skipped": []
     *   },
     *   "meta": {
     *     "timestamp": "2025-11-17T10:00:00.000000Z"
     *   }
     * }
     */
    public function productCatalogCreated(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',
            'products.*.name' => 'required|string',
            'products.*.barcode' => 'required|string',
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
            $skippedProducts = [];

            foreach ($data['products'] as $productData) {
                // Check if product with this barcode already exists
                $existingProduct = Product::where('barcode', $productData['barcode'])->first();

                if ($existingProduct) {
                    $skippedProducts[] = [
                        'barcode' => $productData['barcode'],
                        'reason' => 'Product with this barcode already exists',
                    ];
                    continue;
                }

                $product = Product::create([
                    'name' => $productData['name'],
                    'barcode' => $productData['barcode'],
                    'description' => $productData['description'] ?? null,
                    'price' => $productData['price'],
                    'unit' => $productData['unit'],
                    'category' => $productData['category'] ?? null,
                    'is_active' => true,
                    'status' => 'new',
                ]);

                $createdProducts[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'status' => $product->status,
                ];
            }

            DB::commit();

            return ApiResponse::make(
                true,
                201,
                'Product catalog event processed',
                [
                    'products' => $createdProducts,
                    'created_count' => count($createdProducts),
                    'skipped_count' => count($skippedProducts),
                    'skipped' => $skippedProducts,
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
     *
     * This endpoint processes inventory addition events from external systems.
     * Records are created with status='new' for later processing.
     * No validation is performed on product_id or branch_id existence.
     *
     * @tags Events
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 201 {
     *   "ok": true,
     *   "code": 201,
     *   "message": "Inventory items added successfully",
     *   "result": {
     *     "inventory_records": [
     *       {
     *         "id": 1,
     *         "product_id": 1,
     *         "branch_id": 1,
     *         "quantity_added": 10.5,
     *         "new_quantity": 50.5
     *       }
     *     ],
     *     "count": 1
     *   },
     *   "meta": {
     *     "timestamp": "2025-11-17T10:00:00.000000Z"
     *   }
     * }
     */
    public function inventoryItemsAdded(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.branch_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.previous_quantity' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'user_id' => 'nullable|integer',
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
                    'status' => 'new',
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
     *
     * This endpoint processes inventory removal events from external systems.
     * Records are created with status='new' for later processing.
     * No validation is performed on product_id or branch_id existence.
     *
     * @tags Events
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 201 {
     *   "ok": true,
     *   "code": 201,
     *   "message": "Inventory items removed successfully",
     *   "result": {
     *     "inventory_records": [
     *       {
     *         "id": 2,
     *         "product_id": 1,
     *         "branch_id": 1,
     *         "quantity_removed": 5.0,
     *         "new_quantity": 45.5
     *       }
     *     ],
     *     "count": 1
     *   },
     *   "meta": {
     *     "timestamp": "2025-11-17T10:00:00.000000Z"
     *   }
     * }
     */
    public function inventoryItemsRemoved(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.branch_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.previous_quantity' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'user_id' => 'nullable|integer',
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
                    'status' => 'new',
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
