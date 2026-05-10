<?php

namespace Modules\Pharmacy\Classes\Services;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Enums\TaskOutcome;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\Task;
use Modules\Core\Contracts\StockProviderContract;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;

class DispenseService
{
    public function __construct(
        protected StockProviderContract $stockProvider
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

        if (! $dispensedBy->hasAnyRole(['pharmacist', 'pharmacy_technician'])) {
            throw new UnauthorizedMedicationOrderException('Only pharmacy staff can dispense.');
        }

        if ($service->requires_payment_before && ! $this->isPaidFor($item)) {
            throw new UnauthorizedMedicationOrderException('Payment is required before dispensing this item.');
        }

        $medication = Medication::query()->findOrFail($data['medication_id']);
        $branchId = (string) $item->serviceRequest->branch_id;
        $qty = (int) $data['quantity'];

        return DB::transaction(function () use ($item, $data, $dispensedBy, $medication, $branchId, $qty) {
            $this->stockProvider->decrement($branchId, $medication->id, $qty, 'dispense');

            $dispense = Dispense::query()->create([
                'request_item_id' => $item->id,
                'medication_id' => $medication->id,
                'dispensed_by' => $dispensedBy->id,
                'quantity' => $qty,
                'batch_number' => $data['batch_number'] ?? null,
                'expiry_date' => $data['expiry_date'] ?? null,
                'notes' => $data['notes'] ?? null,
                'dispensed_at' => now(),
            ]);

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

            $task->complete(TaskOutcome::COMPLETED, ['dispense_id' => $dispense->id], $data['notes'] ?? null);

            return $dispense;
        });
    }

    protected function isPaidFor(RequestItem $item): bool
    {
        if (! class_exists(InvoiceLine::class) || ! Schema::hasTable('invoice_lines')) {
            return false;
        }

        return InvoiceLine::query()
            ->where('billable_type', $item::class)
            ->where('billable_id', $item->id)
            ->where('line_status', InvoiceLineStatus::Paid)
            ->exists();
    }
}

