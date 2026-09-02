<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Core\Filament\Support\SuperAdminExportAction;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\DispenseResource;
use Modules\Pharmacy\Filament\Exports\DispenseExporter;

class ListDispenses extends ListRecords
{
    protected static string $resource = DispenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuperAdminExportAction::make(DispenseExporter::class),
        ];
    }
}
