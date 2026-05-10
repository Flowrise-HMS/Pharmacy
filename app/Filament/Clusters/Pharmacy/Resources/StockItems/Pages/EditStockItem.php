<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\StockItemResource;

class EditStockItem extends EditRecord
{
    protected static string $resource = StockItemResource::class;
}
