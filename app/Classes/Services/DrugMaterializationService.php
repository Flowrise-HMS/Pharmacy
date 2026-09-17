<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Arr;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

/**
 * Pharmacy's "Create from Drug" flow: adopt a reference drug into the
 * formulary with a price, billing flags and optional opening stock. When a
 * clinician already materialized the drug by prescribing it, this prices and
 * promotes that existing row instead of creating a second one.
 */
class DrugMaterializationService
{
    public function __construct(
        protected DrugMedicationResolver $resolver,
        protected MedicationService $medicationService,
        protected MedicationBillingSyncService $billingSyncService,
    ) {}

    public function materialize(Drug $drug, array $data = []): Medication
    {
        $medication = $this->resolver->resolve($drug, $data);

        if (! $medication->wasRecentlyCreated) {
            $this->billingSyncService->ensureBillingService($medication, Arr::only($data, [
                'service_name',
                'service_description',
                'price',
                'requires_prescription',
                'requires_payment_before',
            ]));
        }

        $medication->forceFill(['is_formulary' => true])->save();

        $this->applyInitialStock($medication, $data);

        if (filled(Arr::get($data, 'initial_stock_quantity'))) {
            $drug->increment('times_stocked');
        }

        return $medication->fresh(['service']);
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
