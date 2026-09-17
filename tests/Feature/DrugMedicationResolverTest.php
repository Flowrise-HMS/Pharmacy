<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Pharmacy\Classes\Services\DrugMedicationResolver;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

class DrugMedicationResolverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    public function test_resolving_the_same_drug_twice_returns_one_non_formulary_medication(): void
    {
        $drug = Drug::factory()->create(['rxnorm_code' => '55555', 'ndc_code' => null]);

        $first = app(DrugMedicationResolver::class)->resolve($drug);
        $second = app(DrugMedicationResolver::class)->resolve($drug);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Medication::query()->where('drug_id', $drug->id)->count());
        $this->assertFalse($first->is_formulary);
        $this->assertDatabaseHas('services', [
            'id' => $first->service_id,
            'price' => '0.00',
            'requires_prescription' => 1,
        ]);
    }

    public function test_sibling_drugs_sharing_an_rxnorm_code_resolve_to_one_medication(): void
    {
        $openFda = Drug::factory()->create(['source_provider' => 'openfda', 'rxnorm_code' => '777', 'ndc_code' => '1111-2222-33']);
        $rxNorm = Drug::factory()->create(['source_provider' => 'rxnorm', 'rxnorm_code' => '777', 'ndc_code' => null]);

        $first = app(DrugMedicationResolver::class)->resolve($openFda);
        $second = app(DrugMedicationResolver::class)->resolve($rxNorm);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($openFda->id, $second->drug_id);
        $this->assertSame(1, Medication::query()->count());
    }

    public function test_it_adopts_an_unlinked_medication_matching_by_rxnorm_code(): void
    {
        $medication = Medication::factory()->create(['rxnorm_code' => '999', 'ndc_code' => null, 'drug_id' => null]);
        $drug = Drug::factory()->create(['rxnorm_code' => '999', 'ndc_code' => null]);

        $resolved = app(DrugMedicationResolver::class)->resolve($drug);

        $this->assertSame($medication->id, $resolved->id);
        $this->assertSame($drug->id, $resolved->fresh()->drug_id);
        $this->assertSame(1, Medication::query()->count());
    }

    public function test_it_adopts_an_unlinked_medication_matching_by_ndc_code(): void
    {
        $medication = Medication::factory()->create(['rxnorm_code' => null, 'ndc_code' => '4444-5555-66', 'drug_id' => null]);
        $drug = Drug::factory()->create(['rxnorm_code' => null, 'ndc_code' => '4444-5555-66']);

        $resolved = app(DrugMedicationResolver::class)->resolve($drug);

        $this->assertSame($medication->id, $resolved->id);
        $this->assertSame($drug->id, $resolved->fresh()->drug_id);
    }

    public function test_a_drug_without_codes_never_matches_an_unrelated_medication(): void
    {
        $unrelated = Medication::factory()->create();
        $drug = Drug::factory()->create(['rxnorm_code' => null, 'ndc_code' => null]);

        $resolved = app(DrugMedicationResolver::class)->resolve($drug);

        $this->assertNotSame($unrelated->id, $resolved->id);
        $this->assertSame($drug->id, $resolved->drug_id);
        $this->assertSame(2, Medication::query()->count());
    }
}
