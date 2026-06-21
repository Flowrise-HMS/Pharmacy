<?php

namespace Modules\Pharmacy\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Pharmacy\Classes\Services\PharmacyAnalyticsService;

class PharmacyOperationsStatsWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $summary = app(PharmacyAnalyticsService::class)->getOperationsSummary(
            $this->resolveBranchId()
        );

        $currency = config('core.default_currency');

        return [
            Stat::make(__('Medication sales today'), $currency.' '.number_format((float) $summary['medication_sales_today'], 2))
                ->description(__('POS and clinical medication revenue'))
                ->descriptionIcon('heroicon-m-beaker')
                ->color('success'),
            Stat::make(__('Service sales today'), $currency.' '.number_format((float) $summary['service_sales_today'], 2))
                ->description(__('Pharmacy POS service tab revenue'))
                ->descriptionIcon('heroicon-m-wrench-screwdriver'),
            Stat::make(__('Dispenses today'), (string) $summary['dispenses_today'])
                ->description(__('Clinical dispenses recorded today'))
                ->descriptionIcon('heroicon-m-archive-box-arrow-down'),
            Stat::make(__('Queue in progress'), (string) $summary['queue_in_progress'])
                ->description(__('Pharmacy prescriptions being fulfilled'))
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($summary['queue_in_progress'] > 0 ? 'warning' : 'success'),
            Stat::make(__('Low stock items'), (string) $summary['low_stock_count'])
                ->description(__('At or below reorder point'))
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($summary['low_stock_count'] > 0 ? 'danger' : 'success'),
        ];
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
