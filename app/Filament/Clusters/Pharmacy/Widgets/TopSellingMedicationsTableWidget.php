<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\Concerns\InteractsWithReportPayload;

class TopSellingMedicationsTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Top selling medications'))
            ->records(fn (): array => $this->reportRows('top_selling_medications', 'label'))
            ->columns([
                TextColumn::make('label')->label(__('Medication')),
                TextColumn::make('quantity')->label(__('Qty sold'))->numeric(),
                CurrencyColumn::make('revenue')->label(__('Revenue')),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No medication sales in range'));
    }
}
