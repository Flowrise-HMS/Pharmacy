<?php

namespace Modules\Pharmacy\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Exceptions\UnauthorizedMedicationOrderException;
use Tests\TestCase;

class MedicationOrderingAuthorityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Pharmacy']);
    }

    public function test_non_prescription_item_can_be_ordered_by_any_authenticated_user(): void
    {
        $branch = Branch::factory()->default()->create();
        $orderedBy = User::factory()->create();
        $category = $this->medicationServiceCategory();
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'requires_prescription' => false,
            'requires_payment_before' => false,
        ]);

        $request = app(MedicationOrderService::class)->order(
            patientOrGuest: [
                'guest_name' => 'Walk In',
                'guest_phone' => '233000000000',
                'branch_id' => $branch->id,
            ],
            items: [
                ['service_id' => $service->id, 'quantity' => 2],
            ],
            orderedBy: $orderedBy
        );

        $this->assertNotNull($request->id);
        $this->assertCount(1, $request->items);
        $this->assertDatabaseHas('request_items', [
            'service_request_id' => $request->id,
            'service_id' => $service->id,
            'quantity' => 2,
        ]);
    }

    public function test_prescription_required_item_is_blocked_without_authorized_role(): void
    {
        $branch = Branch::factory()->default()->create();
        $orderedBy = User::factory()->create();
        $category = $this->medicationServiceCategory();
        $service = Service::factory()->create([
            'category_id' => $category->id,
            'requires_prescription' => true,
        ]);

        $this->expectException(UnauthorizedMedicationOrderException::class);

        app(MedicationOrderService::class)->order(
            patientOrGuest: [
                'guest_name' => 'Walk In',
                'guest_phone' => '233000000000',
                'branch_id' => $branch->id,
            ],
            items: [
                ['service_id' => $service->id, 'quantity' => 1],
            ],
            orderedBy: $orderedBy
        );
    }
}
