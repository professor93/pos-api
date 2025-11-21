<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventReceivedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource['message'],
            'products_count' => $this->resource['products_count'] ?? null,
            'items_count' => $this->resource['items_count'] ?? null,
            'cancelled_items_count' => $this->resource['cancelled_items_count'] ?? null,
        ];
    }
}
