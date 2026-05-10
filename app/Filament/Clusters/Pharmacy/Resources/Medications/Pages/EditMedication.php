<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;

class EditMedication extends EditRecord
{
    protected static string $resource = MedicationResource::class;
}
