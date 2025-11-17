<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\PromoCodeGenerationHistory;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PromoCodeController extends Controller
{
    /**
     * Generate a promo code based on a sales receipt
     *
     * This endpoint creates a sale record and generates a promo code for the customer.
     * The branch must exist in the system before generating a promo code.
     *
     * @tags Promo Codes
     *
     * @param  Request  $request
     * @return JsonResponse
     *
     * @response 201 {
     *   "ok": true,
     *   "code": 201,
     *   "message": "Promo code generated successfully",
     *   "result": {
     *     "sale_id": 1,
     *     "check_number": "CHK-001",
     *     "promo_code": "PROMO20251117ABC123",
     *     "amount_spent": 90.00
     *   },
     *   "meta": {
     *     "timestamp": "2025-11-17T10:00:00.000000Z"
     *   }
     * }
     *
     * @response 400 {
     *   "ok": false,
     *   "code": 400,
     *   "message": "Validation failed",
     *   "meta": {
     *     "errors": {
     *       "branch_id": ["The branch id field is required."]
     *     }
     *   }
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'check_number' => 'required|string|unique:sales,check_number',
            'branch_id' => 'required|integer|exists:branches,id',
            'total_amount' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'sale_datetime' => 'required|date',
            'store_id' => 'required|string',
            'cashier_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|integer',
            'items.*.barcode' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.discount_price' => 'nullable|numeric|min:0',
            'fiscal_sign' => 'nullable|string',
            'terminal_id' => 'nullable|string',
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
            $finalAmount = $data['total_amount'] - $data['discount_amount'];

            // Create the sale record
            $sale = Sale::create([
                'check_number' => $data['check_number'],
                'branch_id' => $data['branch_id'],
                'store_id' => $data['store_id'],
                'cashier_id' => $data['cashier_id'],
                'total_amount' => $data['total_amount'],
                'discount_amount' => $data['discount_amount'],
                'final_amount' => $finalAmount,
                'fiscal_sign' => $data['fiscal_sign'] ?? null,
                'terminal_id' => $data['terminal_id'] ?? null,
                'sale_datetime' => $data['sale_datetime'],
                'status' => 'completed',
            ]);

            // Create sale items
            foreach ($data['items'] as $item) {
                $itemFinalPrice = $item['total_price'] - ($item['discount_price'] ?? 0);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['item_id'],
                    'barcode' => $item['barcode'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total_price'],
                    'discount_price' => $item['discount_price'] ?? 0,
                    'final_price' => $itemFinalPrice,
                    'is_cancelled' => false,
                ]);
            }

            // Generate promo code (you can implement your own logic here)
            $promoCode = $this->generatePromoCodeLogic($sale);

            // Record promo code generation history
            $history = PromoCodeGenerationHistory::create([
                'sale_id' => $sale->id,
                'promo_code' => $promoCode,
                'amount_spent' => $finalAmount,
                'discount_received' => $data['discount_amount'],
                'status' => 'generated',
            ]);

            DB::commit();

            return ApiResponse::make(
                true,
                201,
                'Promo code generated successfully',
                [
                    'sale_id' => $sale->id,
                    'check_number' => $sale->check_number,
                    'promo_code' => $promoCode,
                    'amount_spent' => $finalAmount,
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
                'Failed to generate promo code',
                null,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Cancel items from a receipt and update promo code status
     *
     * This endpoint marks specific items in a sale as cancelled and updates the promo code status.
     * If all items are cancelled, the sale status becomes 'cancelled', otherwise 'partially_cancelled'.
     *
     * @tags Promo Codes
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
    public function cancel(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'receipt_id' => 'required|integer|exists:sales,id',
            'store_id' => 'required|string',
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

        try {
            DB::beginTransaction();

            $data = $validator->validated();
            $sale = Sale::findOrFail($data['receipt_id']);

            // Verify store_id and cashier_id match (security check)
            if ($sale->store_id !== $data['store_id']) {
                return ApiResponse::make(
                    false,
                    403,
                    'Store ID does not match the receipt',
                );
            }

            // Mark items as cancelled
            $cancelledItems = SaleItem::whereIn('id', $data['cancelled_items'])
                ->where('sale_id', $sale->id)
                ->get();

            if ($cancelledItems->isEmpty()) {
                return ApiResponse::make(
                    false,
                    404,
                    'No matching items found for this sale',
                );
            }

            $totalCancelledAmount = 0;
            foreach ($cancelledItems as $item) {
                if (!$item->is_cancelled) {
                    $item->is_cancelled = true;
                    $item->save();
                    $totalCancelledAmount += $item->final_price;
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

            DB::commit();

            return ApiResponse::make(
                true,
                200,
                'Items cancelled successfully',
                [
                    'sale_id' => $sale->id,
                    'status' => $sale->status,
                    'cancelled_items_count' => $cancelledItems->count(),
                    'total_cancelled_amount' => $totalCancelledAmount,
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
                'Failed to cancel items',
                null,
                ['error' => $e->getMessage()]
            );
        }
    }

    /**
     * Generate a promo code based on sale data
     * You can implement your own logic here
     */
    private function generatePromoCodeLogic(Sale $sale): string
    {
        // Example: Generate a promo code based on sale ID and timestamp
        $prefix = 'PROMO';
        $timestamp = now()->format('Ymd');
        $unique = strtoupper(substr(md5($sale->id . $sale->check_number), 0, 6));

        return "{$prefix}{$timestamp}{$unique}";
    }
}
