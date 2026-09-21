<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages\ManagePharmacySettings;
use Modules\Pharmacy\Settings\PharmacySettings;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Pharmacy']);
    Permission::findOrCreate('View ManagePharmacySettings', 'web');
    $this->admin = User::factory()->create()->givePermissionTo('View ManagePharmacySettings');
});

it('offers one POS checkout mode instead of the pay-now toggle and default charge mode', function (): void {
    expect(app(PharmacySettings::class)->pos_checkout_mode)->toBe('cashier_chooses');

    Livewire::actingAs($this->admin)
        ->test(ManagePharmacySettings::class)
        ->assertFormFieldExists('pos_checkout_mode')
        ->assertFormFieldDoesNotExist('pos_collect_payment')
        ->assertFormFieldDoesNotExist('pos_default_charge_mode')
        ->fillForm(['pos_checkout_mode' => 'pay_now'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(app(PharmacySettings::class)->pos_checkout_mode)->toBe('pay_now');
});
