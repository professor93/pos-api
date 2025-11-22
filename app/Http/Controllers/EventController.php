<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Resources\EventReceivedResource;
use App\Models\Branch;
use App\Models\InventoryHistory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use function Illuminate\Support\defer;

/**
 * POS Events API
 *
 * @group Events
 *
 * ## Security
 *
 * All endpoints under /api/v1/pos/* are protected by the following security measures:
 *
 * ### Request Signature Verification (X-Signature Header)
 * Every request must include an X-Signature header containing an HMAC-SHA256 hash of the request payload.
 * The signature is computed using a shared secret key.
 *
 * **Signature Generation:**
 * ```
 * signature = HMAC-SHA256(request_body, secret_key)
 * ```
 *
 * **Note:** Signature validation is disabled in local environment for development purposes.
 *
 * ### IP Whitelist
 * Only requests from pre-approved IP addresses are accepted. All other requests will be rejected.
 * Contact the system administrator to add your IP address to the whitelist.
 *
 * @header X-Signature string required HMAC-SHA256 signature of request body. Example: a3b5c7d9e1f2a4b6c8d0e2f4a6b8c0d2e4f6a8b0c2d4e6f8a0b2c4d6e8f0a2b4
 */
class EventController extends Controller
{
    /**
     * Handle product catalog created event
     *
     * This endpoint processes product catalog events from external systems.
     * Products are created if their ext_id doesn't already exist.
     * Processing is deferred to after the HTTP response is sent.
     *
     * @tags Events
     *
     * @header X-Sequence-Id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam products array required Array of products to create (at least 1 required).
     * @bodyParam products.*.id string required Product external ID. Example: PROD-101
     * @bodyParam products.*.name string required Product name. Example: Coca Cola 500ml
     * @bodyParam products.*.barcode string required Product barcode (alphanumeric and hyphens only). Example: 1234567890123
     * @bodyParam products.*.description string Product description. Example: Refreshing cola drink
     * @bodyParam products.*.price number required Product price. Example: 2.50
     * @bodyParam products.*.discount_price number Discount price. Example: 2.00
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
     *     "products_count": 2,
     *     "process_id": "550e8400-e29b-41d4-a716-446655440000"
     *   }
     * }
     */
    public function productCatalogCreated(Request $request): JsonResponse
    {
        $data = $request->all();
        $sequenceId = $request->header('X-Sequence-Id');
        $processId = Str::uuid()->toString();

        // Defer processing to after response is sent
        defer(function () use ($data, $sequenceId, $processId) {
            try {
                DB::beginTransaction();

                // Get existing ext_ids to filter out duplicates
                $extIds = array_column($data['products'], 'id');
                $existingExtIds = Product::whereIn('ext_id', $extIds)
                    ->pluck('ext_id')
                    ->toArray();

                // Prepare bulk insert data for new products only
                $productsToCreate = [];
                foreach ($data['products'] as $productData) {
                    if (!in_array($productData['id'], $existingExtIds)) {
                        $productsToCreate[] = [
                            'ext_id' => $productData['id'],
                            'name' => $productData['name'],
                            'barcode' => $productData['barcode'],
                            'description' => $productData['description'] ?? null,
                            'price' => $productData['price'],
                            'discount_price' => $productData['discount_price'] ?? null,
                            'unit' => $productData['unit'],
                            'category' => $productData['category'] ?? null,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Bulk insert new products
                if (!empty($productsToCreate)) {
                    Product::insert($productsToCreate);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                logger()->error('Failed to process product catalog event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'process_id' => $processId,
                    'sequence_id' => $sequenceId,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            200,
            'Product catalog event received successfully',
            new EventReceivedResource([
                'products_count' => isset($data['products']) ? count($data['products']) : 0,
                'process_id' => $processId,
            ])
        );
    }

    /**
     * Handle product catalog updated event
     *
     * This endpoint processes product catalog update events from external systems.
     * Products are created or updated based on their ext_id using upsert.
     * Processing is deferred to after the HTTP response is sent.
     *
     * @tags Events
     *
     * @header X-Sequence-Id integer required Event sequence ID for ordering. Example: 2
     * @bodyParam products array required Array of products to update (at least 1 required).
     * @bodyParam products.*.id string required Product external ID. Example: PROD-101
     * @bodyParam products.*.name string required Product name. Example: Coca Cola 500ml
     * @bodyParam products.*.barcode string required Product barcode (alphanumeric and hyphens only). Example: 1234567890123
     * @bodyParam products.*.description string Product description. Example: Refreshing cola drink
     * @bodyParam products.*.price number required Product price. Example: 2.50
     * @bodyParam products.*.discount_price number Discount price. Example: 2.00
     * @bodyParam products.*.unit string required Unit of measurement. Example: pcs
     * @bodyParam products.*.category string Product category. Example: Beverages
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Product catalog update event received successfully",
     *   "result": {
     *     "products_count": 2,
     *     "process_id": "550e8400-e29b-41d4-a716-446655440000"
     *   }
     * }
     */
    public function productCatalogUpdated(Request $request): JsonResponse
    {
        $data = $request->all();
        $sequenceId = $request->header('X-Sequence-Id');
        $processId = Str::uuid()->toString();

        // Defer processing to after response is sent
        defer(function () use ($data, $sequenceId, $processId) {
            try {
                DB::beginTransaction();

                // Prepare bulk upsert data
                $productsToUpsert = [];
                foreach ($data['products'] as $productData) {
                    $productsToUpsert[] = [
                        'ext_id' => $productData['id'],
                        'name' => $productData['name'],
                        'barcode' => $productData['barcode'],
                        'description' => $productData['description'] ?? null,
                        'price' => $productData['price'],
                        'discount_price' => $productData['discount_price'] ?? null,
                        'unit' => $productData['unit'],
                        'category' => $productData['category'] ?? null,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Bulk upsert products
                Product::upsert(
                    $productsToUpsert,
                    ['ext_id'], // Unique key to match on
                    ['name', 'barcode', 'description', 'price', 'discount_price', 'unit', 'category', 'updated_at'] // Fields to update
                );

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                logger()->error('Failed to process product catalog update event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'process_id' => $processId,
                    'sequence_id' => $sequenceId,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            200,
            'Product catalog update event received successfully',
            new EventReceivedResource([
                'products_count' => isset($data['products']) ? count($data['products']) : 0,
                'process_id' => $processId,
            ])
        );
    }

    /**
     * Handle inventory items added event
     *
     * This endpoint processes inventory addition events from external systems.
     * Records are created for tracking inventory additions.
     * Processing is deferred to after the HTTP response is sent.
     * No validation is performed on product_id or branch_id existence.
     *
     * @tags Events
     *
     * @header X-Sequence-Id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam items array required Array of inventory items to add (at least 1 required).
     * @bodyParam items.*.product_id string required Product external ID. Example: PROD-101
     * @bodyParam items.*.branch_id string required Branch external ID. Example: BR001
     * @bodyParam items.*.quantity number required Quantity added (min: 0.001). Example: 10.500
     * @bodyParam items.*.previous_quantity number required Previous quantity before addition. Example: 40.000
     * @bodyParam items.*.total_quantity number required New total quantity after addition. Example: 50.500
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Inventory items added event received successfully",
     *   "result": {
     *     "items_count": 1,
     *     "process_id": "550e8400-e29b-41d4-a716-446655440000"
     *   }
     * }
     */
    public function inventoryItemsAdded(Request $request): JsonResponse
    {
        $data = $request->all();
        $sequenceId = $request->header('X-Sequence-Id');
        $processId = Str::uuid()->toString();

        // Defer processing to after response is sent
        defer(function () use ($data, $sequenceId, $processId) {
            try {
                DB::beginTransaction();

                // Prepare bulk insert data
                $inventoryRecords = [];
                foreach ($data['items'] as $item) {
                    // Look up product by ext_id
                    $product = Product::where('ext_id', $item['product_id'])->first();
                    // Look up branch by ext_id
                    $branch = Branch::where('ext_id', $item['branch_id'])->first();

                    if ($product && $branch) {
                        $inventoryRecords[] = [
                            'product_id' => $product->id,
                            'branch_id' => $branch->id,
                            'type' => 'added',
                            'quantity' => $item['quantity'],
                            'previous_quantity' => $item['previous_quantity'],
                            'new_quantity' => $item['total_quantity'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Bulk insert inventory records
                if (!empty($inventoryRecords)) {
                    InventoryHistory::insert($inventoryRecords);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                logger()->error('Failed to process inventory items added event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'process_id' => $processId,
                    'sequence_id' => $sequenceId,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            200,
            'Inventory items added event received successfully',
            new EventReceivedResource([
                'items_count' => isset($data['items']) ? count($data['items']) : 0,
                'process_id' => $processId,
            ])
        );
    }

    /**
     * Handle inventory items removed event
     *
     * This endpoint processes inventory removal events from external systems.
     * Records are created for tracking inventory removals.
     * Processing is deferred to after the HTTP response is sent.
     * No validation is performed on product_id or branch_id existence.
     *
     * @tags Events
     *
     * @header X-Sequence-Id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam items array required Array of inventory items to remove (at least 1 required).
     * @bodyParam items.*.product_id string required Product external ID. Example: PROD-101
     * @bodyParam items.*.branch_id string required Branch external ID. Example: BR001
     * @bodyParam items.*.quantity number required Quantity removed (min: 0.001). Example: 5.000
     * @bodyParam items.*.previous_quantity number required Previous quantity before removal. Example: 50.500
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Inventory items removed event received successfully",
     *   "result": {
     *     "items_count": 1,
     *     "process_id": "550e8400-e29b-41d4-a716-446655440000"
     *   }
     * }
     */
    public function inventoryItemsRemoved(Request $request): JsonResponse
    {
        $data = $request->all();
        $sequenceId = $request->header('X-Sequence-Id');
        $processId = Str::uuid()->toString();

        // Defer processing to after response is sent
        defer(function () use ($data, $sequenceId, $processId) {
            try {
                DB::beginTransaction();

                // Prepare bulk insert data
                $inventoryRecords = [];
                foreach ($data['items'] as $item) {
                    // Look up product by ext_id
                    $product = Product::where('ext_id', $item['product_id'])->first();
                    // Look up branch by ext_id
                    $branch = Branch::where('ext_id', $item['branch_id'])->first();

                    if ($product && $branch) {
                        $newQuantity = max(0, $item['previous_quantity'] - $item['quantity']);

                        $inventoryRecords[] = [
                            'product_id' => $product->id,
                            'branch_id' => $branch->id,
                            'type' => 'removed',
                            'quantity' => $item['quantity'],
                            'previous_quantity' => $item['previous_quantity'],
                            'new_quantity' => $newQuantity,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }

                // Bulk insert inventory records
                if (!empty($inventoryRecords)) {
                    InventoryHistory::insert($inventoryRecords);
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                logger()->error('Failed to process inventory items removed event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'process_id' => $processId,
                    'sequence_id' => $sequenceId,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            200,
            'Inventory items removed event received successfully',
            new EventReceivedResource([
                'items_count' => isset($data['items']) ? count($data['items']) : 0,
                'process_id' => $processId,
            ])
        );
    }

    /**
     * Cancel items from a receipt
     *
     * This endpoint marks specific items in a sale as cancelled.
     * Processing is deferred to after the HTTP response is sent.
     * If all items are cancelled, the sale status becomes 'cancelled', otherwise 'partially_cancelled'.
     *
     * @tags Events
     *
     * @header X-Sequence-Id integer required Event sequence ID for ordering. Example: 1
     * @bodyParam receipt_id string required Receipt number to cancel items from. Example: RCP-20251121-001
     * @bodyParam branch_id string required Branch external identifier (must match receipt's branch). Example: BR001
     * @bodyParam cashier_id string required Cashier identifier performing the cancellation. Example: CASH123
     * @bodyParam cancelled_items array required Array of items to cancel (at least 1 required).
     * @bodyParam cancelled_items.*.product_id string required Product ID. Example: PROD-001
     * @bodyParam cancelled_items.*.amount number required Item amount. Example: 25.00
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 200 scenario="Success" {
     *   "ok": true,
     *   "code": 200,
     *   "message": "Promo code cancellation event received successfully",
     *   "result": {
     *     "cancelled_items_count": 2,
     *     "process_id": "550e8400-e29b-41d4-a716-446655440000"
     *   }
     * }
     */
    public function promoCodeCancelled(Request $request): JsonResponse
    {
        $data = $request->all();
        $sequenceId = $request->header('X-Sequence-Id');
        $processId = Str::uuid()->toString();

        // Defer processing to after response is sent
        defer(function () use ($data, $sequenceId, $processId) {
            try {
                DB::beginTransaction();

                $sale = Sale::where('receipt_id', $data['receipt_id'])->firstOrFail();

                // Mark items as cancelled by matching product_id
                foreach ($data['cancelled_items'] as $cancelItem) {
                    $saleItem = SaleItem::where('sale_id', $sale->id)
                        ->where('product_id', $cancelItem['product_id'])
                        ->where('is_cancelled', false)
                        ->first();

                    if ($saleItem) {
                        $saleItem->is_cancelled = true;
                        $saleItem->save();
                    }
                }

                // Update sale status
                $allItemsCancelled = $sale->items()->where('is_cancelled', false)->count() === 0;
                $sale->status = $allItemsCancelled ? 'cancelled' : 'partially_cancelled';
                $sale->save();

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                logger()->error('Failed to process promo code cancellation event', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'process_id' => $processId,
                    'sequence_id' => $sequenceId,
                ]);
            }
        });

        return ApiResponse::make(
            true,
            200,
            'Promo code cancellation event received successfully',
            new EventReceivedResource([
                'cancelled_items_count' => isset($data['cancelled_items']) ? count($data['cancelled_items']) : 0,
                'process_id' => $processId,
            ])
        );
    }
}
