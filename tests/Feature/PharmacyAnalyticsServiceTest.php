<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Classes\Services\PharmacyAnalyticsService;
use Modules\Pharmacy\Classes\Services\PharmacyPosCheckoutService;
use Modules\Pharmacy\Data\PharmacyReportCriteria;
use Modules\Pharmacy\Database\Factories\DispenseFactory;
use Modules\Pharmacy\Database\Factories\PrescriptionDetailFactory;
use Modules\Pharmacy\Enums\DispenseFulfillmentType;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;
use Tests\TestCase;

class PharmacyAnalyticsServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing', 'Clinical', 'Pharmacy']);
    }

    public function test_operations_summary_splits_medication_and_service_sales_today(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = BranchFactory::new()->create(['is_active' => true]);
        $medCategory = $this->medicationServiceCategory();

        $medService = Service::factory()->create([
            'category_id' => $medCategory->id,
            'price' => 20.00,
            'is_active' => true,
        ]);

        $posService = Service::factory()->create([
            'category_id' => $medCategory->id,
            'branch_id' => $branch->id,
            'price' => 15.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $medService->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 20,
        ]);

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Walk-in',
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 2],
                ['type' => 'service', 'id' => $posService->id, 'quantity' => 1],
            ],
        ]);

        $summary = app(PharmacyAnalyticsService::class)->getOperationsSummary((string) $branch->id);

        $this->assertSame('40.00', $summary['medication_sales_today']);
        $this->assertSame('15.00', $summary['service_sales_today']);
    }

    public function test_revenue_mix_returns_medication_and_service_totals_for_mixed_pos_cart(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $medService = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'is_active' => true,
        ]);

        $posService = Service::factory()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'price' => 5.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $medication = Medication::factory()->create([
            'service_id' => $medService->id,
            'is_active' => true,
        ]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
                ['type' => 'service', 'id' => $posService->id, 'quantity' => 2],
            ],
        ]);

        $criteria = PharmacyReportCriteria::fromRequest([
            'preset' => 'today',
            'branch_id' => $branch->id,
        ]);

        $mix = app(PharmacyAnalyticsService::class)->getRevenueMix($criteria);

        $this->assertSame(['Medications', 'Services'], $mix['labels']);
        $this->assertSame('10.00', $mix['amounts'][0]);
        $this->assertSame('10.00', $mix['amounts'][1]);
    }

    public function test_request_item_clinical_lines_count_as_medication_not_service_revenue(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $branch = BranchFactory::new()->create();
        $category = $this->medicationServiceCategory();
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 30.00,
            'is_active' => true,
        ]);

        $requestItem = RequestItemFactory::new()
            ->forRequest(ServiceRequestFactory::new()->create(['branch_id' => $branch->id]))
            ->forService($service)
            ->create();

        PrescriptionDetailFactory::new()->create(['request_item_id' => $requestItem->id]);

        $invoice = $this->createClinicalInvoice($branch, '30.00');

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => $requestItem->getMorphClass(),
            'billable_id' => $requestItem->id,
            'service_id' => $service->id,
            'description' => $service->name,
            'quantity' => 1,
            'unit_price' => '30.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'line_total' => '30.00',
            'line_status' => InvoiceLineStatus::Paid,
            'patient_responsibility_amount' => '30.00',
            'created_at' => now(),
        ]);

        $criteria = PharmacyReportCriteria::fromRequest([
            'preset' => 'today',
            'branch_id' => $branch->id,
        ]);

        $summary = app(PharmacyAnalyticsService::class)->getRevenueSummary($criteria);

        $this->assertSame('30.00', $summary['medication_sales']);
        $this->assertSame('0.00', $summary['service_sales']);
    }

    public function test_daily_sales_trend_returns_dual_series_labels(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $criteria = PharmacyReportCriteria::fromRequest([
            'start_date' => '2026-06-15',
            'end_date' => '2026-06-16',
            'preset' => 'custom',
        ]);

        $trend = app(PharmacyAnalyticsService::class)->getDailySalesTrend($criteria);

        $this->assertSame(['Jun 15', 'Jun 16'], $trend['labels']);
        $this->assertCount(2, $trend['medication_amounts']);
        $this->assertCount(2, $trend['service_amounts']);
    }

    public function test_top_selling_medications_and_services_order_by_revenue(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $cheapService = Service::factory()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'price' => 5.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $expensiveService = Service::factory()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'price' => 50.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $lowMedService = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 10.00,
            'is_active' => true,
        ]);

        $highMedService = Service::factory()->create([
            'category_id' => $category->id,
            'price' => 50.00,
            'is_active' => true,
        ]);

        $lowMed = Medication::factory()->create(['service_id' => $lowMedService->id, 'is_active' => true]);
        $highMed = Medication::factory()->create(['service_id' => $highMedService->id, 'is_active' => true]);

        StockItem::factory()->create(['branch_id' => $branch->id, 'medication_id' => $lowMed->id, 'quantity_on_hand' => 10]);
        StockItem::factory()->create(['branch_id' => $branch->id, 'medication_id' => $highMed->id, 'quantity_on_hand' => 10]);

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $lowMed->id, 'quantity' => 1],
                ['type' => 'medication', 'id' => $highMed->id, 'quantity' => 3],
                ['type' => 'service', 'id' => $cheapService->id, 'quantity' => 1],
                ['type' => 'service', 'id' => $expensiveService->id, 'quantity' => 1],
            ],
        ]);

        $criteria = PharmacyReportCriteria::fromRequest(['preset' => 'today', 'branch_id' => $branch->id]);

        $topMeds = app(PharmacyAnalyticsService::class)->getTopSellingMedications($criteria, 2);
        $topServices = app(PharmacyAnalyticsService::class)->getTopSellingServices($criteria, 2);

        $this->assertSame('150.00', $topMeds[0]['revenue']);
        $this->assertSame('50.00', $topServices[0]['revenue']);
    }

    public function test_branch_scoping_limits_revenue_to_selected_branch(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $user = User::factory()->create();
        $this->actingAs($user);

        $branchA = Branch::factory()->create(['is_active' => true]);
        $branchB = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        foreach ([$branchA, $branchB] as $index => $branch) {
            $service = Service::factory()->create([
                'category_id' => $category->id,
                'price' => ($index + 1) * 10,
                'is_active' => true,
            ]);

            $medication = Medication::factory()->create(['service_id' => $service->id, 'is_active' => true]);

            StockItem::factory()->create([
                'branch_id' => $branch->id,
                'medication_id' => $medication->id,
                'quantity_on_hand' => 5,
            ]);

            app(PharmacyPosCheckoutService::class)->checkout([
                'branch_id' => $branch->id,
                'patient_id' => null,
                'currency' => 'GHS',
                'payment_method' => PaymentMethod::Cash,
                'cart' => [['type' => 'medication', 'id' => $medication->id, 'quantity' => 1]],
            ]);
        }

        $criteria = PharmacyReportCriteria::fromRequest([
            'preset' => 'today',
            'branch_id' => $branchA->id,
        ]);

        $summary = app(PharmacyAnalyticsService::class)->getRevenueSummary($criteria);

        $this->assertSame('10.00', $summary['medication_sales']);
    }

    public function test_pharmacy_queue_excludes_non_prescription_request_items(): void
    {
        $branch = BranchFactory::new()->create();
        $category = $this->medicationServiceCategory();
        $service = Service::factory()->create(['category_id' => $category->id, 'is_active' => true]);

        $pharmacyItem = RequestItemFactory::new()
            ->forRequest(ServiceRequestFactory::new()->create(['branch_id' => $branch->id]))
            ->forService($service)
            ->create(['status' => RequestItemStatus::IN_PROGRESS]);

        PrescriptionDetailFactory::new()->create(['request_item_id' => $pharmacyItem->id]);

        RequestItemFactory::new()
            ->forRequest(ServiceRequestFactory::new()->create(['branch_id' => $branch->id]))
            ->forService($service)
            ->create(['status' => RequestItemStatus::IN_PROGRESS]);

        $summary = app(PharmacyAnalyticsService::class)->getOperationsSummary((string) $branch->id);

        $this->assertSame(1, $summary['queue_in_progress']);
    }

    public function test_line_kind_and_channel_filters_narrow_revenue(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);
        $category = $this->medicationServiceCategory();

        $medService = Service::factory()->create(['category_id' => $category->id, 'price' => 20.00, 'is_active' => true]);
        $posService = Service::factory()->create([
            'category_id' => $category->id,
            'branch_id' => $branch->id,
            'price' => 8.00,
            'is_active' => true,
            'is_billable' => true,
        ]);

        $medication = Medication::factory()->create(['service_id' => $medService->id, 'is_active' => true]);

        StockItem::factory()->create([
            'branch_id' => $branch->id,
            'medication_id' => $medication->id,
            'quantity_on_hand' => 10,
        ]);

        app(PharmacyPosCheckoutService::class)->checkout([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'currency' => 'GHS',
            'payment_method' => PaymentMethod::Cash,
            'cart' => [
                ['type' => 'medication', 'id' => $medication->id, 'quantity' => 1],
                ['type' => 'service', 'id' => $posService->id, 'quantity' => 1],
            ],
        ]);

        $requestItem = RequestItemFactory::new()
            ->forRequest(ServiceRequestFactory::new()->create(['branch_id' => $branch->id]))
            ->forService($medService)
            ->create();

        PrescriptionDetailFactory::new()->create(['request_item_id' => $requestItem->id]);

        $invoice = $this->createClinicalInvoice($branch, '20.00');

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => $requestItem->getMorphClass(),
            'billable_id' => $requestItem->id,
            'service_id' => $medService->id,
            'description' => $medService->name,
            'quantity' => 1,
            'unit_price' => '20.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'line_total' => '20.00',
            'line_status' => InvoiceLineStatus::Paid,
            'patient_responsibility_amount' => '20.00',
            'created_at' => now(),
        ]);

        $serviceOnly = PharmacyReportCriteria::fromRequest([
            'preset' => 'today',
            'branch_id' => $branch->id,
            'line_kind' => 'service',
        ]);
        $this->assertSame('8.00', app(PharmacyAnalyticsService::class)->getRevenueSummary($serviceOnly)['total_sales']);

        $clinicalOnly = PharmacyReportCriteria::fromRequest([
            'preset' => 'today',
            'branch_id' => $branch->id,
            'channel' => 'clinical',
        ]);
        $this->assertSame('20.00', app(PharmacyAnalyticsService::class)->getRevenueSummary($clinicalOnly)['total_sales']);

        $posOnly = PharmacyReportCriteria::fromRequest([
            'preset' => 'today',
            'branch_id' => $branch->id,
            'channel' => 'pos',
        ]);
        $posSummary = app(PharmacyAnalyticsService::class)->getRevenueSummary($posOnly);
        $this->assertSame('28.00', $posSummary['total_sales']);
    }

    public function test_fulfillment_type_split_groups_dispenses(): void
    {
        Carbon::setTestNow('2026-06-16 12:00:00');

        $branch = BranchFactory::new()->create();
        $serviceRequest = ServiceRequestFactory::new()->create(['branch_id' => $branch->id]);
        $requestItem = RequestItemFactory::new()->forRequest($serviceRequest)->create();

        DispenseFactory::new()->count(2)->create([
            'request_item_id' => $requestItem->id,
            'fulfillment_type' => DispenseFulfillmentType::IN_HOUSE,
            'dispensed_at' => now(),
        ]);

        DispenseFactory::new()->create([
            'request_item_id' => $requestItem->id,
            'fulfillment_type' => DispenseFulfillmentType::OUTSIDE_PURCHASE,
            'dispensed_at' => now(),
        ]);

        $criteria = PharmacyReportCriteria::fromRequest(['preset' => 'today', 'branch_id' => $branch->id]);
        $split = app(PharmacyAnalyticsService::class)->getFulfillmentTypeSplit($criteria);

        $this->assertCount(2, $split['labels']);
        $this->assertSame(3, array_sum($split['counts']));
    }

    protected function createClinicalInvoice(Branch $branch, string $total): Invoice
    {
        return Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Paid,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => now(),
            'total' => $total,
            'amount_paid' => $total,
        ]));
    }
}
