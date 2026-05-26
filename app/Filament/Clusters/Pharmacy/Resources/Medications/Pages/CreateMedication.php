<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Modules\Pharmacy\Classes\Services\MedicationBillingSyncService;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;

class CreateMedication extends CreateRecord
{
    protected static string $resource = MedicationResource::class;

    private array $billingFormData = [];

    private array $stockData = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['insurance_price'] ??= 0;
        $data['is_insurance_covered'] ??= false;
        $data['coverage_type'] ??= 'none';

        $this->billingFormData = Arr::only($data, [
            'price', 'insurance_price', 'is_insurance_covered', 'coverage_type',
        ]);

        $this->stockData = Arr::only($data, [
            'stock_branch_id', 'initial_quantity',
        ]);

        return Arr::except($data, [
            'price', 'insurance_price', 'is_insurance_covered', 'coverage_type',
            'stock_branch_id', 'initial_quantity',
        ]);
    }

    protected function afterCreate(): void
    {
        app(MedicationBillingSyncService::class)
            ->ensureBillingService($this->record, $this->billingFormData);

        if (filled($this->stockData['stock_branch_id'] ?? null) && ($this->stockData['initial_quantity'] ?? 0) > 0) {
            $this->record->stockItems()->create([
                'branch_id' => $this->stockData['stock_branch_id'],
                'quantity_on_hand' => (int) $this->stockData['initial_quantity'],
            ]);
        }
    }
}
