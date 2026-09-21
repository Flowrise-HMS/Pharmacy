<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\DispenseResource;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages\EditDispense;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages\ListDispenses;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages\ViewDispense;
use Modules\Pharmacy\Models\Dispense;
use Modules\Pharmacy\Models\Medication;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Pharmacy');
    $this->migrateModules(['Core', 'Patient', 'Staff', 'Clinical', 'Pharmacy']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
});

FilamentResourceTestSuite::register([
    'resource' => DispenseResource::class,
    'subject' => 'Dispense',
    'model' => Dispense::class,
    'listPage' => ListDispenses::class,
    'editPage' => EditDispense::class,
    'viewPage' => ViewDispense::class,
    'sortColumn' => 'quantity',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => function (TestCase $test, array $attributes = []): Dispense {
        $patient = Patient::factory()->create(['branch_id' => $test->branch->id]);
        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $test->branch->id,
        ]);
        $item = RequestItem::factory()
            ->forRequest($request)
            ->forService($test->nonDiagnosticService())
            ->create();

        return Dispense::factory()->create([
            'branch_id' => $test->branch->id,
            'request_item_id' => $item->id,
            'medication_id' => Medication::factory()->create()->id,
            ...$attributes,
        ]);
    },
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $patient = Patient::factory()->create(['branch_id' => $test->branch->id]);
            $request = ServiceRequest::factory()->create([
                'patient_id' => $patient->id,
                'branch_id' => $test->branch->id,
            ]);
            $item = RequestItem::factory()
                ->forRequest($request)
                ->forService($test->nonDiagnosticService())
                ->create();

            $records->push(Dispense::factory()->create([
                'branch_id' => $test->branch->id,
                'request_item_id' => $item->id,
                'medication_id' => Medication::factory()->create()->id,
                'quantity' => $index + 2,
            ]));
        }

        return $records;
    },
    'updateForm' => fn (): array => [
        'quantity' => 4,
        'notes' => 'Updated dispense notes',
    ],
    'schemaState' => fn (mixed $test, Dispense $record): array => [
        'quantity' => $record->quantity,
    ],
]);
