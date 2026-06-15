<?php

namespace Modules\Pharmacy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Classes\Support\PrescriptionSigFormatter;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Models\Dispense;

class PrescriptionSlipController extends Controller
{
    public function show(Request $request, RequestItem $requestItem, PrescriptionSigFormatter $sigFormatter): View
    {
        $requestItem->load([
            'service',
            'prescriptionDetail.doseUnit',
            'serviceRequest.patient',
            'serviceRequest.orderedBy',
            'serviceRequest.encounter',
            'serviceRequest.branch.organization',
        ]);

        abort_unless($requestItem->prescriptionDetail !== null, 404);

        $detail = $requestItem->prescriptionDetail;

        /** @var Dispense|null $outsideDispense */
        $outsideDispense = $requestItem->dispenses()
            ->where('fulfillment_type', DispenseFulfillmentType::OUTSIDE_PURCHASE->value)
            ->with('dispensedBy')
            ->latest('dispensed_at')
            ->first();

        $branch = $requestItem->serviceRequest?->branch;
        $patient = $requestItem->serviceRequest?->patient;

        return view('pharmacy::prescription-slip', [
            'item' => $requestItem,
            'detail' => $detail,
            'patient' => $patient,
            'prescriber' => $requestItem->serviceRequest?->orderedBy,
            'encounter' => $requestItem->serviceRequest?->encounter,
            'branch' => $branch,
            'organization' => $branch?->organization,
            'outsideDispense' => $outsideDispense,
            'sigLine' => $sigFormatter->sigLine($detail),
            'sigRows' => $sigFormatter->rows($detail),
            'issuedAt' => $outsideDispense?->dispensed_at ?? $requestItem->serviceRequest?->created_at ?? now(),
        ]);
    }
}
