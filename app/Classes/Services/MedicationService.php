<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;

class MedicationService
{
    public function createFromDrug(Drug $drug, array $data = []): Medication
    {
        return $this->createWithService([
            'rxnorm_code' => Arr::get($data, 'rxnorm_code', $drug->rxnorm_code),
            'ndc_code' => Arr::get($data, 'ndc_code', $drug->ndc_code),
            'generic_name' => Arr::get($data, 'generic_name', $drug->generic_name),
            'brand_name' => Arr::get($data, 'brand_name', $drug->brand_name),
            'dosage_form' => Arr::get($data, 'dosage_form', $this->resolveDosageForm($drug->dosage_form_text)),
            'strength' => Arr::get($data, 'strength', $drug->strength_text),
            'service_name' => Arr::get($data, 'service_name', $drug->display_name),
            'service_description' => Arr::get($data, 'service_description', $drug->display_name),
            'service_code' => Arr::get($data, 'service_code'),
            'price' => Arr::get($data, 'price', 0),
            'insurance_price' => Arr::get($data, 'insurance_price', Arr::get($data, 'price', 0)),
            'is_insurance_covered' => Arr::get($data, 'is_insurance_covered', false),
            'coverage_type' => Arr::get($data, 'coverage_type', 'none'),
            'requires_payment_before' => Arr::get($data, 'requires_payment_before', false),
            'requires_prescription' => Arr::get($data, 'requires_prescription', false),
            'billing_type' => Arr::get($data, 'billing_type', 'fixed'),
            'is_active' => Arr::get($data, 'is_active', true),
            'service_metadata' => Arr::get($data, 'service_metadata', []),
        ]);
    }

    public function createWithService(array $data): Medication
    {
        $category = ServiceCategory::query()->firstOrCreate(
            ['code' => 'MED'],
            [
                'name' => 'Medications',
                'description' => 'Medication catalog services',
                'is_active' => true,
                'sort_order' => 50,
            ]
        );

        $service = Service::query()->create([
            'category_id' => $category->id,
            'name' => Arr::get($data, 'service_name', Arr::get($data, 'generic_name')),
            'description' => Arr::get($data, 'service_description', Arr::get($data, 'generic_name')),
            'code' => Arr::get($data, 'service_code'),
            'slug' => Str::slug(Arr::get($data, 'service_name', Arr::get($data, 'generic_name'))),
            'price' => (float) Arr::get($data, 'price', 0),
            'insurance_price' => (float) Arr::get($data, 'insurance_price', Arr::get($data, 'price', 0)),
            'is_insurance_covered' => (bool) Arr::get($data, 'is_insurance_covered', false),
            'coverage_type' => Arr::get($data, 'coverage_type', 'none'),
            'requires_payment_before' => (bool) Arr::get($data, 'requires_payment_before', false),
            'requires_prescription' => (bool) Arr::get($data, 'requires_prescription', false),
            'is_billable' => true,
            'billing_type' => Arr::get($data, 'billing_type', 'fixed'),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
            'metadata' => Arr::get($data, 'service_metadata', []),
        ]);

        return Medication::query()->create([
            'service_id' => $service->id,
            'rxnorm_code' => Arr::get($data, 'rxnorm_code'),
            'ndc_code' => Arr::get($data, 'ndc_code'),
            'generic_name' => Arr::get($data, 'generic_name'),
            'brand_name' => Arr::get($data, 'brand_name'),
            'dosage_form' => Arr::get($data, 'dosage_form'),
            'strength' => Arr::get($data, 'strength'),
            'controlled_schedule' => Arr::get($data, 'controlled_schedule'),
            'is_active' => (bool) Arr::get($data, 'is_active', true),
        ]);
    }

    protected function resolveDosageForm(?string $dosageFormText): string
    {
        $normalized = Str::lower(trim((string) $dosageFormText));

        return collect(DosageForm::cases())
            ->first(fn (DosageForm $case) => $case->value === $normalized)
            ?->value ?? DosageForm::TABLET->value;
    }
}
