<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Database\Factories\StockItemFactory;

class StockItem extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'medication_id',
        'branch_id',
        'quantity_on_hand',
        'reorder_point',
    ];

    protected $casts = [
        'quantity_on_hand' => 'integer',
        'reorder_point' => 'integer',
    ];

    protected static function newFactory(): StockItemFactory
    {
        return StockItemFactory::new();
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getQuantityOnHandWithUnitAttribute(): string
    {
        return $this->quantity_on_hand . ' ' . ($this->medication?->stockUnit?->label ?? '');
    }

    public function getReorderPointWithUnitAttribute(): string
    {
        return $this->reorder_point . ' ' . ($this->medication?->stockUnit?->label ?? '');
    }
}
