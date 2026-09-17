<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Classes\Services\MedicationMergeService;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Models\StockMovement;
use Tests\TestCase;

class MergeDuplicateMedicationsCommandTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy']);
    }

    public function test_dry_run_reports_duplicates_without_changing_anything(): void
    {
        [$canonical, $duplicate] = $this->seedRxNormDuplicates();

        $this->artisan('pharmacy:merge-duplicate-medications')
            ->expectsOutputToContain('[DRY-RUN]')
            ->expectsOutputToContain($canonical->id)
            ->expectsOutputToContain($duplicate->id)
            ->assertSuccessful();

        $this->assertDatabaseHas('medications', ['id' => $duplicate->id]);
        $this->assertSame(2, Medication::query()->count());
    }

    public function test_name_only_matches_are_reported_but_not_merged_by_default(): void
    {
        $shared = ['generic_name' => 'Paracetamol', 'brand_name' => null, 'strength' => '500mg', 'dosage_form' => DosageForm::TABLET, 'rxnorm_code' => null, 'drug_id' => null];
        $packOf100 = Medication::factory()->create($shared + ['ndc_code' => '0001-0001-01']);
        $packOf500 = Medication::factory()->create($shared + ['ndc_code' => '0001-0001-02']);

        $groups = app(MedicationMergeService::class)->findDuplicateGroups();

        $this->assertCount(1, $groups);
        $this->assertTrue($groups->first()['name_only']);

        $this->artisan('pharmacy:merge-duplicate-medications --force')
            ->expectsOutputToContain('possible match by name only')
            ->assertSuccessful();

        $this->assertDatabaseHas('medications', ['id' => $packOf100->id]);
        $this->assertDatabaseHas('medications', ['id' => $packOf500->id]);
    }

    public function test_force_merges_into_the_higher_stock_row_and_repoints_every_reference(): void
    {
        [$canonical, $duplicate, $branch] = $this->seedRxNormDuplicates();

        // Colliding stock rows at the same branch, plus one at a second branch.
        $otherBranch = Branch::factory()->create();
        StockItem::factory()->create(['medication_id' => $duplicate->id, 'branch_id' => $branch->id, 'quantity_on_hand' => 7, 'reorder_point' => 20]);
        StockItem::factory()->create(['medication_id' => $duplicate->id, 'branch_id' => $otherBranch->id, 'quantity_on_hand' => 3]);

        $orderedBy = User::factory()->create();
        $request = ServiceRequest::factory()->create([
            'branch_id' => $branch->id,
            'ordered_by' => $orderedBy->id,
            'created_by' => $orderedBy->id,
            'patient_id' => null,
            'guest_name' => 'Guest User',
            'guest_phone' => '233000000000',
        ]);
        $requestItem = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $duplicate->service_id,
            'quantity' => 1,
        ]);
        $dispense = Dispense::factory()->create([
            'request_item_id' => $requestItem->id,
            'medication_id' => $duplicate->id,
            'branch_id' => $branch->id,
        ]);
        $movement = StockMovement::factory()->create(['medication_id' => $duplicate->id, 'branch_id' => $branch->id]);

        $duplicateServiceId = $duplicate->service_id;

        $this->artisan('pharmacy:merge-duplicate-medications --force')
            ->assertSuccessful();

        $this->assertDatabaseMissing('medications', ['id' => $duplicate->id]);
        $this->assertDatabaseHas('medications', ['id' => $canonical->id]);

        $this->assertDatabaseHas('stock_items', [
            'medication_id' => $canonical->id,
            'branch_id' => $branch->id,
            'quantity_on_hand' => 50 + 7,
            'reorder_point' => 20,
        ]);
        $this->assertDatabaseHas('stock_items', ['medication_id' => $canonical->id, 'branch_id' => $otherBranch->id, 'quantity_on_hand' => 3]);
        $this->assertDatabaseHas('stock_movements', ['medication_id' => $canonical->id, 'reason' => 'merge_from:'.$duplicate->id, 'delta' => 7]);

        $this->assertDatabaseHas('request_items', ['id' => $requestItem->id, 'service_id' => $canonical->service_id]);
        $this->assertDatabaseHas('dispenses', ['id' => $dispense->id, 'medication_id' => $canonical->id]);
        $this->assertDatabaseHas('stock_movements', ['id' => $movement->id, 'medication_id' => $canonical->id]);

        $this->assertDatabaseHas('services', ['id' => $duplicateServiceId, 'is_active' => 0]);
        $this->assertSame($canonical->service_id, Service::query()->find($duplicateServiceId)->metadata['merged_into_service_id']);
    }

    public function test_delete_services_removes_the_duplicate_service_and_keeps_prescriptions(): void
    {
        [$canonical, $duplicate, $branch] = $this->seedRxNormDuplicates();

        $orderedBy = User::factory()->create();
        $request = ServiceRequest::factory()->create([
            'branch_id' => $branch->id,
            'ordered_by' => $orderedBy->id,
            'created_by' => $orderedBy->id,
            'patient_id' => null,
            'guest_name' => 'Guest User',
            'guest_phone' => '233000000000',
        ]);
        $requestItem = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $duplicate->service_id,
        ]);
        $duplicateServiceId = $duplicate->service_id;

        $this->artisan('pharmacy:merge-duplicate-medications --force --delete-services')
            ->assertSuccessful();

        $this->assertDatabaseMissing('services', ['id' => $duplicateServiceId]);
        $this->assertDatabaseHas('request_items', ['id' => $requestItem->id, 'service_id' => $canonical->service_id]);
    }

    public function test_merge_adopts_the_drug_link_and_copies_a_price_onto_an_unpriced_canonical(): void
    {
        $branch = Branch::factory()->default()->create();
        $drug = Drug::factory()->create(['rxnorm_code' => '42424', 'ndc_code' => null]);

        $stockedButUnpriced = Medication::factory()->create(['rxnorm_code' => '42424', 'ndc_code' => null, 'drug_id' => null]);
        $stockedButUnpriced->service->update(['price' => 0]);
        StockItem::factory()->create(['medication_id' => $stockedButUnpriced->id, 'branch_id' => $branch->id, 'quantity_on_hand' => 10]);

        $pricedAndLinked = Medication::factory()->create(['rxnorm_code' => '42424', 'ndc_code' => null, 'drug_id' => $drug->id]);
        $pricedAndLinked->service->update(['price' => 15.5]);

        $this->artisan('pharmacy:merge-duplicate-medications --force')->assertSuccessful();

        $canonical = $stockedButUnpriced->fresh(['service']);

        $this->assertDatabaseMissing('medications', ['id' => $pricedAndLinked->id]);
        $this->assertSame($drug->id, $canonical->drug_id);
        $this->assertSame('15.50', (string) $canonical->service->price);
    }

    /**
     * @return array{0: Medication, 1: Medication, 2: Branch}
     */
    private function seedRxNormDuplicates(): array
    {
        $branch = Branch::factory()->default()->create();

        $canonical = Medication::factory()->create(['generic_name' => 'Propofol', 'rxnorm_code' => '31313', 'ndc_code' => null, 'drug_id' => null]);
        StockItem::factory()->create(['medication_id' => $canonical->id, 'branch_id' => $branch->id, 'quantity_on_hand' => 50, 'reorder_point' => 5]);

        $duplicate = Medication::factory()->create(['generic_name' => 'Propofol', 'rxnorm_code' => '31313', 'ndc_code' => null, 'drug_id' => null]);

        return [$canonical, $duplicate, $branch];
    }
}
