<?php

namespace Modules\Pharmacy\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\RequestItem;

class DispensingQueueWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $count = RequestItem::query()
            ->where('status', RequestItemStatus::IN_PROGRESS->value)
            ->count();

        return [
            Stat::make('Dispensing Queue', $count)
                ->description('Current in-progress request items awaiting dispensing')
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($count > 0 ? 'warning' : 'success'),
        ];
    }
}
