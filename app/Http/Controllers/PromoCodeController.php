<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\PromoCodeGenerateRequest;
use App\Http\Resources\PromoCodeGeneratedResource;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Services\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * POS Promo Code API
 *
 * @group Promo Codes
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
class PromoCodeController extends Controller
{
    public function __construct(
        private PromoCodeService $promoCodeService
    ) {}

    /**
     * Generate a promo code based on a sales receipt
     *
     * This endpoint creates a sale record and generates a promo code for the customer.
     * The branch is looked up by branch_id (matching the branch ext_id).
     * Each item in the sale generates a unique 10-character alphanumeric promo code.
     *
     * @tags Promo Codes
     *
     * @bodyParam receipt_id string required Unique receipt number. Example: RCP-20251121-001
     * @bodyParam total_amount number required Total sale amount. Example: 150.50
     * @bodyParam sold_at string required Sale datetime in ISO 8601 format. Example: 2025-11-21T10:30:00Z
     * @bodyParam branch_id string required Branch external identifier. Example: BR001
     * @bodyParam cashier_id string required Cashier identifier. Example: CASH123
     * @bodyParam items array required Array of sale items (at least 1 item required).
     * @bodyParam items.*.product_id string required Product external ID. Example: PROD-001
     * @bodyParam items.*.amount number required Unit price of the item. Example: 25.00
     *
     * @param  PromoCodeGenerateRequest  $request
     * @return JsonResponse
     *
     * @response 201 scenario="Success" {
     *   "ok": true,
     *   "code": 201,
     *   "message": "Promo code generated successfully",
     *   "result": {
     *     "receipt_id": "RCP-001",
     *     "codes": [
     *       {
     *         "product_id": "PROD-001",
     *         "code": "A5B3C7D9E1"
     *       },
     *       {
     *         "product_id": "PROD-002",
     *         "code": "K2M8N4P6Q0"
     *       }
     *     ]
     *   }
     * }
     *
     * @response 400 scenario="Validation Error" {
     *   "ok": false,
     *   "code": 400,
     *   "message": "Validation failed",
     *   "meta": {
     *     "errors": {
     *       "receipt_id": ["This receipt ID has already been processed"]
     *     }
     *   }
     * }
     *
     * @response 404 scenario="Branch Not Found" {
     *   "ok": false,
     *   "code": 404,
     *   "message": "Branch not found for the provided branch_id"
     * }
     *
     * @response 500 scenario="Server Error" {
     *   "ok": false,
     *   "code": 500,
     *   "message": "Failed to generate promo code"
     * }
     */
    public function generate(PromoCodeGenerateRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();

            // Look up branch by ext_id
            $branch = Branch::where('ext_id', $data['branch_id'])->first();

            if (!$branch) {
                return ApiResponse::make(
                    false,
                    404,
                    'Branch not found for the provided branch_id'
                );
            }

            DB::beginTransaction();

            // Create the sale record
            $sale = Sale::create([
                'receipt_id' => $data['receipt_id'],
                'branch_id' => $branch->id,
                'cashier_id' => $data['cashier_id'],
                'total_amount' => $data['total_amount'],
                'sold_at' => $data['sold_at'],
                'status' => 'completed',
            ]);

            // Prepare bulk insert data for sale items and generate promo codes
            $saleItems = [];
            $promoCodes = [];

            foreach ($data['items'] as $item) {
                $promoCode = $this->promoCodeService->generateCode();

                $saleItems[] = [
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'unit' => 'pcs',
                    'unit_price' => $item['amount'],
                    'is_cancelled' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $promoCodes[] = [
                    'product_id' => $item['product_id'],
                    'code' => $promoCode,
                ];
            }

            // Bulk insert sale items
            SaleItem::insert($saleItems);

            DB::commit();

            return ApiResponse::make(
                true,
                201,
                'Promo code generated successfully',
                new PromoCodeGeneratedResource([
                    'receipt_id' => $sale->receipt_id,
                    'codes' => $promoCodes,
                ])
            );
        } catch (\Exception $e) {
            DB::rollBack();

            logger()->error('Failed to generate promo code', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated(),
            ]);

            return ApiResponse::make(
                false,
                500,
                'Failed to generate promo code'
            );
        }
    }
}
