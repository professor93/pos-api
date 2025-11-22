<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'id',
        'name',
        'barcode',
        'description',
        'price',
        'discount_price',
        'unit',
        'category',
        'is_active',
        'status',
        'sequence_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'discount_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the sale items for this product.
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Get the inventory history for this product.
     */
    public function inventoryHistory(): HasMany
    {
        return $this->hasMany(InventoryHistory::class);
    }
}
