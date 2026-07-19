<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages;

use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\DispenseResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListDispenseActivities extends ListActivitiesBySubject
{
    protected static string $resource = DispenseResource::class;
}
