<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Enums\MedicationRoute;
use Modules\Pharmacy\Enums\StockMovementReason;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Modules\Pharmacy\Models\StockItem;
use Tests\TestCase;

class EdgeCaseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    // ─── Drug model ─────────────────────────────────────────────────────────

    public function test_drug_has_uuid(): void
    {
        $drug = Drug::factory()->create();
        $this->assertNotNull($drug->id);
    }

    // ─── Medication model ───────────────────────────────────────────────────

    public function test_medication_has_uuid(): void
    {
        $medication = Medication::factory()->create();
        $this->assertNotNull($medication->id);
    }

    // ─── Dispense model ─────────────────────────────────────────────────────

    public function test_dispense_has_uuid(): void
    {
        $dispense = Dispense::factory()->create();
        $this->assertNotNull($dispense->id);
    }

    // ─── StockItem model ────────────────────────────────────────────────────

    public function test_stock_item_has_uuid(): void
    {
        $stockItem = StockItem::factory()->create();
        $this->assertNotNull($stockItem->id);
    }

    // ─── PrescriptionDetail model ───────────────────────────────────────────

    public function test_prescription_detail_has_uuid(): void
    {
        $detail = PrescriptionDetail::factory()->create();
        $this->assertNotNull($detail->id);
    }

    // ─── DosageForm enum ────────────────────────────────────────────────────

    public function test_dosage_form_values(): void
    {
        $values = DosageForm::values();
        $this->assertContains('tablet', $values);
        $this->assertContains('capsule', $values);
        $this->assertContains('syrup', $values);
        $this->assertContains('injection', $values);
        $this->assertContains('cream', $values);
        $this->assertContains('inhaler', $values);
        $this->assertGreaterThanOrEqual(15, count($values));
    }

    // ─── MedicationFrequency enum ──────────────────────────────────────────

    public function test_medication_frequency_values(): void
    {
        $values = MedicationFrequency::values();
        $this->assertContains('qd', $values);
        $this->assertContains('bid', $values);
        $this->assertContains('tid', $values);
        $this->assertContains('qid', $values);
        $this->assertContains('prn', $values);
        $this->assertContains('stat', $values);
        $this->assertContains('once', $values);
    }

    public function test_medication_frequency_times_per_day(): void
    {
        $this->assertSame(1, MedicationFrequency::QD->timesPerDay());
        $this->assertSame(2, MedicationFrequency::BID->timesPerDay());
        $this->assertSame(3, MedicationFrequency::TID->timesPerDay());
        $this->assertSame(4, MedicationFrequency::QID->timesPerDay());
        $this->assertNull(MedicationFrequency::PRN->timesPerDay());
    }

    public function test_medication_frequency_hours_interval(): void
    {
        $this->assertSame(4, MedicationFrequency::Q4H->hoursInterval());
        $this->assertSame(6, MedicationFrequency::Q6H->hoursInterval());
        $this->assertSame(8, MedicationFrequency::Q8H->hoursInterval());
        $this->assertSame(12, MedicationFrequency::Q12H->hoursInterval());
        $this->assertNull(MedicationFrequency::QD->hoursInterval());
        $this->assertNull(MedicationFrequency::PRN->hoursInterval());
    }

    public function test_medication_frequency_is_interval_based(): void
    {
        $this->assertTrue(MedicationFrequency::Q4H->isIntervalBased());
        $this->assertFalse(MedicationFrequency::BID->isIntervalBased());
        $this->assertFalse(MedicationFrequency::PRN->isIntervalBased());
    }

    // ─── MedicationRoute enum ──────────────────────────────────────────────

    public function test_medication_route_values(): void
    {
        $values = MedicationRoute::values();
        $this->assertContains('po', $values);
        $this->assertContains('iv', $values);
        $this->assertContains('im', $values);
        $this->assertContains('sc', $values);
        $this->assertContains('topical', $values);
        $this->assertContains('inhalation', $values);
        $this->assertGreaterThanOrEqual(8, count($values));
    }

    // ─── AdministrationContext enum ─────────────────────────────────────────

    public function test_administration_context_values(): void
    {
        $values = AdministrationContext::values();
        $this->assertContains('in_facility', $values);
        $this->assertContains('take_home', $values);
        $this->assertCount(2, $values);
    }

    // ─── ControlledSchedule enum ───────────────────────────────────────────

    public function test_controlled_schedule_values(): void
    {
        $values = ControlledSchedule::values();
        $this->assertContains('schedule_1', $values);
        $this->assertContains('schedule_2', $values);
        $this->assertContains('schedule_3', $values);
        $this->assertContains('schedule_4', $values);
        $this->assertContains('schedule_5', $values);
    }

    // ─── DispenseFulfillmentType enum ───────────────────────────────────────

    public function test_dispense_fulfillment_type_values(): void
    {
        $values = array_column(DispenseFulfillmentType::cases(), 'value');
        $this->assertContains('in_house', $values);
        $this->assertContains('outside_purchase', $values);
        $this->assertContains('supply_only', $values);
        $this->assertCount(3, $values);
    }

    // ─── StockMovementReason enum ──────────────────────────────────────────

    public function test_stock_movement_reason_values(): void
    {
        $values = StockMovementReason::values();
        $this->assertContains('dispense', $values);
        $this->assertContains('receive', $values);
        $this->assertContains('adjust', $values);
        $this->assertContains('transfer', $values);
        $this->assertCount(4, $values);
    }
}
