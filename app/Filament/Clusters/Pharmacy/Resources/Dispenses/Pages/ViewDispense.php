<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\DispenseResource;

class ViewDispense extends ViewRecord
{
    protected static string $resource = DispenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('activities')
                ->label('Activities')
                ->icon('heroicon-o-bell-alert')
                ->url(fn () => DispenseResource::getUrl('activities', ['record' => $this->getRecord()])),
            EditAction::make(),
        ];
    }
}
