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
     * The store (branch) is looked up by branch_id (matching the branch code).
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
     *     "codes": [
     *       {
     *         "product_id": 1,
     *         "code": "PROMO20251117ABC123"
     *       },
     *       {
     *         "product_id": 2,
     *         "code": "PROMO20251117ABC124"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 400 {
     *   "ok": false,
     *   "code": 400,
     *   "message": "Validation failed"
     * }
     *
     * @response 404 {
     *   "ok": false,
     *   "code": 404,
     *   "message": "Branch not found for the provided branch_id"
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'check_number' => 'required|string|unique:sales,check_number',
            'total_amount' => 'required|numeric|min:0',
            'sold_at' => 'required|date',
            'branch_id' => 'required|string',
            'cashier_id' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.barcode' => 'required|string',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
            'items.*.discount_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::make(
                false,
                400,
                'Validation failed'
            );
        }

        try {
            DB::beginTransaction();

            $data = $validator->validated();

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
                'discount_amount' => 0,
                'final_amount' => $data['total_amount'],
                'fiscal_sign' => null,
                'terminal_id' => null,
                'sold_at' => $data['sold_at'],
                'status' => 'completed',
            ]);

            // Create sale items and generate promo codes
            $promoCodes = [];
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

                // Generate promo code for each item
                $promoCode = $this->generatePromoCodeLogic($sale, $item['product_id']);

                // Record promo code generation history
                PromoCodeGenerationHistory::create([
                    'sale_id' => $sale->id,
                    'promo_code' => $promoCode,
                    'amount_spent' => $data['total_amount'],
                    'discount_received' => 0,
                    'status' => 'generated',
                ]);

                $promoCodes[] = [
                    'product_id' => $item['product_id'],
                    'code' => $promoCode,
                ];
            }

            DB::commit();

            return ApiResponse::make(
                true,
                201,
                'Promo code generated successfully',
                [
                    'sale_id' => $sale->id,
                    'check_number' => $sale->check_number,
                    'codes' => $promoCodes,
                ]
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::make(
                false,
                500,
                'Failed to generate promo code'
            );
        }
    }

    /**
     * Generate a promo code based on sale data and product
     * You can implement your own logic here
     */
    private function generatePromoCodeLogic(Sale $sale, int $productId): string
    {
        // Example: Generate a promo code based on sale ID, product ID and timestamp
        $prefix = 'PROMO';
        $timestamp = now()->format('Ymd');
        $unique = strtoupper(substr(md5($sale->id . $sale->check_number . $productId), 0, 6));

        return "{$prefix}{$timestamp}{$unique}";
    }
}
