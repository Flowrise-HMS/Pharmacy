<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Settings\BillingSettings;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\PharmacyPosCheckoutService;
use Modules\Pharmacy\Classes\Services\StockService;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages\PharmacyPos;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Settings\PharmacySettings;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    config(['insurance.enabled' => false]);

    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing', 'Pharmacy']);

    $settings = app(BillingSettings::class);
    $settings->financial_hold_enabled = true;
    $settings->save();

    $this->branch = Branch::factory()->default()->create(['is_active' => true, 'pharmacy_pos_collect_payment' => true]);
    $this->patient = Patient::withoutEvents(fn (): Patient => Patient::factory()->create(['branch_id' => $this->branch->id]));
    $this->encounter = EncounterFactory::new()->create(['patient_id' => $this->patient->id, 'branch_id' => $this->branch->id]);

    $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    Permission::findOrCreate('View PharmacyPos', 'web');
    $this->user->givePermissionTo('View PharmacyPos');
    $this->actingAs($this->user);
});

/**
 * @return array{0: RequestItem, 1: InvoiceLine, 2: Service}
 */
function orderedService(float $price = 80, string $name = 'Chest X-Ray'): array
{
    $test = test();

    $service = Service::factory()
        ->forCategory($test->serviceCategory(['code' => 'RAD']))
        ->create(['name' => $name, 'price' => $price, 'requires_payment_before' => true, 'branch_id' => $test->branch->id]);

    $request = ServiceRequestFactory::new()->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
        'encounter_id' => $test->encounter->id,
    ]);

    $item = RequestItemFactory::new()->create([
        'service_request_id' => $request->id,
        'service_id' => $service->id,
        'unit_price' => $price,
        'total_price' => $price,
    ]);

    $line = InvoiceLine::query()
        ->where('billable_type', (new RequestItem)->getMorphClass())
        ->where('billable_id', $item->id)
        ->firstOrFail();

    return [$item->fresh(), $line, $service];
}

function walkInMedication(float $price = 25): Medication
{
    $test = test();

    $service = Service::factory()->create([
        'category_id' => $test->medicationServiceCategory()->id,
        'price' => $price,
        'is_active' => true,
        'branch_id' => $test->branch->id,
    ]);

    $medication = Medication::factory()->create(['service_id' => $service->id, 'is_active' => true]);

    StockItem::factory()->create([
        'branch_id' => $test->branch->id,
        'medication_id' => $medication->id,
        'quantity_on_hand' => 10,
    ]);

    return $medication;
}

it('pays an ordered service on its existing invoice line instead of billing it again', function (): void {
    [$item, $line] = orderedService(80);
    $invoiceCount = Invoice::query()->withoutGlobalScopes()->count();

    $result = app(PharmacyPosCheckoutService::class)->checkout([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'currency' => 'GHS',
        'payment_method' => PaymentMethod::Cash,
        'amount_tendered' => '100.00',
        'cart' => [
            ['type' => 'charge', 'id' => $line->id, 'quantity' => 1, 'invoice_line_id' => $line->id],
        ],
    ]);

    expect(Invoice::query()->withoutGlobalScopes()->count())->toBe($invoiceCount)
        ->and($line->fresh()->line_status)->toBe(InvoiceLineStatus::Paid)
        ->and($line->invoice()->withoutGlobalScopes()->first()->status)->toBe(InvoiceStatus::Paid)
        ->and($item->fresh()->payment_status)->toBe(InvoiceLineStatus::Paid)
        ->and($item->fresh()->hasActiveFinancialHold())->toBeFalse()
        ->and(InvoiceLine::query()->where('service_id', $line->service_id)->count())->toBe(1)
        ->and($result['grand_total'])->toBe('80.00')
        ->and($result['payments'])->toHaveCount(1)
        ->and($result['payment']->metadata['change_due'] ?? null)->toBe('20.00')
        ->and($result['invoice']->id)->toBe($line->invoice_id);
});

it('settles ordered charges and sells walk-in items in one checkout', function (): void {
    [$item, $line] = orderedService(80);
    $medication = walkInMedication(25);
    $invoiceCount = Invoice::query()->withoutGlobalScopes()->count();

    $result = app(PharmacyPosCheckoutService::class)->checkout([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'currency' => 'GHS',
        'payment_method' => PaymentMethod::Cash,
        'cart' => [
            ['type' => 'charge', 'id' => $line->id, 'quantity' => 1, 'invoice_line_id' => $line->id],
            ['type' => 'medication', 'id' => $medication->id, 'quantity' => 2],
        ],
    ]);

    expect(Invoice::query()->withoutGlobalScopes()->count())->toBe($invoiceCount + 1)
        ->and($result['grand_total'])->toBe('130.00')
        ->and($result['payments'])->toHaveCount(2)
        ->and($result['invoice']->invoice_type->value)->toBe('standalone')
        ->and($result['invoice']->status)->toBe(InvoiceStatus::Paid)
        ->and($item->fresh()->payment_status)->toBe(InvoiceLineStatus::Paid)
        ->and(StockItem::query()->where('medication_id', $medication->id)->value('quantity_on_hand'))->toBe(8);
});

it('rejects tender below the combined total and does not touch stock for charge rows', function (): void {
    [, $line] = orderedService(80);
    $medication = walkInMedication(25);

    expect(fn () => app(PharmacyPosCheckoutService::class)->checkout([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'currency' => 'GHS',
        'payment_method' => PaymentMethod::Cash,
        'amount_tendered' => '100.00',
        'cart' => [
            ['type' => 'charge', 'id' => $line->id, 'quantity' => 1, 'invoice_line_id' => $line->id],
            ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'Amount tendered is less than the amount due.');

    expect($line->fresh()->line_status)->toBe(InvoiceLineStatus::Unpaid)
        ->and(StockItem::query()->where('medication_id', $medication->id)->value('quantity_on_hand'))->toBe(10)
        ->and(Payment::query()->count())->toBe(0);
});

it('refuses a charge that is no longer pending', function (): void {
    [, $line] = orderedService(80);
    $line->update(['amount_paid' => 80, 'line_status' => InvoiceLineStatus::Paid]);

    expect(fn () => app(PharmacyPosCheckoutService::class)->checkout([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'currency' => 'GHS',
        'payment_method' => PaymentMethod::Cash,
        'cart' => [
            ['type' => 'charge', 'id' => $line->id, 'quantity' => 1, 'invoice_line_id' => $line->id],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'no longer pending');
});

it('ignores charge rows when posting to account and refuses an empty posting', function (): void {
    [, $line] = orderedService(80);
    $medication = walkInMedication(25);
    $invoiceCount = Invoice::query()->withoutGlobalScopes()->count();

    $result = app(PharmacyPosCheckoutService::class)->checkoutChargeToAccount([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'currency' => 'GHS',
        'cart' => [
            ['type' => 'charge', 'id' => $line->id, 'quantity' => 1, 'invoice_line_id' => $line->id],
            ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
        ],
    ]);

    expect(Invoice::query()->withoutGlobalScopes()->count())->toBe($invoiceCount + 1)
        ->and($result['invoice']->lines)->toHaveCount(1)
        ->and($line->fresh()->line_status)->toBe(InvoiceLineStatus::Unpaid);

    expect(fn () => app(PharmacyPosCheckoutService::class)->checkoutChargeToAccount([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'currency' => 'GHS',
        'cart' => [
            ['type' => 'charge', 'id' => $line->id, 'quantity' => 1, 'invoice_line_id' => $line->id],
        ],
    ]))->toThrow(InvalidArgumentException::class, 'already on the patient');
});

it('lists pending charges for the selected patient and links them into the cart', function (): void {
    Gate::before(fn (): bool => true);

    [, $line, $service] = orderedService(80, 'Abdominal Ultrasound');

    $page = Livewire::test(PharmacyPos::class)
        ->call('selectPatient', $this->patient->id)
        ->assertSee('Pending charges')
        ->assertSee('Abdominal Ultrasound')
        ->call('addChargeToCart', (string) $line->id);

    $cart = collect($page->get('cart'));

    expect($cart)->toHaveKey('c'.$line->id)
        ->and($cart['c'.$line->id]['type'])->toBe('charge')
        ->and((float) $cart['c'.$line->id]['price'])->toBe(80.0)
        ->and($page->get('chargeMode'))->toBe('pay_now')
        ->and((float) $page->get('grandTotal'))->toBe(80.0);

    // Adding the same charge twice does nothing; the quantity cannot be changed.
    $page->call('addChargeToCart', (string) $line->id)
        ->call('updateQuantity', 'c'.$line->id, 3);

    expect(collect($page->get('cart')))->toHaveCount(1)
        ->and(collect($page->get('cart'))['c'.$line->id]['quantity'])->toBe(1);

    // Manually adding the same service auto-links to the pending order instead of a new line.
    $page->call('removeFromCart', 'c'.$line->id)
        ->call('addServiceToCart', $service->id)
        ->assertNotified('Linked to existing order');

    $cart = collect($page->get('cart'));
    expect($cart)->toHaveKey('c'.$line->id)
        ->and($cart)->not->toHaveKey('s'.$service->id);

    // Charge rows persist in the cart cache together with the charge mode.
    $cacheKey = 'pharmacy_pos_user_'.$this->user->id.'_branch_'.$this->branch->id;
    $cached = Cache::get($cacheKey);

    expect($cached['charge_mode'] ?? null)->toBe('pay_now')
        ->and($cached['items']['c'.$line->id]['invoice_line_id'] ?? null)->toBe((string) $line->id);
});

it('adds a plain service row when the patient has no matching pending charge', function (): void {
    Gate::before(fn (): bool => true);

    $service = Service::factory()
        ->forCategory($this->serviceCategory(['code' => 'RAD']))
        ->create(['name' => 'Walk-in ECG', 'price' => 40, 'is_active' => true, 'is_billable' => true, 'branch_id' => $this->branch->id]);

    $page = Livewire::test(PharmacyPos::class)
        ->call('selectPatient', $this->patient->id)
        ->call('addServiceToCart', $service->id);

    expect(collect($page->get('cart')))->toHaveKey('s'.$service->id);
});

it('pays pending charges from the point of sale page', function (): void {
    Gate::before(fn (): bool => true);

    [$item, $line] = orderedService(80);

    Livewire::test(PharmacyPos::class)
        ->call('selectPatient', $this->patient->id)
        ->call('addChargeToCart', (string) $line->id)
        ->set('chargeMode', 'pay_now')
        ->set('paymentMethod', 'cash')
        ->call('checkout')
        ->assertNotified('Checkout successful');

    expect($line->fresh()->line_status)->toBe(InvoiceLineStatus::Paid)
        ->and($item->fresh()->payment_status)->toBe(InvoiceLineStatus::Paid)
        ->and($item->fresh()->hasActiveFinancialHold())->toBeFalse();
});

it('offers a print receipt button and auto-prints only when the checkbox is on', function (): void {
    Gate::before(fn (): bool => true);

    [, $line] = orderedService(80);

    Livewire::test(PharmacyPos::class)
        ->call('selectPatient', $this->patient->id)
        ->call('addChargeToCart', (string) $line->id)
        ->set('chargeMode', 'pay_now')
        ->set('paymentMethod', 'cash')
        ->set('autoPrintReceipt', false)
        ->call('checkout')
        ->assertNotified('Checkout successful')
        ->assertNotDispatched('pos-open-receipt');

    [, $secondLine] = orderedService(20, 'Urinalysis');

    Livewire::test(PharmacyPos::class)
        ->call('selectPatient', $this->patient->id)
        ->call('addChargeToCart', (string) $secondLine->id)
        ->set('chargeMode', 'pay_now')
        ->set('paymentMethod', 'cash')
        ->set('autoPrintReceipt', true)
        ->call('checkout')
        ->assertNotified('Checkout successful')
        ->assertDispatched('pos-open-receipt');
});

it('keeps controlled substances off the point of sale when the setting is on', function (): void {
    Gate::before(fn (): bool => true);
    PharmacySettings::fake(['block_controlled_on_pos' => true]);

    $medication = walkInMedication(30);
    $medication->update(['controlled_schedule' => ControlledSchedule::SCHEDULE_2]);

    $page = Livewire::test(PharmacyPos::class)
        ->call('addToCart', $medication->id)
        ->assertNotified('Controlled substance');

    expect(collect($page->get('cart')))->toBeEmpty()
        ->and($page->instance()->blocksControlledSubstances())->toBeTrue();

    PharmacySettings::fake(['block_controlled_on_pos' => false]);

    $page = Livewire::test(PharmacyPos::class)
        ->call('addToCart', $medication->id);

    expect(collect($page->get('cart')))->not->toBeEmpty();
});

it('uses the configured default reorder point for new stock items', function (): void {
    PharmacySettings::fake(['default_reorder_point' => 25]);

    $medication = walkInMedication(30);
    StockItem::query()->where('medication_id', $medication->id)->delete();

    app(StockService::class)->increment($this->branch->id, $medication->id, 5, 'test');

    expect(StockItem::query()->where('medication_id', $medication->id)->value('reorder_point'))->toBe(25);
});
