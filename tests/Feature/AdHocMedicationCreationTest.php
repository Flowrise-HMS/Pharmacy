<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Tests\TestCase;

/**
 * The medication-order "create option" form (Clinical workspace and the
 * Medication Order action) posts only names, dosage form and price. The
 * observer used to copy the medication's unset is_active (null) onto the
 * service and crash with "Column 'is_active' cannot be null".
 */
class AdHocMedicationCreationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
    }

    public function test_creating_a_medication_without_an_active_flag_creates_an_active_service(): void
    {
        $medication = app(MedicationService::class)->createWithService([
            'generic_name' => 'Paracetamol',
            'brand_name' => null,
            'strength' => '500mg',
            'dosage_form' => 'tablet',
            'price' => 0,
            'is_formulary' => false,
        ]);

        $medication->refresh();

        $this->assertTrue($medication->is_active);
        $this->assertNotNull($medication->service);
        $this->assertTrue($medication->service->is_active);
        $this->assertSame($medication->displayName(), $medication->service->name);
    }

    public function test_deactivating_a_medication_deactivates_its_service(): void
    {
        $medication = app(MedicationService::class)->createWithService([
            'generic_name' => 'Ibuprofen',
            'dosage_form' => 'tablet',
            'price' => 2,
        ]);

        $medication->update(['is_active' => false]);

        $this->assertFalse($medication->service->fresh()->is_active);
    }
}
