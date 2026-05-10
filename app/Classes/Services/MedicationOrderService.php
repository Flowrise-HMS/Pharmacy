<?php

namespace Modules\Pharmacy\Classes\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;

class MedicationOrderService
{
    public function __construct(
        protected ServiceRequestService $serviceRequestService
    ) {}

    /**
     * @param  Patient|array{guest_name:string,guest_phone:string,guest_email?:string,branch_id?:string}  $patientOrGuest
     * @param  array<int,array{service_id:string,variant_id?:string,quantity?:int}>  $items
     */
    public function order(Patient|array $patientOrGuest, array $items, User $orderedBy, ?string $encounterId = null): ServiceRequest
    {
        foreach ($items as $item) {
            $service = Service::findOrFail($item['service_id']);

            if ($service->requires_prescription && ! $this->canOrderPrescription($orderedBy, $service)) {
                throw new UnauthorizedMedicationOrderException('This user cannot order prescription-required items.');
            }
        }

        return DB::transaction(function () use ($patientOrGuest, $items, $orderedBy, $encounterId) {
            if ($patientOrGuest instanceof Patient) {
                return $this->serviceRequestService->createForPatient(
                    patient: $patientOrGuest,
                    serviceIds: $items,
                    encounterId: $encounterId,
                    orderedBy: $orderedBy->id
                );
            }

            return $this->serviceRequestService->createForGuest(
                guestName: $patientOrGuest['guest_name'],
                guestPhone: $patientOrGuest['guest_phone'],
                serviceIds: $items,
                guestEmail: $patientOrGuest['guest_email'] ?? null,
                branchId: $patientOrGuest['branch_id'] ?? null,
                orderedBy: $orderedBy->id
            );
        });
    }

    protected function canOrderPrescription(User $user, Service $service): bool
    {
        $allowedRoles = $service->roles;

        if ($allowedRoles->isEmpty()) {
            return false;
        }

        return $user->hasAnyRole($allowedRoles->pluck('name')->toArray());
    }
}
