<?php

namespace Modules\Pharmacy\Classes\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Data\PrescriptionSlipLine;
use Modules\Pharmacy\Classes\Support\PrescriptionSigFormatter;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\Dispense;

class PrescriptionSlipPresenter
{
    public function __construct(
        protected PrescriptionSigFormatter $sigFormatter,
    ) {}

    /**
     * @param  array<int, string>  $itemIds
     * @return array{
     *     lines: Collection<int, PrescriptionSlipLine>,
     *     patient: mixed,
     *     branch: mixed,
     *     issuedAt: \Carbon\CarbonInterface,
     *     pharmacist: User|null,
     *     sharedNotes: string|null,
     * }
     */
    public function present(array $itemIds, User $user, bool $requireOutsideDispense = true): array
    {
        $this->assertPharmacyStaff($user);

        $itemIds = array_values(array_unique(array_filter($itemIds)));
        abort_if($itemIds === [], 404);

        $items = RequestItem::query()
            ->whereIn('id', $itemIds)
            ->with([
                'service' => fn ($query) => $query->withoutGlobalScope('branch'),
                'prescriptionDetail.doseUnit',
                'serviceRequest.patient',
                'serviceRequest.orderedBy',
                'serviceRequest.branch.organization',
                'dispenses.dispensedBy',
            ])
            ->get()
            ->sortBy(fn (RequestItem $item) => array_search($item->id, $itemIds, true))
            ->values();

        abort_if($items->count() !== count($itemIds), 404);

        foreach ($items as $item) {
            abort_unless($item->prescriptionDetail !== null, 404);
        }

        $firstRequest = $items->first()->serviceRequest;
        abort_unless($firstRequest !== null, 404);

        $patientId = $firstRequest->patient_id;
        $branchId = $firstRequest->branch_id;

        foreach ($items as $item) {
            abort_unless(
                $item->serviceRequest?->patient_id === $patientId
                && $item->serviceRequest?->branch_id === $branchId,
                422,
                __('All prescriptions must belong to the same patient and branch.'),
            );
        }

        $lines = $items->map(function (RequestItem $item): PrescriptionSlipLine {
            $detail = $item->prescriptionDetail;

            /** @var Dispense|null $outsideDispense */
            $outsideDispense = $item->dispenses
                ->where('fulfillment_type', DispenseFulfillmentType::OUTSIDE_PURCHASE->value)
                ->sortByDesc('dispensed_at')
                ->first();

            return new PrescriptionSlipLine(
                item: $item,
                sigLine: $this->sigFormatter->sigLine($detail),
                sigRows: $this->sigFormatter->rows($detail),
                prescriber: $item->serviceRequest?->orderedBy,
                outsideDispense: $outsideDispense,
            );
        });

        if ($requireOutsideDispense) {
            $missing = $lines->filter(fn (PrescriptionSlipLine $line) => $line->outsideDispense === null);
            abort_if(
                $missing->isNotEmpty(),
                422,
                __('All selected prescriptions must have an external slip issued before printing.'),
            );
        }

        $issuedAt = $lines
            ->map(fn (PrescriptionSlipLine $line) => $line->outsideDispense?->dispensed_at)
            ->filter()
            ->max() ?? now();

        $pharmacist = $lines
            ->map(fn (PrescriptionSlipLine $line) => $line->outsideDispense?->dispensedBy)
            ->filter()
            ->first();

        $notes = $lines
            ->map(fn (PrescriptionSlipLine $line) => $line->outsideDispense?->notes)
            ->filter(fn (?string $note) => filled($note))
            ->unique()
            ->values();

        $sharedNotes = $notes->count() === 1 ? $notes->first() : null;

        return [
            'lines' => $lines,
            'patient' => $firstRequest->patient,
            'branch' => $firstRequest->branch,
            'issuedAt' => $issuedAt,
            'pharmacist' => $pharmacist,
            'sharedNotes' => $sharedNotes,
        ];
    }

    protected function assertPharmacyStaff(User $user): void
    {
        if (! $user->hasAnyRole(['pharmacist', 'pharmacy_technician'])) {
            throw new UnauthorizedMedicationOrderException('Only pharmacy staff can view prescription slips.');
        }
    }
}
