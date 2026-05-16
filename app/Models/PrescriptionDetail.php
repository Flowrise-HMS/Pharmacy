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
        'frequency',
        'route',
        'duration_days',
        'instructions',
        'prn',
        'indication',
        'refills',
    ];

    protected $casts = [
        'prn' => 'boolean',
        'duration_days' => 'integer',
        'refills' => 'integer',
    ];

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }
}
