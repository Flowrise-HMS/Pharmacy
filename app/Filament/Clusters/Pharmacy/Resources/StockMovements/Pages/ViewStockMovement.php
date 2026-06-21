<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\StockMovementResource;

class ViewStockMovement extends ViewRecord
{
    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('activities')
                ->label('Activities')
                ->icon('heroicon-o-bell-alert')
                ->url(fn () => \Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\StockMovementResource::getUrl('activities', ['record' => $this->getRecord()])),
        ];
    }
}
