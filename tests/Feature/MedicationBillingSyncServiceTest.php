<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Classes\Services\MedicationBillingSyncService;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

class MedicationBillingSyncServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected MedicationBillingSyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);

        $this->syncService = app(MedicationBillingSyncService::class);
    }

    public function test_create_medication_with_billing_creates_service_and_medication(): void
    {
        $medication = $this->syncService->createMedicationWithBilling(
            [
                'generic_name' => 'Paracetamol',
                'brand_name' => 'Tylenol',
                'strength' => '500mg',
                'dosage_form' => 'tablet',
                'is_active' => true,
            ],
            [
                'price' => 10.00,
                'insurance_price' => 8.00,
                'is_insurance_covered' => true,
                'coverage_type' => 'nhis',
            ]
        );

        $this->assertNotNull($medication->id);
        $this->assertDatabaseHas('medications', [
            'id' => $medication->id,
            'generic_name' => 'Paracetamol',
        ]);

        $service = $medication->service;
        $this->assertNotNull($service);
        $this->assertSame('Tylenol 500mg', $service->name);
        $this->assertEquals(10.00, (float) $service->price);
        $this->assertEquals(8.00, (float) $service->insurance_price);
        $this->assertTrue((bool) $service->is_insurance_covered);
        $this->assertSame('nhis', $service->coverage_type?->value);
    }

    public function test_sync_updates_existing_service_prices(): void
    {
        $medication = $this->syncService->createMedicationWithBilling(
            ['generic_name' => 'Amoxicillin', 'dosage_form' => 'capsule', 'is_active' => true],
            ['price' => 15.00, 'insurance_price' => 12.00]
        );

        $medication->load('service');
        $serviceId = $medication->service->id;

        $this->syncService->syncBilling($medication, [
            'price' => 20.00,
            'insurance_price' => 18.00,
        ]);

        $service = Service::find($serviceId);
        $this->assertEquals(20.00, (float) $service->price);
        $this->assertEquals(18.00, (float) $service->insurance_price);
    }

    public function test_insurance_price_defaults_to_cash_price(): void
    {
        $medication = $this->syncService->createMedicationWithBilling(
            ['generic_name' => 'Ibuprofen', 'dosage_form' => 'tablet', 'is_active' => true],
            ['price' => 5.00]
        );

        $service = $medication->service;
        $this->assertEquals(5.00, (float) $service->insurance_price);
    }

    public function test_inactive_medication_syncs_inactive_service(): void
    {
        $medication = $this->syncService->createMedicationWithBilling(
            ['generic_name' => 'Old Med', 'dosage_form' => 'tablet', 'is_active' => false],
            ['price' => 0]
        );

        $service = $medication->service;
        $this->assertFalse((bool) $service->is_active);
    }

    public function test_display_name_includes_strength(): void
    {
        $medication = $this->syncService->createMedicationWithBilling(
            ['generic_name' => 'Metformin', 'strength' => '850mg', 'dosage_form' => 'tablet', 'is_active' => true],
            ['price' => 3.00]
        );

        $this->assertSame('Metformin 850mg', $medication->displayName());
    }

    public function test_ensure_billing_service_creates_when_missing(): void
    {
        $medication = Medication::factory()->create([
            'service_id' => null,
            'generic_name' => 'New Med',
            'dosage_form' => 'tablet',
            'is_active' => true,
        ]);

        $this->assertNull($medication->service);

        $this->syncService->ensureBillingService($medication, [
            'price' => 25.00,
        ]);

        $medication = $medication->fresh();
        $medication->load('service');
        $this->assertNotNull($medication->service);
        $this->assertEquals(25.00, (float) $medication->service->price);
    }

    public function test_ensure_billing_service_updates_when_present(): void
    {
        $medication = $this->syncService->createMedicationWithBilling(
            ['generic_name' => 'Existing', 'dosage_form' => 'tablet', 'is_active' => true],
            ['price' => 10.00]
        );

        $this->syncService->ensureBillingService($medication, [
            'price' => 30.00,
        ]);

        $medication->load('service');
        $this->assertEquals(30.00, (float) $medication->service->price);
    }

    public function test_sync_throws_when_no_service(): void
    {
        $this->expectException(\RuntimeException::class);

        $medication = Medication::factory()->create([
            'service_id' => null,
            'generic_name' => 'Orphan',
            'is_active' => true,
        ]);

        $this->syncService->syncBilling($medication, ['price' => 10.00]);
    }

    public function test_derive_service_name_brand_preferred(): void
    {
        $medication = $this->syncService->createMedicationWithBilling(
            [
                'generic_name' => 'Paracetamol',
                'brand_name' => 'Panadol',
                'strength' => '500mg',
                'dosage_form' => 'tablet',
                'is_active' => true,
            ],
            ['price' => 5.00]
        );

        $this->assertSame('Panadol 500mg', $medication->service->name);
    }

    public function test_display_name_falls_back_to_generic(): void
    {
        $medication = Medication::factory()->create([
            'generic_name' => 'Metformin',
            'brand_name' => null,
            'strength' => null,
            'dosage_form' => 'tablet',
            'is_active' => true,
        ]);

        $this->assertSame('Metformin', $medication->displayName());
    }
}
