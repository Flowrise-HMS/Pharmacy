<?php

namespace Modules\Pharmacy\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Models\StockMovement;
use Tests\TestCase;

class PharmacyFactorySmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    public function test_drug_factory(): void
    {
        $drug = Drug::factory()->create();
        $this->assertTrue($drug->exists);
        $this->assertNotNull($drug->id);
    }

    public function test_medication_factory(): void
    {
        $medication = Medication::factory()->create();
        $this->assertTrue($medication->exists);
        $this->assertNotNull($medication->id);
    }

    public function test_dispense_factory(): void
    {
        $dispense = Dispense::factory()->create();
        $this->assertTrue($dispense->exists);
        $this->assertNotNull($dispense->id);
    }

    public function test_prescription_detail_factory(): void
    {
        $detail = PrescriptionDetail::factory()->create();
        $this->assertTrue($detail->exists);
        $this->assertNotNull($detail->id);
    }

    public function test_stock_item_factory(): void
    {
        $item = StockItem::factory()->create();
        $this->assertTrue($item->exists);
        $this->assertNotNull($item->id);
    }

    public function test_stock_movement_factory(): void
    {
        $movement = StockMovement::factory()->create();
        $this->assertTrue($movement->exists);
        $this->assertNotNull($movement->id);
    }
}
