<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\DispenseResource;

class EditDispense extends EditRecord
{
    protected static string $resource = DispenseResource::class;
}
