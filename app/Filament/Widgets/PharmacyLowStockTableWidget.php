<?php

namespace Modules\Pharmacy\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Models\StockItem;

class PharmacyLowStockTableWidget extends BaseTableWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Pharmacy low stock';

    protected function getTableQuery(): Builder
    {
        return StockItem::query()
            ->with(['medication.service', 'branch'])
            ->whereColumn('quantity_on_hand', '<=', 'reorder_point')
            ->when($this->resolveBranchId(), fn (Builder $query, string $branchId) => $query->where('branch_id', $branchId))
            ->orderBy('quantity_on_hand')
            ->limit(10);
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('medication.service.name')
                ->label(__('Medication'))
                ->placeholder('—'),
            TextColumn::make('branch.name')
                ->label(__('Branch'))
                ->placeholder('—'),
            TextColumn::make('quantity_on_hand')
                ->label(__('On hand'))
                ->numeric(),
            TextColumn::make('reorder_point')
                ->label(__('Reorder point'))
                ->numeric(),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
