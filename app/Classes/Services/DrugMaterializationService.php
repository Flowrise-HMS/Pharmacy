<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Arr;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class DrugMaterializationService
{
    public function __construct(
        protected MedicationService $medicationService
    ) {}

    public function materialize(Drug $drug, array $data = []): Medication
    {
        $medication = $this->findExistingMedication($drug)
            ?? $this->medicationService->createFromDrug($drug, $data);

        $this->applyInitialStock($medication, $data);

        if (filled(Arr::get($data, 'initial_stock_quantity'))) {
            $drug->increment('times_stocked');
        }

        return $medication->fresh(['service']);
    }

    protected function findExistingMedication(Drug $drug): ?Medication
    {
        return Medication::query()
            ->when(filled($drug->rxnorm_code), fn ($query) => $query->orWhere('rxnorm_code', $drug->rxnorm_code))
            ->when(filled($drug->ndc_code), fn ($query) => $query->orWhere('ndc_code', $drug->ndc_code))
            ->first();
    }

    protected function applyInitialStock(Medication $medication, array $data): void
    {
        $quantity = (int) Arr::get($data, 'initial_stock_quantity', 0);
        $branchId = Arr::get($data, 'branch_id');

        if ($quantity <= 0 || blank($branchId)) {
            return;
        }

        StockItem::query()->firstOrCreate(
            [
                'medication_id' => $medication->id,
                'branch_id' => $branchId,
            ],
            [
                'quantity_on_hand' => 0,
                'reorder_point' => (int) Arr::get($data, 'reorder_point', 10),
            ]
        )->increment('quantity_on_hand', $quantity);
    }
}
