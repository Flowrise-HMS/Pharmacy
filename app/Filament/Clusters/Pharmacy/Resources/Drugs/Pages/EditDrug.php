<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\DrugResource;

class EditDrug extends EditRecord
{
    protected static string $resource = DrugResource::class;
}
