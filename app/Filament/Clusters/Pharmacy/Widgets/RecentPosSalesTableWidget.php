<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\Concerns\InteractsWithReportPayload;

class RecentPosSalesTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Recent POS sales'))
            ->records(fn (): array => $this->reportRows('recent_pos_sales'))
            ->columns([
                TextColumn::make('sold_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('customer')->label(__('Customer')),
                TextColumn::make('line_kind')
                    ->label(__('Line kind'))
                    ->badge()
                    ->color(fn (string $state): string => $state === 'service' ? 'info' : 'success'),
                TextColumn::make('description')->label(__('Description')),
                TextColumn::make('amount')->label(__('Amount'))->numeric(decimalPlaces: 2),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No POS sales in range'));
    }
}
