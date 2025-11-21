<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\InventoryHistory;
use App\Models\Product;
use App\Models\PromoCodeGenerationHistory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Illuminate\Support\defer;

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
            'sequence_id' => 'required|integer',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|integer',
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

        $data = $validator->validated();

        // Defer processing to after response is sent
        defer(function () use ($data) {
            try {
                DB::beginTransaction();

                foreach ($data['products'] as $productData) {
                    // Check if product with this barcode already exists
                    $existingProduct = Product::where('barcode', $productData['barcode'])->first();

                    if ($existingProduct) {
                        continue;
                    }

                    Product::create([
                        'id' => $productData['id'],
                        'name' => $productData['name'],
                        'barcode' => $productData['barcode'],
                        'description' => $productData['description'] ?? null,
                        'price' => $productData['price'],
                        'unit' => $productData['unit'],
                        'category' => $productData['category'] ?? null,
                        'is_active' => true,
                        'status' => 'new',
                        'sequence_id' => $data['sequence_id'],
                    ]);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                // Log the error for debugging
                logger()->error('Failed to process product catalog event', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            202,
            'Product catalog event received successfully',
            [
                'message' => 'Event will be processed',
                'products_count' => count($data['products']),
            ],
            [
                'timestamp' => now()->toISOString(),
            ]
        );
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
            'sequence_id' => 'required|integer',
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

        $data = $validator->validated();

        // Defer processing to after response is sent
        defer(function () use ($data) {
            try {
                DB::beginTransaction();

                foreach ($data['items'] as $item) {
                    $newQuantity = $item['previous_quantity'] + $item['quantity'];

                    InventoryHistory::create([
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
                        'sequence_id' => $data['sequence_id'],
                    ]);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                // Log the error for debugging
                logger()->error('Failed to process inventory items added event', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            202,
            'Inventory items added event received successfully',
            [
                'message' => 'Event will be processed',
                'items_count' => count($data['items']),
            ],
            [
                'timestamp' => now()->toISOString(),
            ]
        );
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
            'sequence_id' => 'required|integer',
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

        $data = $validator->validated();

        // Defer processing to after response is sent
        defer(function () use ($data) {
            try {
                DB::beginTransaction();

                foreach ($data['items'] as $item) {
                    $newQuantity = max(0, $item['previous_quantity'] - $item['quantity']);

                    InventoryHistory::create([
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
                        'sequence_id' => $data['sequence_id'],
                    ]);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                // Log the error for debugging
                logger()->error('Failed to process inventory items removed event', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            202,
            'Inventory items removed event received successfully',
            [
                'message' => 'Event will be processed',
                'items_count' => count($data['items']),
            ],
            [
                'timestamp' => now()->toISOString(),
            ]
        );
    }

    /**
     * Cancel items from a receipt and update promo code status
     *
     * This endpoint marks specific items in a sale as cancelled and updates the promo code status.
     * If all items are cancelled, the sale status becomes 'cancelled', otherwise 'partially_cancelled'.
     *
     * @tags Events
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Items cancelled successfully",
     *   "result": {
     *     "sale_id": 1,
     *     "status": "partially_cancelled",
     *     "cancelled_items_count": 2,
     *     "total_cancelled_amount": 50.00
     *   },
     *   "meta": {
     *     "timestamp": "2025-11-17T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 403 {
     *   "ok": false,
     *   "code": 403,
     *   "message": "Store ID does not match the receipt"
     * }
     */
    public function promoCodeCancelled(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'sequence_id' => 'required|integer',
            'receipt_id' => 'required|integer|exists:sales,id',
            'branch_id' => 'required|string',
            'cashier_id' => 'required|string',
            'cancelled_items' => 'required|array|min:1',
            'cancelled_items.*' => 'required|integer|exists:sale_items,id',
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

        $data = $validator->validated();

        // Perform security checks before deferring
        $sale = Sale::find($data['receipt_id']);
        if (!$sale) {
            return ApiResponse::make(
                false,
                404,
                'Sale not found'
            );
        }

        // Verify branch_id matches (security check)
        if ($sale->store_id !== $data['branch_id']) {
            return ApiResponse::make(
                false,
                403,
                'Branch ID does not match the receipt',
            );
        }

        // Defer processing to after response is sent
        defer(function () use ($data) {
            try {
                DB::beginTransaction();

                $sale = Sale::findOrFail($data['receipt_id']);

                // Mark items as cancelled
                $cancelledItems = SaleItem::whereIn('id', $data['cancelled_items'])
                    ->where('sale_id', $sale->id)
                    ->get();

                if (!$cancelledItems->isEmpty()) {
                    foreach ($cancelledItems as $item) {
                        if (!$item->is_cancelled) {
                            $item->is_cancelled = true;
                            $item->save();
                        }
                    }

                    // Update sale status
                    $allItemsCancelled = $sale->items()->where('is_cancelled', false)->count() === 0;
                    $sale->status = $allItemsCancelled ? 'cancelled' : 'partially_cancelled';
                    $sale->save();

                    // Update promo code generation history
                    $promoHistory = PromoCodeGenerationHistory::where('sale_id', $sale->id)->first();
                    if ($promoHistory) {
                        $promoHistory->status = 'cancelled';
                        $promoHistory->notes = 'Cancelled due to item cancellation';
                        $promoHistory->save();
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                // Log the error for debugging
                logger()->error('Failed to process promo code cancellation event', [
                    'error' => $e->getMessage(),
                    'data' => $data,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            202,
            'Promo code cancellation event received successfully',
            [
                'message' => 'Event will be processed',
                'cancelled_items_count' => count($data['cancelled_items']),
            ],
            [
                'timestamp' => now()->toISOString(),
            ]
        );
    }
}
