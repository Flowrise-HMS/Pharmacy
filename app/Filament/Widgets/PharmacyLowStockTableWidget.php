<?php

namespace Modules\Pharmacy\Filament\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseTableWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Classes\Services\StockOverviewService;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Support\ModuleAvailability;
use Modules\Inventory\Filament\Clusters\Inventory\Resources\StockBalances\StockBalanceResource;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\StockItemResource;

class PharmacyLowStockTableWidget extends BaseTableWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low stock overview';

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => app(StockOverviewService::class)->allLowStock(
                branchId: $this->resolveBranchId(),
                limitPerSource: 10,
            ))
            ->columns([
                TextColumn::make('source')
                    ->label(__('Source'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'inventory' => __('Central store'),
                        default => __('Pharmacy'),
                    })
                    ->color(fn (string $state): string => $state === 'inventory' ? 'info' : 'warning'),
                TextColumn::make('name')
                    ->label(__('Item')),
                TextColumn::make('branch')
                    ->label(__('Branch')),
                TextColumn::make('location')
                    ->label(__('Location'))
                    ->placeholder('—')
                    ->visible(fn (): bool => ModuleAvailability::inventoryEnabled()),
                TextColumn::make('quantity_on_hand')
                    ->label(__('On hand'))
                    ->numeric(),
                TextColumn::make('reorder_point')
                    ->label(__('Reorder point'))
                    ->numeric(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No low stock items'))
            ->headerActions([
                Action::make('view_pharmacy_stock')
                    ->label(__('Pharmacy stock'))
                    ->icon('heroicon-m-beaker')
                    ->url(StockItemResource::getUrl('index')),
                Action::make('view_central_store')
                    ->label(__('Central store'))
                    ->icon('heroicon-m-archive-box')
                    ->visible(fn (): bool => ModuleAvailability::inventoryEnabled()
                        && class_exists(StockBalanceResource::class))
                    ->url(fn (): string => StockBalanceResource::getUrl('index')),
            ]);
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
