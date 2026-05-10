<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Models\Medication;

class StockItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('medication_id')
                    ->required()
                    ->searchable()
                    ->options(fn () => Medication::query()->orderBy('generic_name')->pluck('generic_name', 'id')->toArray()),
                Select::make('branch_id')
                    ->required()
                    ->searchable()
                    ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id')->toArray()),
                TextInput::make('quantity_on_hand')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                TextInput::make('reorder_point')
                    ->numeric()
                    ->required()
                    ->minValue(0),
            ]);
    }
}
