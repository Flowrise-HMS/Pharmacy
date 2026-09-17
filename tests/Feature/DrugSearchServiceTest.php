<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Tests\Support\RxNormHttpFake;
use Tests\TestCase;

class DrugSearchServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    public function test_it_ranks_formulary_medications_before_local_drugs_before_external_results(): void
    {
        Medication::factory()->create([
            'generic_name' => 'Amoxicillin',
            'brand_name' => null,
            'strength' => '500mg',
            'rxnorm_code' => '900001',
            'ndc_code' => null,
        ]);

        Drug::factory()->create([
            'source_provider' => 'local',
            'source_identifier' => 'local-amox',
            'generic_name' => 'Amoxicillin',
            'display_name' => 'Amoxicillin 250 MG Capsule',
            'brand_name' => 'Amoxil',
            'rxnorm_code' => '900002',
            'ndc_code' => null,
            'search_rank' => 100,
            'is_cached_external' => false,
        ]);

        RxNormHttpFake::register();

        config(['pharmacy.enable_external_drug_lookup' => true]);

        $results = app(DrugSearchService::class)->search('amox');

        $this->assertGreaterThanOrEqual(3, count($results));
        $this->assertSame('local_medication', $results[0]['source']);
        $this->assertSame('local_drug', $results[1]['source']);
        $this->assertSame('Amoxicillin 250 MG Capsule', $results[1]['display_name']);
        $this->assertSame('external', $results[2]['source']);
    }

    public function test_drugs_already_represented_by_a_medication_are_hidden(): void
    {
        $linked = Drug::factory()->create([
            'generic_name' => 'Paracetamol',
            'display_name' => 'Paracetamol 500 MG Tablet',
            'rxnorm_code' => '800001',
            'ndc_code' => null,
        ]);
        Medication::factory()->create(['drug_id' => $linked->id, 'generic_name' => 'Paracetamol', 'rxnorm_code' => null, 'ndc_code' => null]);

        $sharedCode = Drug::factory()->create([
            'generic_name' => 'Paracetamol',
            'display_name' => 'Paracetamol 1 G Tablet',
            'rxnorm_code' => '800002',
            'ndc_code' => null,
        ]);
        Medication::factory()->create(['drug_id' => null, 'generic_name' => 'Panadol', 'rxnorm_code' => '800002', 'ndc_code' => null]);

        $unclaimed = Drug::factory()->create([
            'generic_name' => 'Paracetamol',
            'display_name' => 'Paracetamol 120 MG/5 ML Syrup',
            'rxnorm_code' => '800003',
            'ndc_code' => null,
        ]);

        $drugIds = collect(app(DrugSearchService::class)->search('paracetamol'))
            ->pluck('drug_id')
            ->filter()
            ->values()
            ->all();

        $this->assertSame([$unclaimed->id], $drugIds);
        $this->assertNotContains($linked->id, $drugIds);
        $this->assertNotContains($sharedCode->id, $drugIds);
    }

    public function test_top_local_results_rank_medications_first_and_hide_linked_drugs(): void
    {
        $linked = Drug::factory()->create(['rxnorm_code' => '700001', 'ndc_code' => null, 'times_prescribed' => 999]);
        Medication::factory()->create(['drug_id' => $linked->id, 'rxnorm_code' => null, 'ndc_code' => null]);
        $unclaimed = Drug::factory()->create(['rxnorm_code' => '700002', 'ndc_code' => null, 'times_prescribed' => 1]);

        $results = app(DrugSearchService::class)->getTopLocalDrugs(10);

        $this->assertSame('local_medication', $results[0]['source']);
        $this->assertSame([$unclaimed->id], collect($results)->pluck('drug_id')->filter()->values()->all());
    }
}
