<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Models\Drug;
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

    public function test_it_prefers_local_drug_hits_before_external_results(): void
    {
        Drug::factory()->create([
            'source_provider' => 'local',
            'source_identifier' => 'local-amox',
            'generic_name' => 'Amoxicillin',
            'display_name' => 'Amoxicillin 250 MG Capsule',
            'brand_name' => 'Amoxil',
            'search_rank' => 100,
            'is_cached_external' => false,
        ]);

        RxNormHttpFake::register();

        config(['pharmacy.enable_external_drug_lookup' => true]);

        $results = app(DrugSearchService::class)->search('amox');

        $this->assertNotEmpty($results);
        $this->assertSame('local_drug', $results[0]['source']);
        $this->assertSame('Amoxicillin 250 MG Capsule', $results[0]['display_name']);
        $this->assertGreaterThanOrEqual(2, count($results));
        $this->assertSame('external', $results[1]['source']);
    }
}
