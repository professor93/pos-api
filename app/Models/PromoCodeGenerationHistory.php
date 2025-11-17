<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoCodeGenerationHistory extends Model
{
    use HasFactory;

    protected $table = 'promo_code_generation_history';

    protected $fillable = [
        'sale_id',
        'promo_code',
        'amount_spent',
        'discount_received',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount_spent' => 'decimal:2',
            'discount_received' => 'decimal:2',
        ];
    }

    /**
     * Get the sale associated with this promo code generation.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
