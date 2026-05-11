<?php

namespace Modules\Pharmacy\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Database\Factories\MedicationFactory;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DosageForm;

class Medication extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'service_id',
        'rxnorm_code',
        'ndc_code',
        'generic_name',
        'brand_name',
        'dosage_form',
        'strength',
        'controlled_schedule',
        'is_active',
    ];

    protected $casts = [
        'dosage_form' => DosageForm::class,
        'controlled_schedule' => ControlledSchedule::class,
        'is_active' => 'boolean',
    ];

    protected static function newFactory(): MedicationFactory
    {
        return MedicationFactory::new();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(Dispense::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
