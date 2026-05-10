<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;

class ListMedications extends ListRecords
{
    protected static string $resource = MedicationResource::class;
}
