<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\Concerns\InteractsWithReportPayload;

class RecentDispensesTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Recent dispenses'))
            ->records(fn (): array => $this->reportRows('recent_dispenses'))
            ->columns([
                TextColumn::make('dispensed_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('patient')->label(__('Patient')),
                TextColumn::make('medication')->label(__('Medication')),
                TextColumn::make('quantity')->label(__('Qty'))->numeric(),
                TextColumn::make('pharmacist')->label(__('Pharmacist')),
                TextColumn::make('fulfillment_type')->label(__('Fulfillment')),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No dispenses in range'));
    }
}
