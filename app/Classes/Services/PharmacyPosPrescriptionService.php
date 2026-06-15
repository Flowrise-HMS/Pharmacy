<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Data\PharmacyPosPrescriptionLine;
use Modules\Pharmacy\Classes\Support\PrescriptionSigFormatter;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class PharmacyPosPrescriptionService
{
    public function __construct(
        protected MedicationFulfillmentPolicy $policy,
        protected PrescriptionSigFormatter $sigFormatter,
    ) {}

    /**
     * @return Collection<int, PharmacyPosPrescriptionLine>
     */
    public function forPatient(string $patientId, ?string $branchId = null): Collection
    {
        $query = RequestItem::query()
            ->whereHas('prescriptionDetail')
            ->whereHas('serviceRequest', function ($q) use ($patientId, $branchId) {
                $q->where('patient_id', $patientId);
                if ($branchId) {
                    $q->where('branch_id', $branchId);
                }
            })
            ->where(function (Builder $q) {
                $q->whereIn('status', ['pending', 'in_progress'])
                    ->orWhereHas('dispenses', fn (Builder $d) => $d
                        ->where('fulfillment_type', DispenseFulfillmentType::OUTSIDE_PURCHASE->value));
            })
            ->with([
                'service',
                'prescriptionDetail.doseUnit',
                'serviceRequest.orderedBy',
                'serviceRequest.encounter',
                'serviceRequest.patient',
                'dispenses',
            ])
            ->latest();

        return $query->get()->map(fn (RequestItem $item) => $this->buildLine($item, $branchId));
    }

    public function lineFor(RequestItem $item, ?string $branchId = null): PharmacyPosPrescriptionLine
    {
        if (! $item->relationLoaded('service')) {
            $item->loadMissing([
                'service',
                'prescriptionDetail.doseUnit',
                'dispenses',
            ]);
        }

        return $this->buildLine($item, $branchId);
    }

    protected function buildLine(RequestItem $item, ?string $branchId): PharmacyPosPrescriptionLine
    {
        $medication = Medication::query()
            ->where('service_id', $item->service_id)
            ->first();

        $stockQuantity = 0;
        if ($medication && $branchId) {
            $stockQuantity = (int) StockItem::query()
                ->where('medication_id', $medication->id)
                ->where('branch_id', $branchId)
                ->sum('quantity_on_hand');
        }

        $hasCatalogRow = $medication !== null;
        $controlledSubstance = $medication?->controlled_schedule !== null;
        $detail = $item->prescriptionDetail;

        $sigSummary = $detail
            ? $this->sigFormatter->summary($detail)
            : ($item->service?->name ?? '—');

        $hasOutsideSlip = $item->dispenses()
            ->where('fulfillment_type', DispenseFulfillmentType::OUTSIDE_PURCHASE->value)
            ->exists();

        $stockLabel = match (true) {
            $hasOutsideSlip => __('External slip issued'),
            ! $hasCatalogRow => __('Not in catalog'),
            $stockQuantity > 0 => __('In stock')." ({$stockQuantity})",
            default => __('Out of stock'),
        };

        $blockedReason = $this->resolveBlockedReason($item, $controlledSubstance, $hasOutsideSlip);
        $canDispenseInHouse = $blockedReason === null
            && ! $hasOutsideSlip
            && $hasCatalogRow
            && $stockQuantity > 0
            && $this->policy->canDispense($item);

        $canRecordOutsidePurchase = $blockedReason === null
            && ! $hasOutsideSlip
            && ! $controlledSubstance
            && ($stockQuantity <= 0 || ! $hasCatalogRow)
            && $this->policyCanFulfillOutside($item);

        $canReprintExternalSlip = $hasOutsideSlip;

        return new PharmacyPosPrescriptionLine(
            requestItem: $item,
            medication: $medication,
            stockQuantity: $stockQuantity,
            hasMedicationCatalogRow: $hasCatalogRow,
            controlledSubstance: $controlledSubstance,
            canDispenseInHouse: $canDispenseInHouse,
            canRecordOutsidePurchase: $canRecordOutsidePurchase,
            canReprintExternalSlip: $canReprintExternalSlip,
            blockedReason: $blockedReason,
            sigSummary: $sigSummary,
            stockLabel: $stockLabel,
        );
    }

    protected function resolveBlockedReason(RequestItem $item, bool $controlledSubstance, bool $hasOutsideSlip): ?string
    {
        if ($hasOutsideSlip) {
            return null;
        }

        if ($this->hasCompletedInHouseFulfillment($item)) {
            return __('This prescription has already been dispensed.');
        }

        if ($this->policy->requiresPaymentBeforeMarOrDispense($item) && ! $this->policy->isPaidFor($item)) {
            return __('Payment is required before fulfillment.');
        }

        if ($controlledSubstance) {
            return __('Controlled medications cannot be sent to an external pharmacy from POS.');
        }

        return null;
    }

    protected function policyCanFulfillOutside(RequestItem $item): bool
    {
        if ($item->isTerminal()) {
            return false;
        }

        if ($this->policy->requiresPaymentBeforeMarOrDispense($item) && ! $this->policy->isPaidFor($item)) {
            return false;
        }

        return true;
    }

    protected function hasCompletedInHouseFulfillment(RequestItem $item): bool
    {
        $detail = $item->prescriptionDetail;
        if ($detail && $this->policy->requiresMar($detail)) {
            return false;
        }

        return $item->dispenses()
            ->where('fulfillment_type', DispenseFulfillmentType::IN_HOUSE->value)
            ->exists();
    }
}
