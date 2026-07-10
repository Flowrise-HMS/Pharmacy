<?php

namespace Modules\Pharmacy\Classes\Support;

use Modules\Core\Contracts\PharmacyLowStockProviderContract;
use Modules\Pharmacy\Classes\Services\PharmacyAnalyticsService;

class PharmacyLowStockProvider implements PharmacyLowStockProviderContract
{
    public function __construct(
        protected PharmacyAnalyticsService $analytics,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function items(?string $branchId = null, int $limit = 10): array
    {
        return array_map(
            fn (array $row): array => [
                'source' => 'pharmacy',
                'id' => $row['id'],
                'name' => $row['medication'],
                'branch' => $row['branch'],
                'location' => null,
                'quantity_on_hand' => $row['quantity_on_hand'],
                'reorder_point' => $row['reorder_point'],
            ],
            $this->analytics->getLowStockItems($branchId, $limit),
        );
    }
}
