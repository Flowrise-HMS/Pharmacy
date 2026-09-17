<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Classes\Services\DrugMaterializationService;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class DrugBackedMedicationOrderingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    public function test_a_drug_reference_resolves_to_a_service_id_before_order_creation(): void
    {
        $branch = Branch::factory()->default()->create();
        $user = User::factory()->create();
        $drug = Drug::factory()->create([
            'source_provider' => 'rxnorm',
            'source_identifier' => '12345',
            'generic_name' => 'Ibuprofen',
            'display_name' => 'Ibuprofen 200 MG Tablet',
            'strength_text' => '200 MG',
            'dosage_form_text' => 'tablet',
            'rxnorm_code' => '12345',
        ]);

        $medication = app(DrugMaterializationService::class)->materialize($drug, [
            'service_name' => 'Ibuprofen 200 MG Tablet',
            'price' => 5,
            'requires_prescription' => false,
            'branch_id' => $branch->id,
            'initial_stock_quantity' => 15,
        ]);

        $request = app(MedicationOrderService::class)->order(
            patientOrGuest: [
                'guest_name' => 'Walk In',
                'guest_phone' => '233000000000',
                'branch_id' => $branch->id,
            ],
            items: [
                ['service_id' => $medication->service_id, 'quantity' => 2],
            ],
            orderedBy: $user
        );

        $this->assertDatabaseHas('request_items', [
            'service_request_id' => $request->id,
            'service_id' => $medication->service_id,
            'quantity' => 2,
        ]);
    }

    public function test_ordering_the_same_reference_drug_twice_reuses_one_medication(): void
    {
        $branch = Branch::factory()->default()->create();
        $prescriber = $this->prescriber();
        $drug = Drug::factory()->create([
            'rxnorm_code' => '67890',
            'ndc_code' => null,
            'times_prescribed' => 0,
        ]);

        $orderService = app(MedicationOrderService::class);
        $guest = ['guest_name' => 'Walk In', 'guest_phone' => '233000000000', 'branch_id' => $branch->id];

        $first = $orderService->order($guest, [['service_id' => 'drug:'.$drug->id, 'quantity' => 1]], $prescriber);
        $second = $orderService->order($guest, [['service_id' => 'drug:'.$drug->id, 'quantity' => 1]], $prescriber);

        $medications = Medication::query()->where('drug_id', $drug->id)->get();

        $this->assertCount(1, $medications);
        $this->assertFalse($medications->first()->is_formulary);
        $this->assertSame(1, Service::query()->where('id', $medications->first()->service_id)->count());
        $this->assertSame(
            $first->items()->value('service_id'),
            $second->items()->value('service_id'),
        );
        $this->assertSame(2, $drug->fresh()->times_prescribed);
    }

    public function test_unauthorized_reference_drug_order_creates_no_catalog_rows(): void
    {
        $branch = Branch::factory()->default()->create();
        $unauthorized = User::factory()->create();
        $drug = Drug::factory()->create(['rxnorm_code' => '13579', 'ndc_code' => null, 'times_prescribed' => 0]);
        $servicesBefore = Service::query()->count();

        try {
            app(MedicationOrderService::class)->order(
                ['guest_name' => 'Walk In', 'guest_phone' => '233000000000', 'branch_id' => $branch->id],
                [['service_id' => 'drug:'.$drug->id, 'quantity' => 1]],
                $unauthorized,
            );
            $this->fail('Expected the order to be rejected.');
        } catch (UnauthorizedMedicationOrderException) {
            // expected
        }

        $this->assertSame(0, Medication::query()->count());
        $this->assertSame($servicesBefore, Service::query()->count());
        $this->assertSame(0, $drug->fresh()->times_prescribed);
    }

    private function prescriber(): User
    {
        Permission::findOrCreate('order_prescription_medication', 'web');
        $user = User::factory()->create();
        $user->givePermissionTo('order_prescription_medication');

        return $user;
    }
}
