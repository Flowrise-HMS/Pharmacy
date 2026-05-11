<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\DrugResource;

class ListDrugs extends ListRecords
{
    protected static string $resource = DrugResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
