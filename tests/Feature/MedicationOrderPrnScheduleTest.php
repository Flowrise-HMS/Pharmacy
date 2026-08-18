<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Tests\TestCase;

class MedicationOrderPrnScheduleTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
    }

    public function test_normalize_prn_and_frequency_makes_prn_and_scheduled_frequency_exclusive(): void
    {
        $service = app(MedicationOrderService::class);

        $prnFromCheckbox = $service->normalizePrnAndFrequency([
            'frequency' => MedicationFrequency::QD->value,
            'prn' => true,
        ]);

        $this->assertTrue($prnFromCheckbox['prn']);
        $this->assertSame(MedicationFrequency::PRN->value, $prnFromCheckbox['frequency']);

        $scheduled = $service->normalizePrnAndFrequency([
            'frequency' => MedicationFrequency::BID->value,
            'prn' => false,
        ]);

        $this->assertFalse($scheduled['prn']);
        $this->assertSame(MedicationFrequency::BID->value, $scheduled['frequency']);
    }

    public function test_create_prescription_detail_does_not_store_qd_with_prn(): void
    {
        [$item, $encounter] = $this->seedRequestItem();

        $detail = app(MedicationOrderService::class)->createPrescriptionDetail($item, [
            'frequency' => MedicationFrequency::QD->value,
            'duration_days' => 2,
            'route' => 'po',
            'dose_amount' => 1,
            'prn' => true,
            'administration_context' => AdministrationContext::IN_FACILITY->value,
        ], $encounter);

        $this->assertTrue($detail->prn);
        $this->assertSame(MedicationFrequency::PRN->value, $detail->frequency);
        $this->assertNull($detail->total_administrations);
    }

    public function test_create_prescription_detail_accepts_administration_context_enum(): void
    {
        [$item, $encounter] = $this->seedRequestItem();

        $detail = app(MedicationOrderService::class)->createPrescriptionDetail($item, [
            'frequency' => MedicationFrequency::QD->value,
            'duration_days' => 1,
            'route' => 'po',
            'dose_amount' => 1,
            'administration_context' => AdministrationContext::IN_FACILITY,
        ], $encounter);

        $this->assertSame(AdministrationContext::IN_FACILITY, $detail->administration_context);
    }

    public function test_convert_mistaken_prn_to_scheduled_course_recomputes_total(): void
    {
        [$item] = $this->seedRequestItem();

        $detail = PrescriptionDetail::create([
            'request_item_id' => $item->id,
            'frequency' => MedicationFrequency::QD->value,
            'duration_days' => 2,
            'route' => 'po',
            'dose_amount' => 1,
            'prn' => true,
            'administration_context' => AdministrationContext::IN_FACILITY,
            'course_started_at' => now(),
            'course_end_at' => now()->addDays(2),
            'total_administrations' => null,
        ]);

        $repaired = app(MedicationOrderService::class)->convertMistakenPrnToScheduledCourse($detail);

        $this->assertFalse($repaired->prn);
        $this->assertSame(MedicationFrequency::QD->value, $repaired->frequency);
        $this->assertSame(2, $repaired->total_administrations);
    }

    /**
     * @return array{RequestItem, Encounter}
     */
    protected function seedRequestItem(): array
    {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create();
        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::INPATIENT,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);
        $service = Service::factory()->create();
        Medication::factory()->create(['service_id' => $service->id]);
        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'branch_id' => $branch->id,
        ]);
        $item = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
            'status' => 'pending',
        ]);

        return [$item, $encounter];
    }
}
