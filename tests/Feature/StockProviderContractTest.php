<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Classes\Services\StockService;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Tests\TestCase;

class StockProviderContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);
    }

    public function test_it_handles_increment_decrement_and_stock_checks(): void
    {
        $service = Service::factory()->create();
        $branch = Branch::factory()->create();
        $medication = Medication::factory()->create(['service_id' => $service->id]);
        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 20,
        ]);

        $stockService = app(StockService::class);

        $this->assertTrue($stockService->hasStock($branch->id, $medication->id, 10));
        $stockService->decrement($branch->id, $medication->id, 5);
        $this->assertSame(15, $stockService->getQuantityOnHand($branch->id, $medication->id));
        $stockService->increment($branch->id, $medication->id, 3);
        $this->assertSame(18, $stockService->getQuantityOnHand($branch->id, $medication->id));
    }
}
