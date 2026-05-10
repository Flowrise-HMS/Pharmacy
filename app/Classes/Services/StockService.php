<?php

namespace Modules\Pharmacy\Classes\Services;

use Modules\Core\Contracts\StockProviderContract;
use Modules\Pharmacy\Exceptions\InsufficientStockException;
use Modules\Pharmacy\Models\StockItem;

class StockService implements StockProviderContract
{
    public function getQuantityOnHand(string $branchId, string $itemId): int
    {
        return (int) StockItem::query()
            ->where('branch_id', $branchId)
            ->where('medication_id', $itemId)
            ->value('quantity_on_hand');
    }

    public function hasStock(string $branchId, string $itemId, int $quantity): bool
    {
        return $this->getQuantityOnHand($branchId, $itemId) >= $quantity;
    }

    public function decrement(string $branchId, string $itemId, int $quantity, ?string $reason = null): void
    {
        $stock = StockItem::query()
            ->where('branch_id', $branchId)
            ->where('medication_id', $itemId)
            ->first();

        if (! $stock || $stock->quantity_on_hand < $quantity) {
            throw new InsufficientStockException('Insufficient stock to dispense.');
        }

        $stock->update([
            'quantity_on_hand' => $stock->quantity_on_hand - $quantity,
        ]);
    }

    public function increment(string $branchId, string $itemId, int $quantity, ?string $reason = null): void
    {
        $stock = StockItem::query()->firstOrCreate(
            ['branch_id' => $branchId, 'medication_id' => $itemId],
            ['quantity_on_hand' => 0, 'reorder_point' => 0]
        );

        $stock->update([
            'quantity_on_hand' => $stock->quantity_on_hand + $quantity,
        ]);
    }
}
