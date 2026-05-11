<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Widgets\DispensingQueueWidget;
use Override;

class PharmacyCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Pharmacy';

    #[Override]
    public function getHeaderWidgets(): array
    {
        return [
            DispensingQueueWidget::class,
        ];
    }

}
