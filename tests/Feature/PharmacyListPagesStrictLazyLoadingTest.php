<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages\ListDispenses;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\ListStockItems;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\ListStockMovements;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Models\StockMovement;
use Tests\TestCase;

/**
 * The Pharmacy list pages must render with Model::preventLazyLoading() on.
 *
 * Stock items, stock movements and dispenses each render relationship columns
 * (medication, unit, branch, ...). Without explicit eager loading the tables
 * threw LazyLoadingViolationException in local/staging as soon as more than one
 * row existed, because Eloquent only flags multi-row result sets.
 */
class PharmacyListPagesStrictLazyLoadingTest extends TestCase
{
    use DatabaseTransactions;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
        Model::preventLazyLoading(true);

        Gate::before(fn () => true);
        $this->branch = Branch::factory()->default()->create();
        $user = User::factory()->create(['branch_id' => $this->branch->id]);
        Livewire::actingAs($user);
    }

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    public function test_stock_items_list_renders(): void
    {
        StockItem::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'medication_id' => fn () => Medication::factory()->create()->id,
        ]);

        Livewire::test(ListStockItems::class)->assertOk();
    }

    public function test_stock_movements_list_renders(): void
    {
        StockMovement::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'medication_id' => fn () => Medication::factory()->create()->id,
        ]);

        Livewire::test(ListStockMovements::class)->assertOk();
    }

    public function test_dispenses_list_renders(): void
    {
        // The Diagnostics fulfillment listener is irrelevant here and needs a
        // full service-request graph, so create the request items silently.
        RequestItem::withoutEvents(fn () => Dispense::factory()->count(2)->create([
            'branch_id' => $this->branch->id,
            'medication_id' => fn () => Medication::factory()->create()->id,
        ]));

        Livewire::test(ListDispenses::class)->assertOk();
    }
}
