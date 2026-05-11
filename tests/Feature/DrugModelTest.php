<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Pharmacy\Models\Drug;
use Tests\TestCase;

class DrugModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);
    }

    public function test_it_persists_reference_drug_metadata(): void
    {
        $drug = Drug::query()->create([
            'source_provider' => 'rxnorm',
            'source_identifier' => '12345',
            'rxnorm_code' => '12345',
            'ndc_code' => '00011-2222-33',
            'generic_name' => 'Amoxicillin',
            'display_name' => 'Amoxicillin 500 MG Oral Capsule',
            'brand_name' => 'Amoxil',
            'strength_text' => '500 MG',
            'dosage_form_text' => 'Oral Capsule',
            'search_rank' => 25,
            'times_prescribed' => 4,
            'times_stocked' => 1,
            'is_cached_external' => true,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('drugs', [
            'id' => $drug->id,
            'source_provider' => 'rxnorm',
            'source_identifier' => '12345',
            'generic_name' => 'Amoxicillin',
            'display_name' => 'Amoxicillin 500 MG Oral Capsule',
            'brand_name' => 'Amoxil',
            'search_rank' => 25,
            'times_prescribed' => 4,
            'times_stocked' => 1,
            'is_cached_external' => 1,
            'is_active' => 1,
        ]);
    }

    public function test_it_casts_synonyms_and_raw_payload_as_arrays(): void
    {
        $drug = Drug::query()->create([
            'source_provider' => 'rxnorm',
            'source_identifier' => '99999',
            'generic_name' => 'Ibuprofen',
            'display_name' => 'Ibuprofen',
            'synonyms' => ['Brufen', 'Advil'],
            'raw_payload' => ['tty' => 'SCD', 'language' => 'ENG'],
            'is_cached_external' => true,
            'is_active' => true,
        ]);

        $this->assertSame(['Brufen', 'Advil'], $drug->fresh()->synonyms);
        $this->assertSame(['tty' => 'SCD', 'language' => 'ENG'], $drug->fresh()->raw_payload);
    }
}
