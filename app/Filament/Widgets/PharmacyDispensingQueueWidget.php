<?php

namespace Modules\Pharmacy\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class PharmacyDispensingQueueWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?int $sort = 11;

    protected function getStats(): array
    {
        $branchId = $this->resolveBranchId();

        $pending = RequestItem::query()
            ->whereHas('prescriptionDetail')
            ->when($branchId, fn ($q) => $q->whereHas('serviceRequest', fn ($sr) => $sr->where('branch_id', $branchId)))
            ->where('status', RequestItemStatus::PENDING->value)
            ->count();

        $inProgress = RequestItem::query()
            ->whereHas('prescriptionDetail')
            ->when($branchId, fn ($q) => $q->whereHas('serviceRequest', fn ($sr) => $sr->where('branch_id', $branchId)))
            ->where('status', RequestItemStatus::IN_PROGRESS->value)
            ->count();

        return [
            Stat::make(__('Pending prescriptions'), (string) $pending)
                ->description(__('Pharmacy orders awaiting fulfillment'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($pending > 0 ? 'warning' : 'success'),
            Stat::make(__('In progress'), (string) $inProgress)
                ->description(__('Pharmacy dispensing queue'))
                ->descriptionIcon('heroicon-m-queue-list')
                ->color($inProgress > 0 ? 'primary' : 'success'),
        ];
    }

    protected function resolveBranchId(): ?string
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        return $branchId !== null ? (string) $branchId : null;
    }
}
