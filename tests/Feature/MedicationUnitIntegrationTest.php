<?php

namespace Modules\Pharmacy\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Enums\UnitCategory;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Pharmacy\Classes\Support\UnitResolver;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Medication;
use Tests\TestCase;

class MedicationUnitIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
        $this->seed(\Modules\Core\Database\Seeders\UnitSeeder::class);
    }

    public function test_observer_auto_sets_units_on_create(): void
    {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'dosage_form' => DosageForm::TABLET,
            'stock_unit_id' => null,
            'billing_unit_id' => null,
            'dose_unit_id' => null,
        ]);

        $medication->refresh();

        $this->assertNotNull($medication->stock_unit_id);
        $this->assertNotNull($medication->billing_unit_id);
        $this->assertNotNull($medication->dose_unit_id);

        $this->assertDatabaseHas('medications', [
            'id' => $medication->id,
            'stock_unit_id' => $medication->stock_unit_id,
            'billing_unit_id' => $medication->billing_unit_id,
            'dose_unit_id' => $medication->dose_unit_id,
        ]);
    }

    public function test_observer_does_not_override_explicit_units(): void
    {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        $tablet = \Modules\Core\Models\Unit::where('code', 'tablet')->first();
        $capsule = \Modules\Core\Models\Unit::where('code', 'capsule')->first();

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'dosage_form' => DosageForm::TABLET,
            'stock_unit_id' => $capsule->id,
            'billing_unit_id' => null,
            'dose_unit_id' => null,
        ]);

        $medication->refresh();

        $this->assertSame($capsule->id, $medication->stock_unit_id);
    }

    public function test_observer_skips_when_no_dosage_form(): void
    {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'dosage_form' => null,
            'stock_unit_id' => null,
            'billing_unit_id' => null,
            'dose_unit_id' => null,
        ]);

        $medication->refresh();

        $this->assertNull($medication->stock_unit_id);
        $this->assertNull($medication->billing_unit_id);
        $this->assertNull($medication->dose_unit_id);
    }

    public function test_backfill_command_fills_missing_units(): void
    {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        $medication = Medication::withoutEvents(fn () => Medication::factory()->create([
            'service_id' => $service->id,
            'dosage_form' => DosageForm::SYRUP,
            'stock_unit_id' => null,
            'billing_unit_id' => null,
            'dose_unit_id' => null,
        ]));

        $this->artisan('pharmacy:backfill-medication-units')
            ->expectsOutputToContain('Backfilled')
            ->assertExitCode(0);

        $medication->refresh();

        $this->assertNotNull($medication->stock_unit_id);
        $this->assertNotNull($medication->billing_unit_id);
        $this->assertNotNull($medication->dose_unit_id);
    }

    public function test_backfill_dry_run_does_not_update(): void
    {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        $medication = Medication::withoutEvents(fn () => Medication::factory()->create([
            'service_id' => $service->id,
            'dosage_form' => DosageForm::TABLET,
            'stock_unit_id' => null,
            'billing_unit_id' => null,
            'dose_unit_id' => null,
        ]));

        $this->artisan('pharmacy:backfill-medication-units --dry-run')
            ->expectsOutputToContain('DRY-RUN')
            ->assertExitCode(0);

        $medication->refresh();

        $this->assertNull($medication->stock_unit_id);
    }

    public function test_backfill_skips_medications_without_dosage_form(): void
    {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        Medication::withoutEvents(fn () => Medication::factory()->create([
            'service_id' => $service->id,
            'dosage_form' => null,
            'stock_unit_id' => null,
            'billing_unit_id' => null,
            'dose_unit_id' => null,
        ]));

        $this->artisan('pharmacy:backfill-medication-units')
            ->doesntExpectOutputToContain('DRY-RUN')
            ->assertExitCode(0);
    }

    public function test_returns_success_when_all_units_assigned(): void
    {
        $category = ServiceCategory::factory()->create();
        $service = Service::factory()->create(['category_id' => $category->id]);

        $tablet = \Modules\Core\Models\Unit::where('code', 'tablet')->first();

        Medication::withoutEvents(fn () => Medication::factory()->create([
            'service_id' => $service->id,
            'dosage_form' => DosageForm::TABLET,
            'stock_unit_id' => $tablet->id,
            'billing_unit_id' => $tablet->id,
            'dose_unit_id' => $tablet->id,
            'units_per_stock_unit' => 1,
        ]));

        $this->artisan('pharmacy:backfill-medication-units')
            ->expectsOutputToContain('already have units')
            ->assertExitCode(0);
    }
}
