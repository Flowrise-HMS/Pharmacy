<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Core\Models\Unit;
use Modules\Pharmacy\Classes\Services\DrugMedicationResolver;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\CreateMedication;
use Modules\Pharmacy\Models\Drug;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

class CreateMedicationPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);

        Gate::before(fn (): bool => true);

        $this->actingAs(User::factory()->create());

        Filament::setCurrentPanel(Filament::getDefaultPanel());
    }

    public function test_creating_from_a_drug_links_it_and_marks_it_formulary(): void
    {
        $drug = Drug::factory()->create(['rxnorm_code' => '51515', 'ndc_code' => null]);
        $unit = Unit::factory()->create();

        Livewire::test(CreateMedication::class)
            ->fillForm([
                'drug_id' => $drug->id,
                'generic_name' => 'Ceftriaxone',
                'dosage_form' => DosageForm::INJECTION->value,
                'strength' => '1g',
                'price' => 25,
                'is_active' => true,
                'is_formulary' => true,
                'stock_unit_id' => $unit->id,
                'billing_unit_id' => $unit->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $medication = Medication::query()->where('drug_id', $drug->id)->firstOrFail();

        $this->assertTrue($medication->is_formulary);
        $this->assertSame(1, Medication::query()->count());
        $this->assertDatabaseHas('services', ['id' => $medication->service_id, 'price' => '25.00']);
    }

    public function test_a_drug_already_prescribed_by_a_clinician_cannot_be_created_twice(): void
    {
        $drug = Drug::factory()->create(['rxnorm_code' => '61616', 'ndc_code' => null]);
        app(DrugMedicationResolver::class)->resolve($drug);

        Livewire::test(CreateMedication::class)
            ->fillForm([
                'drug_id' => $drug->id,
                'generic_name' => 'Ceftriaxone',
                'dosage_form' => DosageForm::INJECTION->value,
                'price' => 25,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['drug_id' => 'unique']);

        $this->assertSame(1, Medication::query()->where('drug_id', $drug->id)->count());
    }
}
