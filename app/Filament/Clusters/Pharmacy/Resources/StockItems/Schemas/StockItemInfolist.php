<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class StockItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('medication.generic_name')->label('Medication'),
                TextEntry::make('branch.name')->label('Branch'),
                TextEntry::make('quantity_on_hand'),
                TextEntry::make('reorder_point'),
                TextEntry::make('updated_at')->dateTime(),
            ]);
    }
}
