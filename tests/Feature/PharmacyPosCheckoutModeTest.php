<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Core\Enums\PosCheckoutMode;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Service;
use Modules\Core\Support\AppSettings;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages\PharmacyPos;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Settings\PharmacySettings;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The POS checkout mode decides whether the cashier collects payment (pay
 * now), only issues the bill for the Billing Desk (send to billing), or may
 * choose per sale. The chosen mode is enforced on the server, not just hidden.
 */
uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    config(['insurance.enabled' => false]);

    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing', 'Pharmacy']);

    $this->organization = Organization::factory()->create(['is_active' => true]);
    $this->branch = Branch::factory()->default()->create(['organization_id' => $this->organization->id, 'is_active' => true]);
    $this->patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id]));

    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    Permission::findOrCreate('View PharmacyPos', 'web');
    $this->user->givePermissionTo('View PharmacyPos');
    $this->actingAs($this->user);
});

function checkoutModeMedication(?Branch $branch = null, float $price = 25): Medication
{
    $test = test();
    $branch ??= $test->branch;

    $service = Service::factory()->create([
        'category_id' => $test->medicationServiceCategory()->id,
        'price' => $price,
        'is_active' => true,
        'branch_id' => $branch->id,
    ]);

    $medication = Medication::factory()->create(['service_id' => $service->id, 'is_active' => true]);

    StockItem::factory()->create([
        'branch_id' => $branch->id,
        'medication_id' => $medication->id,
        'quantity_on_hand' => 10,
    ]);

    return $medication;
}

function stockOnHand(Medication $medication, Branch $branch): int
{
    return (int) StockItem::query()->where('medication_id', $medication->id)->where('branch_id', $branch->id)->value('quantity_on_hand');
}

it('sends every sale to the billing desk when the branch is set to send to billing only', function (): void {
    Gate::before(fn (): bool => true);
    $this->branch->update(['pos_checkout_mode' => 'charge_account']);
    $medication = checkoutModeMedication();

    // A stale "pay now" from an earlier session must not survive.
    Cache::put('pharmacy_pos_user_'.$this->user->id.'_branch_'.$this->branch->id, ['items' => [], 'charge_mode' => 'pay_now'], 3600);

    $invoiceCount = Invoice::query()->withoutGlobalScopes()->count();
    $paymentCount = Payment::query()->withoutGlobalScopes()->count();

    $page = Livewire::test(PharmacyPos::class)
        ->assertSet('chargeMode', 'charge_account')
        ->assertSee('Payments are collected at the Billing Desk')
        ->assertDontSeeHtml('value="pay_now"')
        ->set('chargeMode', 'pay_now')
        ->assertNotified('Checkout mode not available')
        ->assertSet('chargeMode', 'charge_account')
        ->call('selectPatient', $this->patient->id)
        ->call('addToCart', $medication->id)
        ->call('checkout')
        ->assertNotified('Charge created');

    $invoice = Invoice::query()->withoutGlobalScopes()->latest('created_at')->firstOrFail();

    expect(Invoice::query()->withoutGlobalScopes()->count())->toBe($invoiceCount + 1)
        ->and(Payment::query()->withoutGlobalScopes()->count())->toBe($paymentCount)
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and(stockOnHand($medication, $this->branch))->toBe(9)
        ->and($page->instance()->allowedChargeModes())->toBe(['charge_account']);
});

it('collects payment at the point of sale when the branch is set to pay now only', function (): void {
    Gate::before(fn (): bool => true);
    $this->branch->update(['pos_checkout_mode' => 'pay_now']);
    $medication = checkoutModeMedication();
    $invoiceCount = Invoice::query()->withoutGlobalScopes()->count();

    $page = Livewire::test(PharmacyPos::class)
        ->assertSet('chargeMode', 'pay_now')
        ->assertSee('Payment is collected at the point of sale')
        ->assertDontSeeHtml('value="charge_account"')
        ->call('selectPatient', $this->patient->id)
        ->call('addToCart', $medication->id)
        ->call('checkoutChargeToAccount')
        ->assertNotified('Send to billing is not available');

    expect(Invoice::query()->withoutGlobalScopes()->count())->toBe($invoiceCount);

    $page->set('paymentMethod', 'cash')
        ->call('checkout')
        ->assertNotified('Checkout successful');

    $invoice = Invoice::query()->withoutGlobalScopes()->latest('created_at')->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and(Payment::query()->withoutGlobalScopes()->where('patient_id', $this->patient->id)->count())->toBe(1);
});

it('lets the cashier choose, pre-selects pay now and honours a switch to send to billing', function (): void {
    Gate::before(fn (): bool => true);
    $this->branch->update(['pos_checkout_mode' => 'cashier_chooses']);
    $medication = checkoutModeMedication();
    $paymentCount = Payment::query()->withoutGlobalScopes()->count();

    $page = Livewire::test(PharmacyPos::class)
        ->assertSet('chargeMode', 'pay_now')
        ->assertSeeHtml('value="pay_now"')
        ->assertSeeHtml('value="charge_account"')
        ->call('selectPatient', $this->patient->id)
        ->call('addToCart', $medication->id)
        ->set('chargeMode', 'charge_account')
        ->assertNotNotified('Checkout mode not available')
        ->assertSet('chargeMode', 'charge_account')
        ->call('checkout')
        ->assertNotified('Charge created')
        // After the sale the POS returns to the pre-selected mode.
        ->assertSet('chargeMode', 'pay_now');

    $invoice = Invoice::query()->withoutGlobalScopes()->latest('created_at')->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and(Payment::query()->withoutGlobalScopes()->count())->toBe($paymentCount)
        ->and($page->instance()->allowedChargeModes())->toBe(['pay_now', 'charge_account']);
});

it('only offers send to billing to a cashier who may not record payments', function (): void {
    $this->branch->update(['pos_checkout_mode' => 'cashier_chooses']);

    $page = Livewire::test(PharmacyPos::class)
        ->assertSet('chargeMode', 'charge_account')
        ->assertDontSeeHtml('value="pay_now"');

    expect($page->instance()->allowedChargeModes())->toBe(['charge_account']);
});

it('re-derives the mode when the cashier switches branch', function (): void {
    Gate::before(fn (): bool => true);
    $this->branch->update(['pos_checkout_mode' => 'cashier_chooses']);
    $billingOnly = Branch::factory()->create(['organization_id' => $this->organization->id, 'is_active' => true, 'pos_checkout_mode' => 'charge_account']);

    Livewire::test(PharmacyPos::class)
        ->assertSet('chargeMode', 'pay_now')
        ->set('selectedBranchId', $billingOnly->id)
        ->assertSet('chargeMode', 'charge_account')
        ->assertDontSeeHtml('value="pay_now"');
});

it('falls back to the global pharmacy setting when neither branch nor organization overrides it', function (): void {
    $this->branch->update(['pos_checkout_mode' => null]);
    $this->organization->update(['pos_checkout_mode' => null]);

    PharmacySettings::fake(['pos_checkout_mode' => 'pay_now']);
    expect(AppSettings::forBranch($this->branch->id)->pharmacyPosCheckoutMode())->toBe(PosCheckoutMode::PayNow);

    PharmacySettings::fake(['pos_checkout_mode' => 'charge_account']);
    expect(AppSettings::forBranch($this->branch->id)->pharmacyPosCheckoutMode())->toBe(PosCheckoutMode::ChargeAccount);

    $this->organization->update(['pos_checkout_mode' => 'cashier_chooses']);
    expect(AppSettings::forBranch($this->branch->id)->pharmacyPosCheckoutMode())->toBe(PosCheckoutMode::CashierChooses);
});
