<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\DrugResource;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\CreateDrug;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\EditDrug;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\ListDrugs;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\ViewDrug;
use Modules\Pharmacy\Models\Drug;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Pharmacy');
    $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
});

FilamentResourceTestSuite::register([
    'resource' => DrugResource::class,
    'subject' => 'Drug',
    'model' => Drug::class,
    'listPage' => ListDrugs::class,
    'createPage' => CreateDrug::class,
    'editPage' => EditDrug::class,
    'viewPage' => ViewDrug::class,
    'searchColumn' => 'generic_name',
    'hasBulkDelete' => true,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'makeRecord' => fn (TestCase $test, array $attributes = []): Drug => Drug::factory()->create([
        'synonyms' => null,
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $records->push(Drug::factory()->create([
                'generic_name' => 'Generic '.$index.' '.fake()->unique()->lexify('????'),
                'display_name' => 'Display '.$index.' '.fake()->unique()->lexify('????'),
                'synonyms' => null,
            ]));
        }

        return $records;
    },
    'createForm' => fn (): array => [
        'source_provider' => 'local',
        'source_identifier' => (string) fake()->unique()->numberBetween(100000, 999999),
        'generic_name' => 'Amoxicillin',
        'display_name' => 'Amoxicillin 500 MG Capsule',
        'is_cached_external' => false,
        'is_active' => true,
    ],
    'updateForm' => fn (): array => [
        'display_name' => 'Updated amoxicillin display',
        'generic_name' => 'Amoxicillin',
    ],
    'schemaState' => fn (mixed $test, Drug $record): array => [
        'generic_name' => $record->generic_name,
        'display_name' => $record->display_name,
    ],
    'requiredValidation' => [
        'source provider is required' => [['source_provider' => null], ['source_provider' => 'required']],
        'generic name is required' => [['generic_name' => null], ['generic_name' => 'required']],
        'display name is required' => [['display_name' => null], ['display_name' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'generic_name' => $payload['generic_name'],
        'display_name' => $payload['display_name'],
        'source_provider' => $payload['source_provider'],
    ],
]);
