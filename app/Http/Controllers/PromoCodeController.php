<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Models\Branch;
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
     * The store (branch) is looked up by store_id (matching the branch code).
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
     * @response 404 {
     *   "ok": false,
     *   "code": 404,
     *   "message": "Branch not found for the provided store_id"
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'check_number' => 'required|string|unique:sales,check_number',
            'total_amount' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'sold_at' => 'required|date',
            'branch_id' => 'required|string',
            'cashier_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.barcode' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
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

            // Look up branch by branch_id (which matches branch code)
            $branch = Branch::where('code', $data['branch_id'])->first();

            if (!$branch) {
                return ApiResponse::make(
                    false,
                    404,
                    'Branch not found for the provided branch_id'
                );
            }

            // Create the sale record
            $sale = Sale::create([
                'check_number' => $data['check_number'],
                'branch_id' => $branch->id,
                'store_id' => $data['branch_id'],
                'cashier_id' => $data['cashier_id'],
                'total_amount' => $data['total_amount'],
                'discount_amount' => $data['discount_amount'],
                'final_amount' => $finalAmount,
                'fiscal_sign' => $data['fiscal_sign'] ?? null,
                'terminal_id' => $data['terminal_id'] ?? null,
                'sold_at' => $data['sold_at'],
                'status' => 'completed',
            ]);

            // Create sale items
            foreach ($data['items'] as $item) {
                $itemFinalPrice = $item['total_price'] - ($item['discount_price'] ?? 0);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'barcode' => $item['barcode'],
                    'quantity' => 1.000,
                    'unit' => 'pcs',
                    'unit_price' => $item['price'],
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
