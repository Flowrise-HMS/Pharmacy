<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Classes\Services\DrugMaterializationService;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Models\Drug;
use Tests\TestCase;

class DrugBackedMedicationOrderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);
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
            'insurance_price' => 4,
            'is_insurance_covered' => true,
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
}
