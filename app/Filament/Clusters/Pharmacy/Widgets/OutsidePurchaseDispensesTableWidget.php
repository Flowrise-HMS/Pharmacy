<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\Concerns\InteractsWithReportPayload;

class OutsidePurchaseDispensesTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Outside purchase dispenses'))
            ->records(fn (): array => $this->reportRows('outside_purchase_dispenses'))
            ->columns([
                TextColumn::make('dispensed_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('patient')->label(__('Patient')),
                TextColumn::make('medication')->label(__('Medication')),
                TextColumn::make('quantity')->label(__('Qty'))->numeric(),
                TextColumn::make('pharmacist')->label(__('Pharmacist')),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No outside purchases in range'));
    }
}
