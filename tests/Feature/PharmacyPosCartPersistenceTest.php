<?php

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages\PharmacyPos;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config(['cache.serializable_classes' => false]);
    config(['cache.default' => 'database']);
    Cache::purge('database');
    Cache::purge();
    config(['insurance.enabled' => false]);

    $this->migrateModules(['Core', 'Patient', 'Billing', 'Pharmacy']);

    $this->branch = Branch::factory()->default()->create();
    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

    Permission::findOrCreate('View PharmacyPos', 'web');
    $this->user->givePermissionTo('View PharmacyPos');
});

it('persists and restores the cart across page reloads', function (): void {
    $this->actingAs($this->user);

    expect(Cache::store())->toBeInstanceOf(\Illuminate\Cache\DatabaseStore::class);

    $cart = collect([
        'm1' => [
            'type' => 'medication',
            'id' => 1,
            'name' => 'Paracetamol',
            'price' => 5.0,
            'quantity' => 2,
            'unit_label' => 'tab',
        ],
        's1' => [
            'type' => 'service',
            'id' => 9,
            'name' => 'Consultation',
            'price' => 50.0,
            'quantity' => 1,
            'unit_label' => 'session',
        ],
    ]);

    Livewire::test(PharmacyPos::class)
        ->set('cart', $cart)
        ->call('removeFromCart', 'm999');

    $restored = Livewire::test(PharmacyPos::class)->get('cart');

    expect($restored)->toBeInstanceOf(Collection::class)
        ->and($restored->get('m1'))->toMatchArray(['quantity' => 2, 'price' => 5.0])
        ->and($restored->get('s1'))->toMatchArray(['quantity' => 1]);
});

it('does not crash when restoring a legacy payload with a serialized collection', function (): void {
    $this->actingAs($this->user);

    $key = 'pharmacy_pos_user_'.$this->user->id.'_branch_'.$this->branch->id;

    Cache::put($key, [
        'items' => collect(['m1' => ['type' => 'medication', 'id' => 1, 'quantity' => 2]]),
    ], 60);

    $restored = Livewire::test(PharmacyPos::class)->get('cart');

    expect($restored)->toBeInstanceOf(Collection::class)
        ->and($restored)->toBeEmpty();
});
