<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\CreateStockItem;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\EditStockItem;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\ListStockItems;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\ViewStockItem;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\StockItemResource;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
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
    'resource' => StockItemResource::class,
    'subject' => 'StockItem',
    'model' => StockItem::class,
    'listPage' => ListStockItems::class,
    'createPage' => CreateStockItem::class,
    'editPage' => EditStockItem::class,
    'viewPage' => ViewStockItem::class,
    'sortColumn' => 'quantity_on_hand',
    'hasBulkDelete' => true,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): StockItem => StockItem::factory()->create([
        'branch_id' => $test->branch->id,
        'medication_id' => $test->medication->id,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => StockItem::factory()->count($count)->create([
        'branch_id' => $test->branch->id,
    ]),
    'createForm' => fn (TestCase $test): array => [
        'medication_id' => $test->medication->id,
        'branch_id' => $test->branch->id,
        'quantity_on_hand' => 40,
        'reorder_point' => 10,
    ],
    'updateForm' => fn (TestCase $test): array => [
        'quantity_on_hand' => 55,
        'reorder_point' => 12,
    ],
    'schemaState' => fn (mixed $test, StockItem $record): array => [
        'quantity_on_hand' => $record->quantity_on_hand,
        'reorder_point' => $record->reorder_point,
    ],
    'requiredValidation' => [
        'medication is required' => [['medication_id' => null], ['medication_id' => 'required']],
        'branch is required' => [['branch_id' => null], ['branch_id' => 'required']],
        'quantity is required' => [['quantity_on_hand' => null], ['quantity_on_hand' => 'required']],
        'reorder point is required' => [['reorder_point' => null], ['reorder_point' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'medication_id' => $payload['medication_id'],
        'branch_id' => $payload['branch_id'],
        'quantity_on_hand' => $payload['quantity_on_hand'],
    ],
]);
