<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages;

use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\StockMovementResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities;

class ListStockMovementActivities extends ListActivities
{
    protected static string $resource = StockMovementResource::class;
}
