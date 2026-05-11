<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\DrugResource;

class CreateDrug extends CreateRecord
{
    protected static string $resource = DrugResource::class;
}
