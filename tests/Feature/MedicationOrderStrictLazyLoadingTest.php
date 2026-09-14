<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Enums\EncounterType;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Tests\TestCase;

/**
 * Multi-drug orders must survive Model::preventLazyLoading().
 *
 * Eloquent only flags hydrated models for lazy-load prevention when a query
 * returns more than one row, so a single-drug order slipped through while a
 * two-drug order threw "Attempted to lazy load [prescriptionDetail]" from the
 * dose schedule sync — the item was hydrated before its detail existed.
 */
class MedicationOrderStrictLazyLoadingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
        Model::preventLazyLoading(true);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_multi_item_order_succeeds_with_lazy_loading_prevented(): void
    {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::INPATIENT,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        $items = collect(['Amoxicillin', 'Paracetamol'])->map(function (string $name): array {
            $service = Service::factory()->create([
                'name' => $name,
                'requires_prescription' => false,
            ]);
            Medication::factory()->create(['service_id' => $service->id]);

            return [
                'service_id' => $service->id,
                'quantity' => 1,
                'frequency' => MedicationFrequency::BID->value,
                'route' => 'po',
                'duration_days' => 3,
                'dose_amount' => 1,
                'administration_context' => AdministrationContext::IN_FACILITY->value,
            ];
        })->all();

        $request = app(MedicationOrderService::class)->order(
            $patient,
            $items,
            User::factory()->create(),
            $encounter->id,
        );

        $this->assertNotNull($request);
        $this->assertSame(2, PrescriptionDetail::query()
            ->whereIn('request_item_id', $request->items()->pluck('id'))
            ->whereNotNull('next_dose_at')
            ->count());
    }
}
