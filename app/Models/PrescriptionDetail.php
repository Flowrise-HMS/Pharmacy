<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Models\RequestItem;

class PrescriptionDetail extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'request_item_id',
        'dosage',
        'dose_amount',
        'dose_unit_id',
        'frequency',
        'route',
        'duration_days',
        'instructions',
        'prn',
        'indication',
        'refills',
        'total_administrations',
    ];

    protected $casts = [
        'prn' => 'boolean',
        'duration_days' => 'integer',
        'refills' => 'integer',
        'total_administrations' => 'integer',
        'dose_amount' => 'decimal:4',
    ];

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function doseUnit(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Unit::class, 'dose_unit_id');
    }
}
