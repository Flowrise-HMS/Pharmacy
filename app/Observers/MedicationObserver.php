<?php

namespace Modules\Pharmacy\Observers;

use Modules\Pharmacy\Models\Medication;

class MedicationObserver
{
    public function creating(Medication $medication): void
    {
        if (blank($medication->generic_name) && blank($medication->brand_name)) {
            $medication->generic_name = 'Unspecified';
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
