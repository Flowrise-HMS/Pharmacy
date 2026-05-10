<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\StockItemResource;

class ListStockItems extends ListRecords
{
    protected static string $resource = StockItemResource::class;
}
