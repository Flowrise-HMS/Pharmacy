<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\DispenseService;
use Modules\Pharmacy\Classes\Services\PharmacyPosPrescriptionService;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Modules\Pharmacy\Models\StockItem;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PharmacyPosPrescriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['clinical.mar_payment.require_before_mar' => false]);

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Patient', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Clinical', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Pharmacy', '--force' => true]);
    }

    public function test_zero_stock_prescription_appears_in_panel_query_not_retail_catalog(): void
    {
        [$branch, $patient, $item, $medication] = $this->seedPrescriptionOrder(stockQty: 0);

        $lines = app(PharmacyPosPrescriptionService::class)
            ->forPatient($patient->id, $branch->id);

        $this->assertCount(1, $lines);
        $this->assertSame(__('Out of stock'), $lines->first()->stockLabel);
        $this->assertTrue($lines->first()->canRecordOutsidePurchase);

        $retailCount = Medication::query()
            ->where('is_active', true)
            ->whereHas('stockItems', fn ($q) => $q
                ->where('branch_id', $branch->id)
                ->where('quantity_on_hand', '>', 0))
            ->count();

        $this->assertSame(0, $retailCount);
    }

    public function test_in_stock_prescription_dispense_completes_with_in_house_fulfillment_type(): void
    {
        [$branch, , $item, $medication, $pharmacist] = $this->seedPrescriptionOrder(stockQty: 5);

        app(DispenseService::class)->dispense($item->fresh(), [
            'medication_id' => $medication->id,
            'quantity' => 1,
        ], $pharmacist);

        $this->assertTrue($item->fresh()->isCompleted());
        $this->assertDatabaseHas('dispenses', [
            'request_item_id' => $item->id,
            'fulfillment_type' => DispenseFulfillmentType::IN_HOUSE->value,
            'quantity' => 1,
        ]);
        $this->assertDatabaseHas('stock_items', [
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 4,
        ]);
    }

    public function test_out_of_stock_outside_purchase_completes_without_stock_decrement(): void
    {
        [$branch, , $item, $medication, $pharmacist] = $this->seedPrescriptionOrder(stockQty: 0);

        app(DispenseService::class)->recordOutsidePurchase($item->fresh(), $pharmacist, 'Obtain from licensed community pharmacy');

        $this->assertTrue($item->fresh()->isCompleted());
        $this->assertDatabaseHas('dispenses', [
            'request_item_id' => $item->id,
            'fulfillment_type' => DispenseFulfillmentType::OUTSIDE_PURCHASE->value,
            'quantity' => 0,
            'medication_id' => $medication->id,
            'notes' => 'Obtain from licensed community pharmacy',
        ]);
        $this->assertDatabaseHas('stock_items', [
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 0,
        ]);

        $response = $this->actingAs($pharmacist)
            ->get(route('pharmacy.prescription-slip', ['requestItem' => $item->id]));

        $response->assertOk();
        $response->assertSee('500mg');
        $response->assertSee('Twice Daily (BID)');
        $response->assertSee('Obtain from licensed community pharmacy');
        $response->assertSee(__('Notes'));
        $response->assertSee('80mm');
    }

    public function test_completed_outside_purchase_allows_reprint_in_panel(): void
    {
        [, $patient, $item, , $pharmacist] = $this->seedPrescriptionOrder(stockQty: 0);

        app(DispenseService::class)->recordOutsidePurchase($item->fresh(), $pharmacist);

        $lines = app(PharmacyPosPrescriptionService::class)
            ->forPatient($patient->id, $item->serviceRequest->branch_id);

        $this->assertCount(1, $lines);
        $line = $lines->first();
        $this->assertFalse($line->canRecordOutsidePurchase);
        $this->assertTrue($line->canReprintExternalSlip);
        $this->assertSame(__('External slip issued'), $line->stockLabel);

        $response = $this->actingAs($pharmacist)
            ->get(route('pharmacy.prescription-slip', ['requestItem' => $item->id]));

        $response->assertOk();
    }

    public function test_service_only_order_outside_purchase_allows_null_medication_id(): void
    {
        [$branch, $patient, $item, , $pharmacist] = $this->seedPrescriptionOrder(stockQty: 0, withMedication: false);

        app(DispenseService::class)->recordOutsidePurchase($item->fresh(), $pharmacist);

        $this->assertTrue($item->fresh()->isCompleted());
        $this->assertDatabaseHas('dispenses', [
            'request_item_id' => $item->id,
            'fulfillment_type' => DispenseFulfillmentType::OUTSIDE_PURCHASE->value,
            'medication_id' => null,
        ]);

        $response = $this->actingAs($pharmacist)
            ->get(route('pharmacy.prescription-slip', ['requestItem' => $item->id]));

        $response->assertOk();
        $response->assertSee('500mg');
        $response->assertSee(__('Obtain at external pharmacy.'));
    }

    public function test_unpaid_non_emergency_blocks_outside_purchase(): void
    {
        config(['clinical.mar_payment.require_before_mar' => true]);

        [, , $item, , $pharmacist] = $this->seedPrescriptionOrder(stockQty: 0, requiresPayment: true, paid: false);

        $this->expectException(UnauthorizedMedicationOrderException::class);

        app(DispenseService::class)->recordOutsidePurchase($item->fresh(), $pharmacist);
    }

    public function test_controlled_medication_blocks_outside_purchase(): void
    {
        [, , $item, $medication, $pharmacist] = $this->seedPrescriptionOrder(stockQty: 0);
        $medication->update(['controlled_schedule' => ControlledSchedule::SCHEDULE_2]);

        $this->expectException(UnauthorizedMedicationOrderException::class);

        app(DispenseService::class)->recordOutsidePurchase($item->fresh(), $pharmacist);
    }

    public function test_retail_catalog_query_matches_pharmacy_pos_filter(): void
    {
        [$branch, , , $medication] = $this->seedPrescriptionOrder(stockQty: 3);

        $query = Medication::query()
            ->where('is_active', true)
            ->whereHas('stockItems', fn ($q) => $q
                ->where('branch_id', $branch->id)
                ->where('quantity_on_hand', '>', 0));

        $this->assertTrue($query->where('id', $medication->id)->exists());
    }

    /**
     * @return array{Branch, Patient, RequestItem, Medication|null, User}
     */
    protected function seedPrescriptionOrder(
        int $stockQty = 0,
        bool $withMedication = true,
        bool $requiresPayment = false,
        bool $paid = true,
    ): array {
        $branch = Branch::factory()->default()->create();
        $patient = Patient::factory()->create();
        $orderedBy = User::factory()->create(['branch_id' => $branch->id]);

        $pharmacist = User::factory()->create(['branch_id' => $branch->id]);
        Role::findOrCreate('pharmacist', 'web');
        $pharmacist->assignRole('pharmacist');

        $category = ServiceCategory::factory()->create(['code' => 'MED']);
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'requires_payment_before' => $requiresPayment,
        ]);

        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'ordered_by' => $orderedBy->id,
            'created_by' => $orderedBy->id,
        ]);

        $item = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
            'status' => 'pending',
        ]);

        PrescriptionDetail::create([
            'request_item_id' => $item->id,
            'frequency' => 'bid',
            'duration_days' => 7,
            'route' => 'po',
            'dosage' => '500mg',
            'administration_context' => AdministrationContext::TAKE_HOME,
            'course_started_at' => now(),
            'course_end_at' => now()->addDays(7),
            'total_administrations' => 14,
        ]);

        $medication = null;
        if ($withMedication) {
            $medication = Medication::factory()->create(['service_id' => $service->id]);
            StockItem::factory()->create([
                'branch_id' => $branch->id,
                'medication_id' => $medication->id,
                'quantity_on_hand' => $stockQty,
            ]);
        }

        if ($requiresPayment) {
            $invoice = Invoice::create([
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => 'INV-POS-'.uniqid(),
                'status' => InvoiceStatus::Issued,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'subtotal' => 10,
                'total' => 10,
                'amount_paid' => $paid ? 10 : 0,
            ]);

            InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'billable_type' => RequestItem::class,
                'billable_id' => $item->id,
                'service_id' => $service->id,
                'quantity' => 1,
                'unit_price' => 10,
                'line_total' => 10,
                'patient_responsibility_amount' => 10,
                'line_status' => $paid ? InvoiceLineStatus::Paid : InvoiceLineStatus::Unpaid,
            ]);
        }

        return [$branch, $patient, $item, $medication, $pharmacist];
    }
}
