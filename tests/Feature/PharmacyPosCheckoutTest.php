<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Core\Enums\BillingType;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Organization;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Core\Support\AppSettings;
use Modules\Pharmacy\Classes\Services\PharmacyPosCheckoutService;
use Modules\Pharmacy\Exceptions\InsufficientStockException;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PharmacyPosCheckoutTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing', 'Pharmacy']);
    }

    public function test_it_processes_guest_checkout_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        $result = app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Walk-in Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 2],
            ],
        ]);

        $this->assertNotNull($result['invoice']->id);
        $this->assertNotNull($result['payment']->id);
        $this->assertSame($result['invoice']->guest_name, 'Walk-in Customer');
        $this->assertSame($result['invoice']->status, InvoiceStatus::Paid);

        $this->assertDatabaseHas('stock_items', [
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 8,
        ]);

        $this->assertDatabaseHas('invoice_lines', [
            'invoice_id' => $result['invoice']->id,
            'billable_type' => $medication->getMorphClass(),
            'billable_id' => $medication->id,
            'service_id' => $service->id,
            'quantity' => 2,
        ]);
    }

    public function test_it_blocks_controlled_substances(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 50.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
            'controlled_schedule' => 'schedule_2',
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 5,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('controlled substance');

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_it_throws_on_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 1,
        ]);

        $this->expectException(InsufficientStockException::class);

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 5],
            ],
        ]);
    }

    public function test_it_applies_pos_discount_to_invoice_and_payment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        $result = app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Walk-in Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'pos_discount_amount' => '5.00',
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 2],
            ],
        ]);

        $this->assertSame('50.00', $result['invoice']->fresh()->subtotal);
        $this->assertSame('5.00', $result['invoice']->fresh()->discount_total);
        $this->assertSame('45.00', $result['invoice']->fresh()->total);
        $this->assertSame('45.00', $result['payment']->fresh()->amount);
    }

    public function test_it_rejects_discount_exceeding_subtotal(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Discount cannot exceed');

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'pos_discount_amount' => '25.00',
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 2],
            ],
        ]);
    }

    public function test_it_rejects_insufficient_tender(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 20.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount tendered is less than the amount due');

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'amount_tendered' => '10.00',
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_checkout_result_contains_payment_for_receipt_url(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        $result = app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Walk-in Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
            ],
        ]);

        $this->assertNotNull($result['payment']->id);
        $this->assertNotNull($result['invoice']->id);
        $receiptUrl = route('billing.payments.receipt', ['payment' => $result['payment']]);
        $this->assertStringContainsString((string) $result['payment']->id, $receiptUrl);
    }

    public function test_receipt_route_accessible_with_print_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        $result = app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Walk-in Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
            ],
        ]);

        Permission::firstOrCreate(['name' => 'print_receipt', 'guard_name' => 'web']);
        $user->givePermissionTo('print_receipt');

        $response = $this->get(route('billing.payments.receipt', $result['payment']));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_checkout_charge_to_account_creates_unpaid_invoice_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $organization = Organization::factory()->create([
            'pharmacy_pos_collect_payment' => true,
        ]);

        $branch = Branch::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'pharmacy_pos_collect_payment' => false,
        ]);

        $category = $this->medicationServiceCategory();

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 25.00,
            'is_active' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        $settings = AppSettings::forBranch($branch->id);
        $this->assertFalse($settings->pharmacyPosCollectPaymentEnabled());

        $result = app(PharmacyPosCheckoutService::class)->checkoutChargeToAccount([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Walk-in Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 2],
            ],
        ]);

        $this->assertNotNull($result['invoice']->id);
        $this->assertNull($result['payment']);
        $this->assertSame(InvoiceStatus::Issued, $result['invoice']->status);

        $this->assertDatabaseHas('stock_items', [
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 8,
        ]);
    }

    public function test_service_checkout_processes_fixed_service_from_correct_branch(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = ServiceCategory::factory()->create(['code' => 'CON']);

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'billing_type' => BillingType::FIXED,
            'price' => 50.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $result = app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Service Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'amount_tendered' => 50.00,
            'cart' => [
                ['type' => 'service', 'id' => $service->id, 'quantity' => 1],
            ],
        ]);

        $this->assertNotNull($result['invoice']->id);
        $this->assertNotNull($result['payment']);
        $this->assertCount(1, $result['invoice']->lines);
        $this->assertSame('50.00', $result['invoice']->lines->first()->unit_price);
    }

    public function test_service_checkout_rejects_time_based_service(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = ServiceCategory::factory()->create(['code' => 'CON']);

        $service = Service::factory()->timeBased()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'price' => 0,
            'time_rate_per_minute' => 2.50,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service {$service->id} not found or inactive.");

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Service Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'amount_tendered' => 50.00,
            'cart' => [
                ['type' => 'service', 'id' => $service->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_service_checkout_rejects_wrong_branch_service(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branchA = Branch::factory()->create(['is_active' => true]);
        $branchB = Branch::factory()->create(['is_active' => true]);
        $category = ServiceCategory::factory()->create(['code' => 'CON']);

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'branch_id' => $branchB->id,
            'price' => 50.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service {$service->id} not found or inactive.");

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branchA->id,
            'patient_id' => null,
            'guest_name' => 'Service Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'amount_tendered' => 50.00,
            'cart' => [
                ['type' => 'service', 'id' => $service->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_charge_to_account_rejects_time_based_service(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $organization = Organization::factory()->create();
        $branch = Branch::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $category = ServiceCategory::factory()->create(['code' => 'CON']);

        $service = Service::factory()->timeBased()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'price' => 0,
            'time_rate_per_minute' => 2.50,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service {$service->id} not found or inactive.");

        app(PharmacyPosCheckoutService::class)->checkoutChargeToAccount([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Service Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'cart' => [
                ['type' => 'service', 'id' => $service->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_charge_to_account_rejects_wrong_branch_service(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $organization = Organization::factory()->create();
        $branchA = Branch::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $branchB = Branch::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
        ]);
        $category = ServiceCategory::factory()->create(['code' => 'CON']);

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'branch_id' => $branchB->id,
            'price' => 50.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Service {$service->id} not found or inactive.");

        app(PharmacyPosCheckoutService::class)->checkoutChargeToAccount([
            'branch_id' => $branchA->id,
            'patient_id' => null,
            'guest_name' => 'Service Customer',
            'guest_phone' => '233501234567',
            'currency' => 'GHS',
            'cart' => [
                ['type' => 'service', 'id' => $service->id, 'quantity' => 1],
            ],
        ]);
    }
}
