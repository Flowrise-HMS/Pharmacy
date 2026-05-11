<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Modules\Pharmacy\Classes\Services\ExternalDrugLookupService;
use Modules\Pharmacy\Models\Drug;
use Tests\TestCase;

class ExternalDrugLookupServiceTest extends TestCase
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

    public function test_it_normalizes_and_caches_rxnorm_lookup_results(): void
    {
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

        $service = app(ExternalDrugLookupService::class);

        $first = $service->search('amox');
        $second = $service->search('amox');

        $this->assertCount(1, $first);
        $this->assertSame('rxnorm', $first[0]['source_provider']);
        $this->assertSame('12345', $first[0]['source_identifier']);
        $this->assertSame('Amoxicillin 500 MG Oral Capsule', $first[0]['display_name']);
        $this->assertNotNull($first[0]['drug_id']);
        $this->assertSame($first, $second);
        $this->assertDatabaseHas('drugs', [
            'id' => $first[0]['drug_id'],
            'source_provider' => 'rxnorm',
            'source_identifier' => '12345',
            'display_name' => 'Amoxicillin 500 MG Oral Capsule',
            'is_cached_external' => 1,
        ]);
        $this->assertSame(1, Drug::query()->count());

        Http::assertSentCount(2);
    }
}
