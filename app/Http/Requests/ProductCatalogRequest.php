<?php

namespace App\Http\Requests;

use App\Helpers\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|string|max:255',
            'products.*.name' => 'required|string|max:255',
            'products.*.barcode' => 'required|string|regex:/^[A-Za-z0-9\-]+$/|max:50',
            'products.*.description' => 'nullable|string|max:1000',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.discount_price' => 'nullable|numeric|min:0',
            'products.*.unit' => 'required|string|max:50',
            'products.*.category' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'products.min' => 'At least one product is required',
            'products.*.barcode.regex' => 'Barcode must contain only alphanumeric characters and hyphens',
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
