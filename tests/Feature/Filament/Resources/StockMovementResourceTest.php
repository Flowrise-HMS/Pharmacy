<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\CreateStockMovement;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\EditStockMovement;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\ListStockMovements;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\ViewStockMovement;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\StockMovementResource;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockMovement;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Pharmacy');
    $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
    $this->medication = Medication::factory()->create();
});

FilamentResourceTestSuite::register([
    'resource' => StockMovementResource::class,
    'subject' => 'StockMovement',
    'model' => StockMovement::class,
    'listPage' => ListStockMovements::class,
    'createPage' => CreateStockMovement::class,
    'editPage' => EditStockMovement::class,
    'viewPage' => ViewStockMovement::class,
    'searchColumn' => 'reason',
    'hasBulkDelete' => true,
    'hasRecordDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): StockMovement => StockMovement::factory()->create([
        'branch_id' => $test->branch->id,
        'medication_id' => $test->medication->id,
        'performed_by' => auth()->id(),
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => StockMovement::factory()->count($count)->create([
        'branch_id' => $test->branch->id,
        'medication_id' => $test->medication->id,
        'performed_by' => auth()->id(),
    ]),
    'createForm' => fn (TestCase $test): array => [
        'branch_id' => $test->branch->id,
        'medication_id' => $test->medication->id,
        'delta' => 10,
        'quantity_after' => 30,
        'reason' => 'opening-stock',
        'performed_by' => auth()->id(),
    ],
    'updateForm' => fn (TestCase $test): array => [
        'delta' => 5,
        'quantity_after' => 35,
        'reason' => 'adjustment',
        'performed_by' => auth()->id(),
    ],
    'schemaState' => fn (mixed $test, StockMovement $record): array => [
        'delta' => $record->delta,
        'quantity_after' => $record->quantity_after,
    ],
    'requiredValidation' => [
        'delta is required' => [['delta' => null], ['delta' => 'required']],
        'quantity after is required' => [['quantity_after' => null], ['quantity_after' => 'required']],
        'branch is required' => [['branch_id' => null], ['branch_id' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'medication_id' => $payload['medication_id'],
        'delta' => $payload['delta'],
        'reason' => $payload['reason'],
    ],
]);
