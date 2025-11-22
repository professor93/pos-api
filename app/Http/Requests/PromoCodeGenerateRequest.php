<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class PromoCodeGenerateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'receipt_id' => 'required|string|unique:sales,receipt_id|max:255',
            'total_amount' => 'required|numeric|min:0',
            'sold_at' => 'required|date',
            'branch_id' => 'required|string|max:255',
            'cashier_id' => 'required|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'receipt_id.unique' => 'This receipt ID has already been processed',
            'items.min' => 'At least one item is required',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::make(
                false,
                422,
                'Validation failed',
                null,
                ['errors' => $validator->errors()]
            )
        );
    }
}
