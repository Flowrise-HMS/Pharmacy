<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Pharmacy\Classes\Services\DispenseService;
use Modules\Pharmacy\Exceptions\DuplicateDispenseException;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DispenseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);
    }

    public function test_it_dispenses_and_reduces_stock_for_authorized_pharmacy_user(): void
    {
        [$branch, $service, $requestItem, $medication] = $this->seedDispenseFixture(false);
        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 12,
        ]);

        $pharmacyUser = User::factory()->create(['branch_id' => $branch->id]);
        Role::findOrCreate('pharmacist', 'web');
        $pharmacyUser->assignRole('pharmacist');

        $dispense = app(DispenseService::class)->dispense($requestItem, [
            'medication_id' => $medication->id,
            'quantity' => 2,
            'notes' => 'Dispensed by pharmacy',
        ], $pharmacyUser);

        $this->assertNotNull($dispense->id);
        $this->assertDatabaseHas('stock_items', [
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);
        $this->assertDatabaseHas('request_items', [
            'id' => $requestItem->id,
            'status' => 'completed',
        ]);
    }

    public function test_it_blocks_dispense_for_non_pharmacy_user(): void
    {
        [, , $requestItem, $medication] = $this->seedDispenseFixture(false);
        $regularUser = User::factory()->create();

        $this->expectException(UnauthorizedMedicationOrderException::class);

        app(DispenseService::class)->dispense($requestItem, [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $regularUser);
    }

    public function test_it_blocks_dispense_when_payment_is_required_but_not_settled(): void
    {
        [, , $requestItem, $medication] = $this->seedDispenseFixture(true);
        $pharmacyUser = User::factory()->create();
        Role::findOrCreate('pharmacist', 'web');
        $pharmacyUser->assignRole('pharmacist');

        $this->expectException(UnauthorizedMedicationOrderException::class);

        app(DispenseService::class)->dispense($requestItem, [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $pharmacyUser);
    }

    public function test_in_facility_supply_dispense_does_not_complete_order(): void
    {
        [$branch, , $requestItem, $medication] = $this->seedDispenseFixture(false);
        $requestItem->prescriptionDetail->update([
            'administration_context' => \Modules\Pharmacy\Enums\AdministrationContext::IN_FACILITY,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 12,
        ]);

        $pharmacyUser = User::factory()->create(['branch_id' => $branch->id]);
        Role::findOrCreate('pharmacist', 'web');
        $pharmacyUser->assignRole('pharmacist');

        app(DispenseService::class)->dispense($requestItem, [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $pharmacyUser);

        $this->assertDatabaseHas('request_items', [
            'id' => $requestItem->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_it_blocks_duplicate_dispense_for_same_request_item(): void
    {
        [$branch, , $requestItem, $medication] = $this->seedDispenseFixture(false);
        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 12,
        ]);

        $pharmacyUser = User::factory()->create(['branch_id' => $branch->id]);
        Role::findOrCreate('pharmacist', 'web');
        $pharmacyUser->assignRole('pharmacist');

        app(DispenseService::class)->dispense($requestItem, [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $pharmacyUser);

        $this->expectException(DuplicateDispenseException::class);

        app(DispenseService::class)->dispense($requestItem, [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $pharmacyUser);
    }

    /**
     * @return array{Branch, Service, RequestItem, Medication}
     */
    protected function seedDispenseFixture(bool $requiresPayment): array
    {
        $orderedBy = User::factory()->create();
        $branch = Branch::factory()->default()->create();
        $category = ServiceCategory::factory()->create(['code' => 'MED']);

        $service = Service::factory()->create([
            'category_id' => $category->id,
            'requires_payment_before' => $requiresPayment,
            'requires_prescription' => false,
        ]);

        $request = ServiceRequest::factory()->create([
            'branch_id' => $branch->id,
            'ordered_by' => $orderedBy->id,
            'created_by' => $orderedBy->id,
            'patient_id' => null,
            'guest_name' => 'Guest User',
            'guest_phone' => '233000000000',
        ]);

        $item = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
            'quantity' => 3,
            'unit_price' => 10,
            'total_price' => 30,
            'status' => 'pending',
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $service->id,
        ]);

        \Modules\Pharmacy\Models\PrescriptionDetail::create([
            'request_item_id' => $item->id,
            'frequency' => 'bid',
            'duration_days' => 7,
            'route' => 'po',
            'administration_context' => \Modules\Pharmacy\Enums\AdministrationContext::TAKE_HOME,
            'course_started_at' => now(),
            'course_end_at' => now()->addDays(7),
            'total_administrations' => 14,
        ]);

        return [$branch, $service, $item, $medication];
    }
}
