<?php

namespace Modules\Pharmacy\Observers;

use Modules\Pharmacy\Models\Medication;

class MedicationObserver
{
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
