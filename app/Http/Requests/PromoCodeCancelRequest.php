<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromoCodeCancelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_id' => 'required|string|exists:sales,receipt_id|max:255',
            'branch_id' => 'required|string|max:255',
            'cashier_id' => 'required|string|max:255',
            'cancelled_items' => 'required|array|min:1',
            'cancelled_items.*.product_id' => 'required|string|max:255',
            'cancelled_items.*.amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_id.exists' => 'Sale not found',
            'cancelled_items.min' => 'At least one item must be cancelled',
        ];
    }
}
