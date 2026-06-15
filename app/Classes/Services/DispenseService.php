<?php

namespace Modules\Pharmacy\Classes\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\Task;
use Modules\Core\Contracts\StockProviderContract;
use Modules\Pharmacy\Classes\Support\MedicationQuantityValidator;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Exceptions\DuplicateDispenseException;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;

class DispenseService
{
    public function __construct(
        protected StockProviderContract $stockProvider,
        protected MedicationFulfillmentPolicy $policy,
    ) {}

    /**
     * @param  array{medication_id:string,quantity:int,batch_number?:string,expiry_date?:string,notes?:string}  $data
     */
    public function dispense(RequestItem $item, array $data, User $dispensedBy): Dispense
    {
        $service = $item->service;
        if (! $service) {
            throw new UnauthorizedMedicationOrderException('Service is required to dispense an item.');
        }

        $this->assertPharmacyStaff($dispensedBy);
        $this->assertPaymentGate($item);

        $detail = $item->prescriptionDetail;
        $isSupplyOnly = $detail !== null && $this->policy->requiresMar($detail);

        if (! $isSupplyOnly && $this->hasCompletedFulfillmentDispense($item)) {
            throw new DuplicateDispenseException('This order line has already been dispensed.');
        }

        if (! empty($data['expiry_date'] ?? null)) {
            $expiry = Carbon::parse((string) $data['expiry_date'])->startOfDay();
            if ($expiry->lt(now()->startOfDay())) {
                throw new UnauthorizedMedicationOrderException('Expiry date cannot be in the past.');
            }
        }

        $medication = Medication::query()->findOrFail($data['medication_id']);
        $branchId = (string) $item->serviceRequest->branch_id;
        $qty = (int) $data['quantity'];

        return DB::transaction(function () use ($item, $data, $dispensedBy, $medication, $branchId, $qty, $isSupplyOnly) {
            if (! $isSupplyOnly && Dispense::query()->where('request_item_id', $item->id)->lockForUpdate()->exists()) {
                throw new DuplicateDispenseException('This order line has already been dispensed.');
            }

            app(MedicationQuantityValidator::class)->assertStockQuantity($medication, $qty);

            if (! $this->stockProvider->hasStock($branchId, $medication->id, $qty)) {
                throw new UnauthorizedMedicationOrderException('Insufficient stock to dispense this item.');
            }

            $this->stockProvider->decrement($branchId, $medication->id, $qty, 'dispense');

            $unitId = $medication->stock_unit_id ?? $medication->billing_unit_id;
            $fulfillmentType = $isSupplyOnly
                ? DispenseFulfillmentType::SUPPLY_ONLY
                : DispenseFulfillmentType::IN_HOUSE;

            $dispense = Dispense::query()->create([
                'request_item_id' => $item->id,
                'medication_id' => $medication->id,
                'dispensed_by' => $dispensedBy->id,
                'quantity' => $qty,
                'unit_id' => $unitId,
                'batch_number' => $data['batch_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'dispensed_at' => now(),
                'fulfillment_type' => $fulfillmentType,
            ]);

            if ($isSupplyOnly) {
                if ($item->isPending()) {
                    $item->markAsInProgress();
                }

                return $dispense;
            }

            $this->completeFulfillmentTask($item, $dispensedBy, $dispense, $data['notes'] ?? null);

            return $dispense;
        });
    }

    /**
     * @param  Collection<int, RequestItem>  $items
     * @return Collection<int, Dispense>
     */
    public function recordOutsidePurchaseBatch(Collection $items, User $dispensedBy, ?string $sharedNotes = null): Collection
    {
        if ($items->isEmpty()) {
            throw new UnauthorizedMedicationOrderException('No prescriptions selected for outside purchase.');
        }

        $this->assertPharmacyStaff($dispensedBy);

        return DB::transaction(function () use ($items, $dispensedBy, $sharedNotes) {
            return $items->map(fn (RequestItem $item) => $this->recordOutsidePurchaseWithinTransaction(
                $item,
                $dispensedBy,
                $sharedNotes,
            ));
        });
    }

    protected function recordOutsidePurchaseWithinTransaction(
        RequestItem $item,
        User $dispensedBy,
        ?string $notes = null,
    ): Dispense {
        if (! $item->service) {
            throw new UnauthorizedMedicationOrderException('Service is required to record outside purchase.');
        }

        $this->assertPaymentGate($item);

        if ($this->policy->isControlledMedication($item)) {
            throw new UnauthorizedMedicationOrderException('Controlled medications cannot be fulfilled via outside purchase.');
        }

        if ($item->dispenses()->where('fulfillment_type', DispenseFulfillmentType::OUTSIDE_PURCHASE)->exists()) {
            throw new DuplicateDispenseException('Outside purchase has already been recorded for this order line.');
        }

        if ($this->hasCompletedFulfillmentDispense($item)) {
            throw new DuplicateDispenseException('This order line has already been fulfilled.');
        }

        $medication = Medication::query()->where('service_id', $item->service_id)->first();

        $dispense = Dispense::query()->create([
            'request_item_id' => $item->id,
            'medication_id' => $medication?->id,
            'dispensed_by' => $dispensedBy->id,
            'quantity' => 0,
            'unit_id' => $medication?->stock_unit_id ?? $medication?->billing_unit_id,
            'notes' => $notes,
            'dispensed_at' => now(),
            'fulfillment_type' => DispenseFulfillmentType::OUTSIDE_PURCHASE,
        ]);

        $this->completeFulfillmentTask($item, $dispensedBy, $dispense, $notes);

        $item->refresh();
        if (! $item->isTerminal()) {
            $item->markAsFulfilled($dispensedBy->id);
        }

        return $dispense;
    }

    public function recordOutsidePurchase(RequestItem $item, User $dispensedBy, ?string $notes = null): Dispense
    {
        $this->assertPharmacyStaff($dispensedBy);

        return DB::transaction(fn () => $this->recordOutsidePurchaseWithinTransaction($item, $dispensedBy, $notes));
    }

    protected function assertPharmacyStaff(User $dispensedBy): void
    {
        if (! $dispensedBy->hasAnyRole(['pharmacist', 'pharmacy_technician'])) {
            throw new UnauthorizedMedicationOrderException('Only pharmacy staff can dispense.');
        }
    }

    protected function assertPaymentGate(RequestItem $item): void
    {
        if ($this->policy->requiresPaymentBeforeMarOrDispense($item) && ! $this->policy->isPaidFor($item)) {
            throw new UnauthorizedMedicationOrderException('Payment is required before dispensing this item.');
        }
    }

    protected function hasCompletedFulfillmentDispense(RequestItem $item): bool
    {
        if ($item->dispenses()->where('fulfillment_type', DispenseFulfillmentType::OUTSIDE_PURCHASE)->exists()) {
            return true;
        }

        $detail = $item->prescriptionDetail;
        $isSupplyOnly = $detail !== null && $this->policy->requiresMar($detail);

        if ($isSupplyOnly) {
            return false;
        }

        return Dispense::query()
            ->where('request_item_id', $item->id)
            ->where('fulfillment_type', DispenseFulfillmentType::IN_HOUSE)
            ->exists();
    }

    protected function completeFulfillmentTask(
        RequestItem $item,
        User $dispensedBy,
        Dispense $dispense,
        ?string $notes,
    ): void {
        $task = Task::query()->firstOrCreate(
            ['request_item_id' => $item->id],
            [
                'status' => 'pending',
                'performed_by' => $dispensedBy->id,
            ]
        );

        if (! $task->started_at) {
            $task->start($dispensedBy->id);
        }

        $task->complete(TaskOutcome::COMPLETED, [
            'dispense_id' => $dispense->id,
            'fulfillment_type' => $dispense->fulfillment_type?->value,
        ], $notes);
    }
}
