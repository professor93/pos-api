<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'receipt_id',
        'branch_id',
        'cashier_id',
        'total_amount',
        'sold_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * Get the branch for this sale.
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the items for this sale.
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }
}
