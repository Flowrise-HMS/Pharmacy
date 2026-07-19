<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Classes\Services\BranchService;

class DispenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->required()
                    ->relationship('branch', 'name')
                    ->label(__('Branch'))
                    ->searchable()
                    ->preload()
                    ->default(fn () => app(BranchService::class)->getDefaultBranchId()),
                TextInput::make('batch_number')
                    ->label(__('Batch number'))
                    ->maxLength(255),
                TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->minValue(0),
                Textarea::make('notes')
                    ->label(__('Notes'))
                    ->columnSpanFull(),
            ]);
    }
}
