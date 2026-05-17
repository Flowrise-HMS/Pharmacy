<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Models\InvoiceLine;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class PharmacyStatsWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected function getStats(): array
    {
        $todayPharmacySales = InvoiceLine::where('billable_type', (new Medication)->getMorphClass())
            ->whereDate('created_at', today())
            ->sum(DB::raw('quantity * unit_price - discount_amount'));

        $lowStockCount = StockItem::whereColumn('quantity_on_hand', '<=', 'reorder_point')->count();

        $currency = config('core.default_currency');

        return [
            Stat::make("Today's Pharmacy Sales", $currency.' '.number_format($todayPharmacySales, 2))
                ->description('Total revenue from medications today')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('success'),
            Stat::make('Low Stock Items', $lowStockCount)
                ->description('Items below reorder point')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($lowStockCount > 0 ? 'danger' : 'success'),
        ];
    }
}
