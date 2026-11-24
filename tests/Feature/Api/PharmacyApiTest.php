<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Tests\Support\ApiResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

$modules = ['Core', 'Patient', 'Billing', 'Pharmacy'];

describe('medications', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'medications',
        'subject' => 'Medication',
        'scope' => 'pharmacy',
        'modules' => $modules,
        'branchIsolated' => false,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => Medication::factory()->create($attributes),
        'store' => fn (TestCase $test, Branch $branch): array => ['generic_name' => 'Paracetamol', 'strength' => '500mg', 'dosage_form' => 'tablet', 'price' => 2.5],
        'invalid' => [['generic_name' => 'X', 'dosage_form' => 'tablet'], 'price'],
        'update' => fn (TestCase $test, $record): array => ['brand_name' => 'Panadol'],
        'destroy' => false,
    ]);
});

describe('pharmacy stock', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'pharmacy-stock',
        'subject' => 'StockItem',
        'scope' => 'pharmacy',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => StockItem::factory()->create(['branch_id' => $branch->id, ...$attributes]),
    ]);
});
