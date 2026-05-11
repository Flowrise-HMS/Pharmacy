<?php

namespace Modules\Pharmacy\Classes\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Core\Contracts\StockProviderContract;
use Modules\Pharmacy\Exceptions\InsufficientStockException;
use Modules\Pharmacy\Models\StockItem;
use Modules\Pharmacy\Models\StockMovement;

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
        DB::transaction(function () use ($branchId, $itemId, $quantity, $reason): void {
            $affected = StockItem::query()
                ->where('branch_id', $branchId)
                ->where('medication_id', $itemId)
                ->where('quantity_on_hand', '>=', $quantity)
                ->update([
                    'quantity_on_hand' => DB::raw('quantity_on_hand - '.(int) $quantity),
                ]);

            if ($affected === 0) {
                throw new InsufficientStockException('Insufficient stock to dispense.');
            }

            $qtyAfter = (int) StockItem::query()
                ->where('branch_id', $branchId)
                ->where('medication_id', $itemId)
                ->value('quantity_on_hand');

            $this->recordMovement(
                branchId: $branchId,
                medicationId: $itemId,
                delta: -$quantity,
                quantityAfter: $qtyAfter,
                reason: $reason,
            );
        });
    }

    public function increment(string $branchId, string $itemId, int $quantity, ?string $reason = null): void
    {
        DB::transaction(function () use ($branchId, $itemId, $quantity, $reason): void {
            $stock = StockItem::query()->firstOrCreate(
                ['branch_id' => $branchId, 'medication_id' => $itemId],
                ['quantity_on_hand' => 0, 'reorder_point' => 0]
            );

            StockItem::query()
                ->whereKey($stock->id)
                ->lockForUpdate()
                ->first();

            StockItem::query()
                ->whereKey($stock->id)
                ->update([
                    'quantity_on_hand' => DB::raw('quantity_on_hand + '.(int) $quantity),
                ]);

            $qtyAfter = (int) StockItem::query()
                ->whereKey($stock->id)
                ->value('quantity_on_hand');

            $this->recordMovement(
                branchId: $branchId,
                medicationId: $itemId,
                delta: $quantity,
                quantityAfter: $qtyAfter,
                reason: $reason,
            );
        });
    }

    protected function recordMovement(
        string $branchId,
        string $medicationId,
        int $delta,
        int $quantityAfter,
        ?string $reason,
    ): void {
        if (! DB::getSchemaBuilder()->hasTable('stock_movements')) {
            return;
        }

        StockMovement::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branchId,
            'medication_id' => $medicationId,
            'delta' => $delta,
            'quantity_after' => $quantityAfter,
            'reason' => $reason,
            'reference_type' => null,
            'reference_id' => null,
            'performed_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
