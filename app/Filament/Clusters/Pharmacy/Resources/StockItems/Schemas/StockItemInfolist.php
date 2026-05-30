<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Modules\Pharmacy\Models\StockItem;

class StockItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('medication.generic_name')->label('Medication'),
                TextEntry::make('branch.name')->label('Branch'),
                TextEntry::make('quantity_on_hand')
                    ->formatStateUsing(fn ($record): string => $record->quantity_on_hand . ' ' . ($record->medication?->stockUnit?->label ?? '')),
                TextEntry::make('reorder_point')
                    ->formatStateUsing(fn ($record): string => $record->reorder_point . ' ' . ($record->medication?->stockUnit?->label ?? '')),
                TextEntry::make('updated_at')->dateTime(),
            ]);
    }
}
