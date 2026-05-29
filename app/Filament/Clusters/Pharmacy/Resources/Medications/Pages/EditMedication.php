<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Arr;
use Modules\Pharmacy\Classes\Services\MedicationBillingSyncService;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;

class EditMedication extends EditRecord
{
    protected static string $resource = MedicationResource::class;

    private array $billingFormData = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $service = $this->record->service;

        if ($service) {
            $data['price'] = $service->price;
            $data['insurance_price'] = $service->insurance_price;
            $data['is_insurance_covered'] = $service->is_insurance_covered;
            $data['coverage_type'] = $service->coverage_type?->value ?? 'none';
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['insurance_price'] ??= $this->record->service?->insurance_price ?? 0;
        $data['is_insurance_covered'] ??= $this->record->service?->is_insurance_covered ?? false;
        $data['coverage_type'] ??= $this->record->service?->coverage_type?->value ?? 'none';

        $this->billingFormData = Arr::only($data, [
            'price', 'insurance_price', 'is_insurance_covered', 'coverage_type',
        ]);

        return Arr::except($data, [
            'price', 'insurance_price', 'is_insurance_covered', 'coverage_type',
        ]);
    }

    protected function afterSave(): void
    {
        app(MedicationBillingSyncService::class)->ensureBillingService($this->record, $this->billingFormData);
        app(MedicationBillingSyncService::class)->syncBilling($this->record, $this->billingFormData);
    }
}
