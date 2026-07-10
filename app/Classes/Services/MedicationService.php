<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class MedicationService
{
    /**
     * @return list<string>
     */
    public static function billingFieldKeys(): array
    {
        return [
            'price',
            'insurance_price',
            'is_insurance_covered',
            'coverage_type',
        ];
    }

    /**
     * @return list<string>
     */
    public static function stockFieldKeys(): array
    {
        return [
            'stock_branch_id',
            'initial_quantity',
        ];
    }

    /**
     * @return list<string>
     */
    public static function formOnlyFieldKeys(): array
    {
        return [
            'drug_reference_id',
            'drug_id',
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractMedicationAttributes(array $data): array
    {
        return Arr::only($data, (new Medication)->getFillable());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractBillingData(array $data): array
    {
        return Arr::only($data, self::billingFieldKeys());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function extractStockData(array $data): array
    {
        return Arr::only($data, self::stockFieldKeys());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function stripNonMedicationFields(array $data): array
    {
        return Arr::except($data, array_merge(
            self::billingFieldKeys(),
            self::stockFieldKeys(),
            self::formOnlyFieldKeys(),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function normalizeBillingDefaults(array $data): array
    {
        $data['insurance_price'] ??= $data['price'] ?? 0;
        $data['is_insurance_covered'] ??= false;
        $data['coverage_type'] ??= 'none';

        return $data;
    }

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
            'stock_unit_id' => Arr::get($data, 'stock_unit_id'),
            'billing_unit_id' => Arr::get($data, 'billing_unit_id'),
            'dose_unit_id' => Arr::get($data, 'dose_unit_id'),
            'units_per_stock_unit' => Arr::get($data, 'units_per_stock_unit'),
            'controlled_schedule' => Arr::get($data, 'controlled_schedule'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function completeCreate(Medication $medication, array $formData): Medication
    {
        $formData = $this->normalizeBillingDefaults($formData);

        app(MedicationBillingSyncService::class)
            ->ensureBillingService($medication, $this->extractBillingData($formData));

        $this->addInitialStockIfNeeded($medication, $this->extractStockData($formData));

        return $medication->fresh(['service', 'stockItems']);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function completeUpdate(Medication $medication, array $formData): Medication
    {
        $formData = $this->normalizeBillingDefaults($formData);

        app(MedicationBillingSyncService::class)
            ->ensureBillingService($medication, $this->extractBillingData($formData));

        return $medication->fresh(['service']);
    }

    /**
     * @param  array<string, mixed>  $formData
     */
    public function billingAttributesFromService(?Service $service): array
    {
        if (! $service) {
            return [];
        }

        return [
            'price' => $service->price,
            'insurance_price' => $service->insurance_price,
            'is_insurance_covered' => $service->is_insurance_covered,
            'coverage_type' => $service->coverage_type?->value ?? 'none',
        ];
    }

    public function resolveDosageForm(?string $dosageFormText): string
    {
        $normalized = Str::lower(trim((string) $dosageFormText));

        return collect(DosageForm::cases())
            ->first(fn (DosageForm $case) => $case->value === $normalized)
            ?->value ?? DosageForm::TABLET->value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createWithService(array $data): Medication
    {
        $medicationData = $this->extractMedicationAttributes($data);
        $billingData = Arr::except($data, (new Medication)->getFillable());

        return app(MedicationBillingSyncService::class)
            ->createMedicationWithBilling($medicationData, $billingData);
    }

    /**
     * @param  array<string, mixed>  $stockData
     */
    protected function addInitialStockIfNeeded(Medication $medication, array $stockData): void
    {
        $branchId = $stockData['stock_branch_id'] ?? null;
        $quantity = (int) ($stockData['initial_quantity'] ?? 0);

        if (blank($branchId) || $quantity <= 0) {
            return;
        }

        StockItem::firstOrCreate(
            [
                'medication_id' => $medication->id,
                'branch_id' => $branchId,
            ],
            [
                'quantity_on_hand' => 0,
                'reorder_point' => 10,
            ]
        )->increment('quantity_on_hand', $quantity);
    }
}
