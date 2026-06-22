<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Models\Unit;
use Modules\Pharmacy\Enums\AdministrationContext;

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
        'administration_context',
        'course_started_at',
        'course_end_at',
        'next_dose_at',
        'max_administrations',
        'administer_in_facility_flag',
    ];

    protected $casts = [
        'prn' => 'boolean',
        'duration_days' => 'integer',
        'refills' => 'integer',
        'total_administrations' => 'integer',
        'max_administrations' => 'integer',
        'dose_amount' => 'decimal:4',
        'administration_context' => AdministrationContext::class,
        'course_started_at' => 'datetime',
        'course_end_at' => 'datetime',
        'next_dose_at' => 'datetime',
        'administer_in_facility_flag' => 'boolean',
    ];

    public function requestItem(): BelongsTo
    {
        return $this->belongsTo(RequestItem::class);
    }

    public function doseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'dose_unit_id');
    }

    public function isInFacility(): bool
    {
        return $this->administration_context === AdministrationContext::IN_FACILITY;
    }

    public function isTakeHome(): bool
    {
        return $this->administration_context === AdministrationContext::TAKE_HOME;
    }
}
