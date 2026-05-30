<?php

namespace Modules\Pharmacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Core\Models\BaseModel;
use Modules\Pharmacy\Database\Factories\StockMovementFactory;

class StockMovement extends BaseModel
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'branch_id',
        'medication_id',
        'delta',
        'quantity_after',
        'unit_label_snapshot',
        'reason',
        'reference_type',
        'reference_id',
        'performed_by',
    ];

    protected $casts = [
        'delta' => 'integer',
        'quantity_after' => 'integer',
    ];

    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Medication::class, 'medication_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
