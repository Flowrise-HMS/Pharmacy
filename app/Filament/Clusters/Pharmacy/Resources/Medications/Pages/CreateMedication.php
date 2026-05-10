<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;

class CreateMedication extends CreateRecord
{
    protected static string $resource = MedicationResource::class;
}
