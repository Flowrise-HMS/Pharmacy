<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\StockItemResource;

class CreateStockItem extends CreateRecord
{
    protected static string $resource = StockItemResource::class;
}
