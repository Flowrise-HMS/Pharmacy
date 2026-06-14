<?php

namespace Modules\Pharmacy\Classes\Services;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Modules\Clinical\Classes\Services\MedicationFulfillmentPolicy;
use Modules\Clinical\Classes\Services\MedicationDoseScheduleService;
use Modules\Clinical\Classes\Services\ServiceRequestService;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;

class MedicationOrderService
{
    public function __construct(
        protected ServiceRequestService $serviceRequestService,
        protected PrescriptionScheduleCalculator $scheduleCalculator,
        protected MedicationFulfillmentPolicy $fulfillmentPolicy,
        protected MedicationDoseScheduleService $doseScheduleService,
    ) {}

    /**
     * @param  Patient|array{guest_name:string,guest_phone:string,guest_email?:string,branch_id?:string}  $patientOrGuest
     * @param  array<int,array{service_id:string,variant_id?:string,quantity?:int}>  $items
     */
    public function order(Patient|array $patientOrGuest, array $items, User $orderedBy, ?string $encounterId = null): ?ServiceRequest
    {
        foreach ($items as &$item) {
            if (str_starts_with($item['service_id'], 'drug:')) {
                $drugId = str($item['service_id'])->after('drug:')->toString();
                $drug = Drug::findOrFail($drugId);
                $medication = app(MedicationService::class)->createFromDrug($drug, $item);
                $item['service_id'] = $medication->service_id;
            } elseif (str_starts_with($item['service_id'], 'medication:')) {
                $medId = str($item['service_id'])->after('medication:')->toString();
                $medication = Medication::findOrFail($medId);
                if (! $medication->service_id) {
                    app(MedicationBillingSyncService::class)->ensureBillingService($medication, []);
                    $medication->refresh();
                }
                $item['service_id'] = $medication->service_id;
            }
        }
        unset($item);

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

            $encounter = $encounterId ? Encounter::find($encounterId) : null;

            foreach ($request->items as $index => $item) {
                $itemData = $items[$index] ?? [];
                $this->createPrescriptionDetail($item, $itemData, $encounter);
            }

            return $request;
        });
    }

    /**
     * @param  array<string, mixed>  $itemData
     */
    public function createPrescriptionDetail(RequestItem $item, array $itemData, ?Encounter $encounter): PrescriptionDetail
    {
        $isPrn = filter_var($itemData['prn'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $administerInFacility = filter_var($itemData['administer_in_facility'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $context = isset($itemData['administration_context'])
            ? \Modules\Pharmacy\Enums\AdministrationContext::tryFrom($itemData['administration_context'])
            : null;

        $context ??= $this->fulfillmentPolicy->defaultAdministrationContext(
            $encounter,
            $itemData['route'] ?? null,
            $administerInFacility,
        );

        $courseStartedAt = $encounter?->admitted_at ?? now();
        $schedule = $this->scheduleCalculator->compute([
            'frequency' => $itemData['frequency'] ?? 'qd',
            'duration_days' => $itemData['duration_days'] ?? 1,
            'prn' => $isPrn,
            'course_started_at' => $courseStartedAt,
            'max_administrations' => $itemData['max_administrations'] ?? null,
        ]);

        $detail = PrescriptionDetail::create([
            'request_item_id' => $item->id,
            'dosage' => $itemData['dosage'] ?? null,
            'dose_amount' => $itemData['dose_amount'] ?? null,
            'dose_unit_id' => $itemData['dose_unit_id'] ?? null,
            'frequency' => $itemData['frequency'] ?? null,
            'route' => $itemData['route'] ?? null,
            'duration_days' => $itemData['duration_days'] ?? null,
            'instructions' => $itemData['instructions'] ?? null,
            'prn' => $isPrn,
            'indication' => $itemData['indication'] ?? null,
            'refills' => $itemData['refills'] ?? 0,
            'total_administrations' => $schedule['total_administrations'],
            'administration_context' => $context,
            'course_started_at' => $schedule['course_started_at'],
            'course_end_at' => $schedule['course_end_at'],
            'max_administrations' => $itemData['max_administrations'] ?? null,
            'administer_in_facility_flag' => $administerInFacility,
        ]);

        $this->doseScheduleService->syncNextDoseAt($item);

        $medication = Medication::where('service_id', $item->service_id)->first();
        if ($medication?->billing_unit_id) {
            $item->updateQuietly(['billing_unit_id' => $medication->billing_unit_id]);
        }

        return $detail;
    }

    public function previewSchedule(array $itemData, ?Encounter $encounter = null): array
    {
        $courseStartedAt = $encounter?->admitted_at ?? now();

        return $this->scheduleCalculator->compute([
            'frequency' => $itemData['frequency'] ?? 'qd',
            'duration_days' => $itemData['duration_days'] ?? 1,
            'prn' => filter_var($itemData['prn'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'course_started_at' => $courseStartedAt,
            'max_administrations' => $itemData['max_administrations'] ?? null,
        ]);
    }

    protected function canOrderPrescription(User $user, Service $service): bool
    {
        return $user->can('order_prescription_medication');
    }
}
