<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Classes\Services\DrugMaterializationService;
use Modules\Pharmacy\Classes\Services\DrugMedicationResolver;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

class DrugMaterializationServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    public function test_it_materializes_a_drug_into_service_medication_and_stock(): void
    {
        $branch = Branch::factory()->default()->create();
        $drug = Drug::factory()->create([
            'generic_name' => 'Amoxicillin',
            'display_name' => 'Amoxicillin 500 MG Oral Capsule',
            'brand_name' => 'Amoxil',
            'strength_text' => '500 MG',
            'dosage_form_text' => 'capsule',
            'rxnorm_code' => '12345',
        ]);

        $medication = app(DrugMaterializationService::class)->materialize($drug, [
            'service_name' => 'Amoxicillin 500 MG Oral Capsule',
            'price' => 12.50,
            'requires_prescription' => true,
            'branch_id' => $branch->id,
            'initial_stock_quantity' => 25,
        ]);

        $service = $medication->service()->first();

        $this->assertInstanceOf(Medication::class, $medication);
        $this->assertInstanceOf(Service::class, $service);
        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Amoxil 500 MG',
            'price' => '12.50',
            'requires_prescription' => 1,
        ]);
        $this->assertDatabaseHas('medications', [
            'id' => $medication->id,
            'service_id' => $service->id,
            'generic_name' => 'Amoxicillin',
            'brand_name' => 'Amoxil',
            'rxnorm_code' => '12345',
        ]);
        $this->assertDatabaseHas('stock_items', [
            'medication_id' => $medication->id,
            'branch_id' => $branch->id,
            'quantity_on_hand' => 25,
        ]);
        $this->assertDatabaseHas('service_categories', [
            'id' => $service->category_id,
            'code' => 'MED',
        ]);
    }

    public function test_it_prices_and_promotes_an_existing_non_formulary_medication_instead_of_duplicating(): void
    {
        $branch = Branch::factory()->default()->create();
        $drug = Drug::factory()->create(['rxnorm_code' => '24680', 'ndc_code' => null]);
        $existing = app(DrugMedicationResolver::class)->resolve($drug);

        $this->assertFalse($existing->is_formulary);

        $medication = app(DrugMaterializationService::class)->materialize($drug, [
            'price' => 8.75,
            'requires_prescription' => true,
            'branch_id' => $branch->id,
            'initial_stock_quantity' => 10,
        ]);

        $this->assertSame($existing->id, $medication->id);
        $this->assertTrue($medication->is_formulary);
        $this->assertSame(1, Medication::query()->where('drug_id', $drug->id)->count());
        $this->assertDatabaseHas('services', [
            'id' => $medication->service_id,
            'price' => '8.75',
        ]);
        $this->assertDatabaseHas('stock_items', [
            'medication_id' => $medication->id,
            'branch_id' => $branch->id,
            'quantity_on_hand' => 10,
        ]);
    }
}
