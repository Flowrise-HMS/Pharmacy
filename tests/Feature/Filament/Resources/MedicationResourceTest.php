<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Unit;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\CreateMedication;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\EditMedication;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\ListMedications;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\ViewMedication;
use Modules\Pharmacy\Models\Medication;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Pharmacy');
    $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    $this->stockUnit = Unit::factory()->create();
    $this->billingUnit = Unit::factory()->create();
});

FilamentResourceTestSuite::register([
    'resource' => MedicationResource::class,
    'subject' => 'Medication',
    'model' => Medication::class,
    'listPage' => ListMedications::class,
    'createPage' => CreateMedication::class,
    'editPage' => EditMedication::class,
    'viewPage' => ViewMedication::class,
    'searchColumn' => 'generic_name',
    'filter' => [
        'name' => 'is_formulary',
        'value' => true,
        'attribute' => 'is_formulary',
    ],
    'hasBulkDelete' => true,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect([
            Medication::factory()->create([
                'is_formulary' => true,
                'generic_name' => 'Formulary Alpha',
            ]),
            Medication::factory()->nonFormulary()->create([
                'generic_name' => 'NonFormulary Beta',
            ]),
        ]);

        for ($index = 2; $index < $count; $index++) {
            $records->push(Medication::factory()->create([
                'is_formulary' => true,
                'generic_name' => 'Formulary '.$index,
            ]));
        }

        return $records;
    },
    'createForm' => fn (TestCase $test): array => [
        'generic_name' => 'Paracetamol',
        'brand_name' => 'Panadol',
        'dosage_form' => DosageForm::TABLET->value,
        'strength' => '500mg',
        'is_active' => true,
        'is_formulary' => true,
        'stock_unit_id' => $test->stockUnit->id,
        'billing_unit_id' => $test->billingUnit->id,
    ],
    'updateForm' => fn (): array => [
        'generic_name' => 'Paracetamol',
        'brand_name' => 'Updated brand',
        'strength' => '250mg',
    ],
    'schemaState' => fn (mixed $test, Medication $record): array => [
        'generic_name' => $record->generic_name,
        'is_active' => $record->is_active,
    ],
    'requiredValidation' => [
        'generic name is required' => [['generic_name' => null], ['generic_name' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'generic_name' => $payload['generic_name'],
        'brand_name' => $payload['brand_name'],
    ],
]);
