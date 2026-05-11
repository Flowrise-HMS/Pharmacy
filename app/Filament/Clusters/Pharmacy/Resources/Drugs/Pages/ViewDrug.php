<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages;

use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\DrugResource;

class ViewDrug extends ViewRecord
{
    protected static string $resource = DrugResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
