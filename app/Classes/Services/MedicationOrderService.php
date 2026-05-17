<?php

namespace Modules\Pharmacy\Classes\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\PrescriptionDetail;

class MedicationOrderService
{
    public function __construct(
        protected ServiceRequestService $serviceRequestService
    ) {}

    /**
     * @param  Patient|array{guest_name:string,guest_phone:string,guest_email?:string,branch_id?:string}  $patientOrGuest
     * @param  array<int,array{service_id:string,variant_id?:string,quantity?:int}>  $items
     */
    public function order(Patient|array $patientOrGuest, array $items, User $orderedBy, ?string $encounterId = null): ?ServiceRequest
    {
        foreach ($items as $item) {
            $service = Service::findOrFail($item['service_id']);

            if ($service->requires_prescription && ! $this->canOrderPrescription($orderedBy, $service)) {
                Notification::make()
                    ->title('You cannot order prescription required medications.')
                    ->danger()
                    ->send();
                if (config('app.env') != 'product ' && config('app.env') != 'production') {
                    throw new UnauthorizedMedicationOrderException('This user cannot order prescription-required items.');
                }

                return null;
            }
        }

        return DB::transaction(function () use ($patientOrGuest, $items, $orderedBy, $encounterId) {
            $request = $patientOrGuest instanceof Patient
                ? $this->serviceRequestService->createForPatient(
                    patient: $patientOrGuest,
                    serviceIds: $items,
                    encounterId: $encounterId,
                    orderedBy: $orderedBy->id
                )
                : $this->serviceRequestService->createForGuest(
                    guestName: $patientOrGuest['guest_name'],
                    guestPhone: $patientOrGuest['guest_phone'],
                    serviceIds: $items,
                    guestEmail: $patientOrGuest['guest_email'] ?? null,
                    branchId: $patientOrGuest['branch_id'] ?? null,
                    orderedBy: $orderedBy->id
                );

            foreach ($request->items as $index => $item) {
                $itemData = $items[$index] ?? [];

                $prescriptionFields = [
                    'dosage', 'frequency', 'route', 'duration_days',
                    'instructions', 'prn', 'indication', 'refills',
                ];

                if (! empty(array_intersect_key(array_flip($prescriptionFields), $itemData))) {
                    $totalAdministrations = null;
                    if (! empty($itemData['frequency']) && ! empty($itemData['duration_days'])) {
                        $freq = MedicationFrequency::tryFrom($itemData['frequency']);
                        $timesPerDay = $freq?->timesPerDay();
                        $isPrn = filter_var($itemData['prn'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        if ($timesPerDay && ! $isPrn) {
                            $totalAdministrations = $timesPerDay * (int) $itemData['duration_days'];
                        }
                    }

                    PrescriptionDetail::create([
                        'request_item_id' => $item->id,
                        'dosage' => $itemData['dosage'] ?? null,
                        'frequency' => $itemData['frequency'] ?? null,
                        'route' => $itemData['route'] ?? null,
                        'duration_days' => $itemData['duration_days'] ?? null,
                        'instructions' => $itemData['instructions'] ?? null,
                        'prn' => filter_var($itemData['prn'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'indication' => $itemData['indication'] ?? null,
                        'refills' => $itemData['refills'] ?? 0,
                        'total_administrations' => $totalAdministrations,
                    ]);
                }
            }

            return $request;
        });
    }

    protected function canOrderPrescription(User $user, Service $service): bool
    {
        return $user->can('order_prescription_medication');
    }
}
