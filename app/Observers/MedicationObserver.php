<?php

namespace Modules\Pharmacy\Observers;

use Modules\Pharmacy\Classes\Support\UnitResolver;
use Modules\Pharmacy\Models\Medication;

class MedicationObserver
{
    public function creating(Medication $medication): void
    {
        if (blank($medication->generic_name) && blank($medication->brand_name)) {
            $medication->generic_name = 'Unspecified';
        }

        if (blank($medication->stock_unit_id) && $medication->dosage_form) {
            $defaults = app(UnitResolver::class)->defaultsForDosageForm($medication->dosage_form);
            $medication->stock_unit_id ??= $defaults['stock_unit']?->id;
            $medication->billing_unit_id ??= $defaults['billing_unit']?->id;
            $medication->dose_unit_id ??= $defaults['dose_unit']?->id;
        }
    }

    public function saved(Medication $medication): void
    {
        $service = $medication->service;

        if (! $service) {
            return;
        }

        $changed = false;

        if ((bool) $service->is_active !== (bool) $medication->is_active) {
            $service->is_active = $medication->is_active;
            $changed = true;
        }

        $expectedName = $medication->displayName();
        if ($service->name !== $expectedName) {
            $service->name = $expectedName;
            $changed = true;
        }

        if ($changed) {
            $service->saveQuietly();
        }
    }
}
