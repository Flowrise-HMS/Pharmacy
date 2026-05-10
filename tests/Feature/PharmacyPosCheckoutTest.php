<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Pharmacy\Classes\Services\PharmacyPosCheckoutService;
use Modules\Pharmacy\Exceptions\InsufficientStockException;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Tests\TestCase;

class PharmacyPosCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Patient', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);
    }

    public function test_it_processes_guest_checkout_and_decrements_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = ServiceCategory::factory()->create(['code' => 'MED']);

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
                ['medication_id' => $medication->id, 'quantity' => 2],
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
        $category = ServiceCategory::factory()->create(['code' => 'MED']);

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
                ['medication_id' => $medication->id, 'quantity' => 1],
            ],
        ]);
    }

    public function test_it_throws_on_insufficient_stock(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = ServiceCategory::factory()->create(['code' => 'MED']);

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
                ['medication_id' => $medication->id, 'quantity' => 5],
            ],
        ]);
    }

    public function test_it_applies_pos_discount_to_invoice_and_payment(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = ServiceCategory::factory()->create(['code' => 'MED']);

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
                ['medication_id' => $medication->id, 'quantity' => 2],
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
        $category = ServiceCategory::factory()->create(['code' => 'MED']);

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
                ['medication_id' => $medication->id, 'quantity' => 2],
            ],
        ]);
    }

    public function test_it_rejects_insufficient_tender(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = ServiceCategory::factory()->create(['code' => 'MED']);

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
                ['medication_id' => $medication->id, 'quantity' => 1],
            ],
        ]);
    }
}
