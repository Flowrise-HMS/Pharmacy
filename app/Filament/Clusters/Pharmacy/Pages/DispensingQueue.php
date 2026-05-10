<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages;

use Filament\Pages\Page;
use Modules\Clinical\Enums\RequestItemStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;

class DispensingQueue extends Page
{
    protected static ?string $title = 'Dispensing queue';

    protected static ?string $navigationLabel = 'Dispensing queue';

    protected static ?string $cluster = PharmacyCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $slug = 'dispensing-queue';

    protected string $view = 'pharmacy::filament.clusters.pharmacy.pages.dispensing-queue';

    public function getPendingItemsCount(): int
    {
        return RequestItem::query()
            ->where('status', RequestItemStatus::IN_PROGRESS->value)
            ->count();
    }
}
