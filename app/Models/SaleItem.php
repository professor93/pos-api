<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'product_id',
        'unit',
        'unit_price',
        'is_cancelled',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'is_cancelled' => 'boolean',
        ];
    }

    /**
     * Get the sale for this item.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
