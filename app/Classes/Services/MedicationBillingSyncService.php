<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Pharmacy\Models\Medication;

final class MedicationBillingSyncService
{
    public function createMedicationWithBilling(array $medicationData, array $billingData): Medication
    {
        $category = ServiceCategory::query()->firstOrCreate(
            ['code' => ServiceCategoryCode::MED->value],
            [
                'name' => 'Medications',
                'description' => 'Medication catalog services',
                'is_active' => true,
                'sort_order' => 50,
            ]
        );

        $serviceName = Arr::get(
            $billingData,
            'service_name',
            Arr::get($medicationData, 'service_name', $this->deriveServiceName($medicationData))
        );

        $baseSlug = Str::slug($serviceName);
        $slug = $baseSlug;
        $counter = 0;

        while (Service::query()->where('slug', $slug)->exists()) {
            $counter++;
            $slug = $baseSlug.'-'.$counter;
        }

        $service = Service::query()->create([
            'category_id' => $category->id,
            'name' => $serviceName,
            'description' => Arr::get($billingData, 'service_description', $serviceName),
            'code' => Arr::get($billingData, 'service_code'),
            'slug' => $slug,
            'price' => (float) Arr::get($billingData, 'price', 0),
            'insurance_price' => (float) Arr::get($billingData, 'insurance_price', Arr::get($billingData, 'price', 0)),
            'is_insurance_covered' => (bool) Arr::get($billingData, 'is_insurance_covered', false),
            'coverage_type' => Arr::get($billingData, 'coverage_type', 'none'),
            'requires_payment_before' => (bool) Arr::get($billingData, 'requires_payment_before', false),
            'requires_prescription' => (bool) Arr::get($billingData, 'requires_prescription', false),
            'is_billable' => true,
            'billing_type' => Arr::get($billingData, 'billing_type', 'fixed'),
            'is_active' => (bool) Arr::get($medicationData, 'is_active', true),
            'metadata' => Arr::get($billingData, 'service_metadata', []),
        ]);

        $medicationData['service_id'] = $service->id;

        return Medication::query()->create(Arr::only($medicationData, (new Medication)->getFillable()));
    }

    public function syncBilling(Medication $medication, array $billingData): void
    {
        $service = $medication->service;

        if (! $service) {
            throw new \RuntimeException(
                "Medication {$medication->id} has no associated billing service. Use ensureBillingService to create one."
            );
        }

        $service->update([
            'price' => (float) Arr::get($billingData, 'price', $service->price),
            'insurance_price' => (float) Arr::get($billingData, 'insurance_price', $service->insurance_price),
            'is_insurance_covered' => (bool) Arr::get($billingData, 'is_insurance_covered', $service->is_insurance_covered),
            'coverage_type' => Arr::get($billingData, 'coverage_type', $service->coverage_type?->value ?? 'none'),
            'requires_payment_before' => (bool) Arr::get($billingData, 'requires_payment_before', $service->requires_payment_before),
            'requires_prescription' => (bool) Arr::get($billingData, 'requires_prescription', $service->requires_prescription),
            'is_active' => (bool) Arr::get($billingData, 'is_active', $service->is_active),
        ]);
    }

    public function ensureBillingService(Medication $medication, array $billingData): Service
    {
        if ($medication->service) {
            $this->syncBilling($medication, $billingData);

            return $medication->fresh()->service;
        }

        $category = ServiceCategory::query()->firstOrCreate(
            ['code' => ServiceCategoryCode::MED->value],
            [
                'name' => 'Medications',
                'description' => 'Medication catalog services',
                'is_active' => true,
                'sort_order' => 50,
            ]
        );

        $serviceName = Arr::get(
            $billingData,
            'service_name',
            $this->deriveServiceName($medication->toArray())
        );

        $baseSlug = Str::slug($serviceName);
        $slug = $baseSlug;
        $counter = 0;

        while (Service::query()->where('slug', $slug)->exists()) {
            $counter++;
            $slug = $baseSlug.'-'.$counter;
        }

        $service = Service::query()->create([
            'category_id' => $category->id,
            'name' => $serviceName,
            'description' => Arr::get($billingData, 'service_description', $serviceName),
            'slug' => $slug,
            'price' => (float) Arr::get($billingData, 'price', 0),
            'insurance_price' => (float) Arr::get($billingData, 'insurance_price', Arr::get($billingData, 'price', 0)),
            'is_insurance_covered' => (bool) Arr::get($billingData, 'is_insurance_covered', false),
            'coverage_type' => Arr::get($billingData, 'coverage_type', 'none'),
            'requires_payment_before' => (bool) Arr::get($billingData, 'requires_payment_before', false),
            'requires_prescription' => (bool) Arr::get($billingData, 'requires_prescription', false),
            'is_billable' => true,
            'billing_type' => Arr::get($billingData, 'billing_type', 'fixed'),
            'is_active' => (bool) ($medication->is_active ?? true),
            'metadata' => Arr::get($billingData, 'service_metadata', []),
        ]);

        $medication->update(['service_id' => $service->id]);

        return $service->fresh();
    }

    private function deriveServiceName(array $data): string
    {
        $name = Arr::get($data, 'brand_name') ?: Arr::get($data, 'generic_name', '');
        $strength = Arr::get($data, 'strength');

        return $strength ? "{$name} {$strength}" : $name;
    }
}
