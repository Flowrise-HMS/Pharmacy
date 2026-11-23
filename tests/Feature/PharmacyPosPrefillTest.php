<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages\PharmacyPos;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    config(['insurance.enabled' => false]);
    $this->migrateModules(['Core', 'Patient', 'Billing', 'Pharmacy']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    Permission::findOrCreate('View PharmacyPos', 'web');
    $this->user->givePermissionTo('View PharmacyPos');
    $this->actingAs($this->user);
});

function stockedMedication(Branch $branch, int $stock = 50, array $attributes = []): Medication
{
    $medication = Medication::factory()->create([
        'service_id' => Service::factory()->create(['price' => 12.5, 'is_active' => true]),
        ...$attributes,
    ]);
    StockItem::factory()->create(['medication_id' => $medication->id, 'branch_id' => $branch->id, 'quantity_on_hand' => $stock]);

    return $medication;
}

it('builds a prefill URL', function (): void {
    $url = PharmacyPos::prefillUrl(['med-a' => 2, 'med-b' => 0, 'med-c' => 1], 'patient-1');

    expect(urldecode($url))->toContain('prefill=med-a:2,med-c:1')
        ->and($url)->toContain('patient=patient-1');
});

it('fills the cart from the URL with quantities capped by stock', function (): void {
    $paracetamol = stockedMedication($this->branch, 50);
    $amoxicillin = stockedMedication($this->branch, 3);

    $cart = Livewire::withQueryParams(['prefill' => "{$paracetamol->id}:2,{$amoxicillin->id}:10,unknown:1"])
        ->test(PharmacyPos::class)
        ->get('cart');

    expect($cart->get('m'.$paracetamol->id)['quantity'])->toBe(2)
        ->and($cart->get('m'.$amoxicillin->id)['quantity'])->toBe(1)
        ->and($cart)->toHaveCount(2);
});

it('applies the controlled-substance block to prefilled lines', function (): void {
    Modules\Pharmacy\Settings\PharmacySettings::fake(['block_controlled_on_pos' => true]);
    $morphine = stockedMedication($this->branch, 10, ['controlled_schedule' => ControlledSchedule::SCHEDULE_2]);

    $cart = Livewire::withQueryParams(['prefill' => "{$morphine->id}:1"])
        ->test(PharmacyPos::class)
        ->get('cart');

    expect($cart)->toHaveCount(0);
});
