<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class MedicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('service.name')->label('Service'),
                TextEntry::make('generic_name'),
                TextEntry::make('brand_name'),
                TextEntry::make('dosage_form')->badge(),
                TextEntry::make('strength'),
                TextEntry::make('rxnorm_code'),
                TextEntry::make('ndc_code'),
                TextEntry::make('controlled_schedule')->badge(),
                TextEntry::make('is_active')->badge()->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('updated_at')->dateTime(),
            ]);
    }
}
