<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
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
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

/**
 * A multi-drug order must attach each drug's own dosage, frequency, route and
 * duration to that drug — never to a sibling line.
 *
 * ServiceRequest::items() is an unordered HasMany and request_items has no
 * sequence column, so the persisted rows do not necessarily come back in the
 * order they were submitted. Pairing them to the input by array position
 * therefore silently mis-assigned prescription instructions across drugs.
 */
class MedicationOrderItemPairingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
    }

    public function test_multi_item_order_attaches_each_sig_to_its_own_drug(): void
    {
        [$patient, $encounter] = $this->seedPatientContext();

        $amoxicillin = $this->seedMedicationService('Amoxicillin');
        $paracetamol = $this->seedMedicationService('Paracetamol');
        $metformin = $this->seedMedicationService('Metformin');

        $items = [
            [
                'service_id' => $amoxicillin->id,
                'quantity' => 1,
                'frequency' => MedicationFrequency::TID->value,
                'route' => 'po',
                'duration_days' => 5,
                'instructions' => 'Amoxicillin - after meals',
            ],
            [
                'service_id' => $paracetamol->id,
                'quantity' => 2,
                'frequency' => MedicationFrequency::QD->value,
                'route' => 'iv',
                'duration_days' => 1,
                'instructions' => 'Paracetamol - for fever',
            ],
            [
                'service_id' => $metformin->id,
                'quantity' => 3,
                'frequency' => MedicationFrequency::BID->value,
                'route' => 'sc',
                'duration_days' => 30,
                'instructions' => 'Metformin - with breakfast',
            ],
        ];

        $request = app(MedicationOrderService::class)->order(
            $patient,
            $items,
            $this->prescribingUser(),
            $encounter->id,
        );

        $this->assertNotNull($request);

        foreach ($items as $expected) {
            $persisted = RequestItem::query()
                ->where('service_request_id', $request->id)
                ->where('service_id', $expected['service_id'])
                ->firstOrFail();

            $detail = $persisted->prescriptionDetail()->firstOrFail();

            $this->assertSame(
                $expected['instructions'],
                $detail->instructions,
                "Prescription instructions were attached to the wrong drug ({$expected['instructions']})."
            );
            $this->assertSame($expected['frequency'], $detail->frequency);
            $this->assertSame($expected['route'], $detail->route);
            $this->assertSame($expected['duration_days'], $detail->duration_days);
        }
    }

    /**
     * Deterministic regression guard: pairing must survive the persisted items
     * arriving in a different order from the submitted rows.
     */
    public function test_pairing_is_independent_of_persisted_item_order(): void
    {
        [$patient, $encounter] = $this->seedPatientContext();

        $first = $this->seedMedicationService('Ceftriaxone');
        $second = $this->seedMedicationService('Ibuprofen');

        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'branch_id' => $patient->branch_id,
        ]);

        $firstItem = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $first->id,
        ]);
        $secondItem = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $second->id,
        ]);

        // Force the worst case: the relation hands back the reverse of the
        // submitted order, which is what index pairing could not survive.
        $request->setRelation('items', collect([$secondItem, $firstItem]));

        $input = [
            ['service_id' => $first->id, 'instructions' => 'Ceftriaxone sig'],
            ['service_id' => $second->id, 'instructions' => 'Ibuprofen sig'],
        ];

        $paired = $this->pair($input, $request);

        $this->assertCount(2, $paired);
        $this->assertSame($firstItem->id, $paired[0][0]->id);
        $this->assertSame('Ceftriaxone sig', $paired[0][1]['instructions']);
        $this->assertSame($secondItem->id, $paired[1][0]->id);
        $this->assertSame('Ibuprofen sig', $paired[1][1]['instructions']);
    }

    public function test_repeated_service_pairs_one_to_one_in_submitted_order(): void
    {
        [$patient, $encounter] = $this->seedPatientContext();

        $service = $this->seedMedicationService('Insulin');

        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'branch_id' => $patient->branch_id,
        ]);

        $morning = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
        ]);
        $evening = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
        ]);

        $request->setRelation('items', collect([$morning, $evening]));

        $paired = $this->pair([
            ['service_id' => $service->id, 'instructions' => 'morning dose'],
            ['service_id' => $service->id, 'instructions' => 'evening dose'],
        ], $request);

        $this->assertCount(2, $paired);
        $this->assertNotSame($paired[0][0]->id, $paired[1][0]->id);
        $this->assertSame('morning dose', $paired[0][1]['instructions']);
        $this->assertSame('evening dose', $paired[1][1]['instructions']);
    }

    public function test_unmatched_service_fails_closed(): void
    {
        [$patient, $encounter] = $this->seedPatientContext();

        $ordered = $this->seedMedicationService('Ordered');
        $missing = $this->seedMedicationService('Missing');

        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'branch_id' => $patient->branch_id,
        ]);
        $item = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $ordered->id,
        ]);
        $request->setRelation('items', collect([$item]));

        $this->expectException(\RuntimeException::class);

        $this->pair([
            ['service_id' => $ordered->id],
            ['service_id' => $missing->id],
        ], $request);
    }

    /**
     * @param  array<int,array<string,mixed>>  $items
     * @return list<array{0:RequestItem,1:array<string,mixed>}>
     */
    protected function pair(array $items, ServiceRequest $request): array
    {
        $service = app(MedicationOrderService::class);
        $method = new \ReflectionMethod($service, 'pairInputToPersistedItems');
        $method->setAccessible(true);

        return $method->invoke($service, $items, $request);
    }

    /**
     * @return array{Patient, Encounter}
     */
    protected function seedPatientContext(): array
    {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create(['branch_id' => $branch->id]);
        $encounter = Encounter::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'type' => EncounterType::INPATIENT,
            'status' => EncounterStatus::IN_PROGRESS,
        ]);

        return [$patient, $encounter];
    }

    protected function seedMedicationService(string $name): Service
    {
        $service = Service::factory()->create([
            'name' => $name,
            'requires_prescription' => false,
        ]);

        Medication::factory()->create(['service_id' => $service->id]);

        return $service;
    }

    protected function prescribingUser(): User
    {
        return User::factory()->create();
    }
}
