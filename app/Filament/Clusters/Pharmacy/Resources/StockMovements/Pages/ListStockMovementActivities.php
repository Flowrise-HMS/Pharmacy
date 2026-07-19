<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages;

use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\StockMovementResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListStockMovementActivities extends ListActivitiesBySubject
{
    protected static string $resource = StockMovementResource::class;
}
