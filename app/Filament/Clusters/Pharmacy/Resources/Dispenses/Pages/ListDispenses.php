<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\DispenseResource;

class ListDispenses extends ListRecords
{
    protected static string $resource = DispenseResource::class;
}
