<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;
use Modules\Pharmacy\Filament\Imports\MedicationImporter;

class ListMedications extends ListRecords
{
    protected static string $resource = MedicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(MedicationImporter::class)
                ->color('info'),
            CreateAction::make(),
        ];
    }
}
