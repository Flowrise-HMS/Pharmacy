<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Database\Factories\DispenseFactory;

class Dispense extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'request_item_id',
        'medication_id',
        'dispensed_by',
        'quantity',
        'batch_number',
        'expiry_date',
        'notes',
        'dispensed_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'expiry_date' => 'date',
        'dispensed_at' => 'datetime',
    ];

    protected static function newFactory(): DispenseFactory
    {
        return DispenseFactory::new();
    }

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class);
    }

    public function dispensedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'dispensed_by');
    }
}
