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
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [];
    }

    /**
     * Get the sale associated with this promo code generation.
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
