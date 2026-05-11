<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\StockMovementResource;

class EditStockMovement extends EditRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
