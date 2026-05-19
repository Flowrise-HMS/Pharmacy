<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Models\Drug;
use Tests\TestCase;

class DrugSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);
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

        Http::fake([
            'https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui.json*' => Http::response([
                'idGroup' => [
                    'rxnormId' => ['12345'],
                ],
            ]),
            'https://rxnav.nlm.nih.gov/REST/Prescribe/rxcui/12345/property.json' => Http::response([
                'propConceptGroup' => [
                    'propConcept' => [[
                        'rxcui' => '12345',
                        'name' => 'Amoxicillin 500 MG Oral Capsule',
                    ]],
                ],
            ]),
        ]);

        config(['pharmacy.enable_external_drug_lookup' => true]);

        $results = app(DrugSearchService::class)->search('amox');

        $this->assertNotEmpty($results);
        $this->assertSame('local_drug', $results[0]['source']);
        $this->assertSame('Amoxicillin 250 MG Capsule', $results[0]['display_name']);
        $this->assertSame('external', $results[1]['source']);
    }
}
