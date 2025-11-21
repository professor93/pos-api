<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\EventReceivedResource;
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
     * Processing is deferred to after the HTTP response is sent.
     *
     * @tags Events
     *
     * @bodyParam sequence_id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam products array required Array of products to create (at least 1 required).
     * @bodyParam products.*.id integer required Product ID from external system. Example: 101
     * @bodyParam products.*.name string required Product name. Example: Coca Cola 500ml
     * @bodyParam products.*.barcode string required Product barcode (must be unique). Example: 1234567890123
     * @bodyParam products.*.description string Product description. Example: Refreshing cola drink
     * @bodyParam products.*.price number required Product price. Example: 2.50
     * @bodyParam products.*.unit string required Unit of measurement. Example: pcs
     * @bodyParam products.*.category string Product category. Example: Beverages
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Product catalog event received successfully",
     *   "result": {
     *     "message": "Event will be processed",
     *     "products_count": 2
     *   }
     * }
     *
     * @response 400 scenario="Validation Error" {
     *   "ok": false,
     *   "code": 400,
     *   "message": "Validation failed"
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
                'Validation failed'
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
            200,
            'Product catalog event received successfully',
            new EventReceivedResource([
                'message' => 'Event will be processed',
                'products_count' => count($data['products']),
            ])
        );
    }

    /**
     * Handle inventory items added event
     *
     * This endpoint processes inventory addition events from external systems.
     * Records are created with status='new' for later processing.
     * Processing is deferred to after the HTTP response is sent.
     * No validation is performed on product_id or branch_id existence.
     *
     * @tags Events
     *
     * @bodyParam sequence_id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam user_id integer User ID who performed the action. Example: 5
     * @bodyParam items array required Array of inventory items to add (at least 1 required).
     * @bodyParam items.*.product_id integer required Product ID. Example: 101
     * @bodyParam items.*.branch_id integer required Branch ID. Example: 1
     * @bodyParam items.*.quantity number required Quantity added (min: 0.001). Example: 10.500
     * @bodyParam items.*.previous_quantity number required Previous quantity before addition. Example: 40.000
     * @bodyParam items.*.total_quantity number required New total quantity after addition. Example: 50.500
     * @bodyParam items.*.reason string Reason for addition. Example: Stock replenishment
     * @bodyParam items.*.notes string Additional notes. Example: Delivery from supplier ABC
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Inventory items added event received successfully",
     *   "result": {
     *     "message": "Event will be processed",
     *     "items_count": 1
     *   }
     * }
     *
     * @response 400 scenario="Validation Error" {
     *   "ok": false,
     *   "code": 400,
     *   "message": "Validation failed"
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
            'items.*.total_quantity' => 'required|numeric|min:0',
            'items.*.reason' => 'nullable|string',
            'items.*.notes' => 'nullable|string',
            'user_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::make(
                false,
                400,
                'Validation failed'
            );
        }

        $data = $validator->validated();

        // Defer processing to after response is sent
        defer(function () use ($data) {
            try {
                DB::beginTransaction();

                foreach ($data['items'] as $item) {
                    InventoryHistory::create([
                        'product_id' => $item['product_id'],
                        'branch_id' => $item['branch_id'],
                        'type' => 'added',
                        'quantity' => $item['quantity'],
                        'previous_quantity' => $item['previous_quantity'],
                        'new_quantity' => $item['total_quantity'],
                        'total_quantity' => $item['total_quantity'],
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
            200,
            'Inventory items added event received successfully',
            new EventReceivedResource([
                'message' => 'Event will be processed',
                'items_count' => count($data['items']),
            ])
        );
    }

    /**
     * Handle inventory items removed event
     *
     * This endpoint processes inventory removal events from external systems.
     * Records are created with status='new' for later processing.
     * Processing is deferred to after the HTTP response is sent.
     * No validation is performed on product_id or branch_id existence.
     *
     * @tags Events
     *
     * @bodyParam sequence_id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam user_id integer User ID who performed the action. Example: 5
     * @bodyParam items array required Array of inventory items to remove (at least 1 required).
     * @bodyParam items.*.product_id integer required Product ID. Example: 101
     * @bodyParam items.*.branch_id integer required Branch ID. Example: 1
     * @bodyParam items.*.quantity number required Quantity removed (min: 0.001). Example: 5.000
     * @bodyParam items.*.previous_quantity number required Previous quantity before removal. Example: 50.500
     * @bodyParam items.*.reason string Reason for removal. Example: Stock depletion
     * @bodyParam items.*.notes string Additional notes. Example: Damaged items removed
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Inventory items removed event received successfully",
     *   "result": {
     *     "message": "Event will be processed",
     *     "items_count": 1
     *   }
     * }
     *
     * @response 400 scenario="Validation Error" {
     *   "ok": false,
     *   "code": 400,
     *   "message": "Validation failed"
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
                'Validation failed'
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
            200,
            'Inventory items removed event received successfully',
            new EventReceivedResource([
                'message' => 'Event will be processed',
                'items_count' => count($data['items']),
            ])
        );
    }

    /**
     * Cancel items from a receipt and update promo code status
     *
     * This endpoint marks specific items in a sale as cancelled and updates the promo code status.
     * Processing is deferred to after the HTTP response is sent.
     * If all items are cancelled, the sale status becomes 'cancelled', otherwise 'partially_cancelled'.
     *
     * @tags Events
     *
     * @bodyParam sequence_id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam receipt_id integer required Sale/Receipt ID to cancel items from. Example: 5
     * @bodyParam branch_id string required Branch code/identifier (must match receipt's branch). Example: BR001
     * @bodyParam cashier_id string required Cashier identifier performing the cancellation. Example: CASH123
     * @bodyParam cancelled_items array required Array of sale item IDs to cancel (at least 1 required). Example: [1, 2, 3]
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Promo code cancellation event received successfully",
     *   "result": {
     *     "message": "Event will be processed",
     *     "cancelled_items_count": 2
     *   }
     * }
     *
     * @response 400 scenario="Validation Error" {
     *   "ok": false,
     *   "code": 400,
     *   "message": "Validation failed"
     * }
     *
     * @response 403 scenario="Branch Mismatch" {
     *   "ok": false,
     *   "code": 403,
     *   "message": "Branch ID does not match the receipt"
     * }
     *
     * @response 404 scenario="Sale Not Found" {
     *   "ok": false,
     *   "code": 404,
     *   "message": "Sale not found"
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
                'Validation failed'
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
            200,
            'Promo code cancellation event received successfully',
            new EventReceivedResource([
                'message' => 'Event will be processed',
                'cancelled_items_count' => count($data['cancelled_items']),
            ])
        );
    }
}
