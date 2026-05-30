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
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Modules\Pharmacy\Observers\MedicationObserver;

#[ObservedBy([MedicationObserver::class])]

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
        'stock_unit_id',
        'billing_unit_id',
        'dose_unit_id',
        'units_per_stock_unit',
    ];

    protected $casts = [
        'dosage_form' => DosageForm::class,
        'controlled_schedule' => ControlledSchedule::class,
        'is_active' => 'boolean',
        'units_per_stock_unit' => 'decimal:4',
    ];

    protected static function newFactory(): MedicationFactory
    {
        return MedicationFactory::new();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function displayName(): string
    {
        if ($this->brand_name) {
            return $this->strength
                ? "{$this->brand_name} {$this->strength}"
                : $this->brand_name;
        }

        if ($this->generic_name) {
            return $this->strength
                ? "{$this->generic_name} {$this->strength}"
                : $this->generic_name;
        }

        return 'Unspecified';
    }

    public function billingService(): ?Service
    {
        if ($this->relationLoaded('service')) {
            return $this->service;
        }

        return $this->load('service')->service;
    }

    public function resolveUnitPrice(): string
    {
        $service = $this->billingService();

        if (! $service) {
            throw new \RuntimeException(
                "{$this->displayName()} is not configured for billing. Set a price in Medications."
            );
        }

        return (string) ($service->price ?? '0');
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

    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Unit::class, 'stock_unit_id');
    }

    public function billingUnit(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Unit::class, 'billing_unit_id');
    }

    public function doseUnit(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Unit::class, 'dose_unit_id');
    }
}
