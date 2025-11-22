<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class InventoryItemsRemovedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string|max:255',
            'items.*.branch_id' => 'required|string|max:255',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.previous_quantity' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
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
