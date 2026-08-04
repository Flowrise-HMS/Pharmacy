<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages\PharmacyPos;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['insurance.enabled' => false]);

    $this->migrateModules(['Core', 'Patient', 'Billing', 'Pharmacy']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

    Permission::findOrCreate('View PharmacyPos', 'web');
    $this->user->givePermissionTo('View PharmacyPos');
});

it('finds patients by middle name through the shared patient search service', function (): void {
    $this->actingAs($this->user);

    $match = Patient::withoutEvents(fn (): Patient => Patient::factory()->create([
        'branch_id' => $this->branch->id,
        'first_name' => 'Ama',
        'middle_name' => 'PosMiddleUnique',
        'last_name' => 'Mensah',
    ]));

    Patient::withoutEvents(fn (): Patient => Patient::factory()->create([
        'branch_id' => $this->branch->id,
        'first_name' => 'Other',
        'middle_name' => null,
        'last_name' => 'Patient',
    ]));

    $component = Livewire::test(PharmacyPos::class)
        ->set('patientSearch', 'PosMiddleUnique');

    $ids = collect($component->get('patientResults'))->pluck('id');

    expect($ids)->toContain($match->id);
});

it('finds patients by full name including middle name through the shared patient search service', function (): void {
    $this->actingAs($this->user);

    $match = Patient::withoutEvents(fn (): Patient => Patient::factory()->create([
        'branch_id' => $this->branch->id,
        'first_name' => 'Kwame',
        'middle_name' => 'Kofi',
        'last_name' => 'AsantePosFull',
    ]));

    Patient::withoutEvents(fn (): Patient => Patient::factory()->create([
        'branch_id' => $this->branch->id,
        'first_name' => 'Other',
        'middle_name' => null,
        'last_name' => 'Patient',
    ]));

    $component = Livewire::test(PharmacyPos::class)
        ->set('patientSearch', 'Kwame Kofi AsantePosFull');

    $ids = collect($component->get('patientResults'))->pluck('id');

    expect($ids)->toContain($match->id);
});

it('clears patient results for short search terms', function (): void {
    $this->actingAs($this->user);

    Patient::withoutEvents(fn (): Patient => Patient::factory()->create([
        'branch_id' => $this->branch->id,
        'first_name' => 'Short',
        'last_name' => 'Term',
    ]));

    $component = Livewire::test(PharmacyPos::class)
        ->set('patientSearch', 'S');

    expect(collect($component->get('patientResults')))->toBeEmpty();
});
