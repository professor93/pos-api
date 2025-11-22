<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InventoryItemsAddedRequest extends FormRequest
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
            'items.*.branch_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.previous_quantity' => 'required|numeric|min:0',
            'items.*.total_quantity' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'items.min' => 'At least one item is required',
        ];
    }
}
