<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Pharmacy\Models\Dispense;

class DispenseInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('requestItem.serviceRequest.request_number')->label('Request #'),
                TextEntry::make('requestItem.service.name')->label('Service'),
                TextEntry::make('medication.generic_name')->label('Medication'),
                TextEntry::make('quantity')
                    ->formatStateUsing(fn (Dispense $record): string => $record->quantity . ' ' . ($record->unit?->label ?? '')),
                TextEntry::make('batch_number'),
                TextEntry::make('expiry_date')->date(),
                TextEntry::make('dispensedBy.name')->label('Dispensed by'),
                TextEntry::make('dispensed_at')->dateTime(),
                TextEntry::make('notes'),
            ]);
    }
}
