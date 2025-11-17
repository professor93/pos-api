<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'check_number',
        'branch_id',
        'store_id',
        'cashier_id',
        'total_amount',
        'discount_amount',
        'final_amount',
        'fiscal_sign',
        'terminal_id',
        'sale_datetime',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'final_amount' => 'decimal:2',
            'sale_datetime' => 'datetime',
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

    /**
     * Get the promo code generation history for this sale.
     */
    public function promoCodeHistory(): HasOne
    {
        return $this->hasOne(PromoCodeGenerationHistory::class);
    }
}
