<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Classes\Services\BranchService;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class StockItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('medication_id')
                    ->required()
                    ->searchable()
                    ->label(__('Medication'))
                    ->relationship('medication', 'generic_name')
                    ->getOptionLabelFromRecordUsing(fn (Medication $record) => $record?->displayName() ?? 'Unknown')
                    ->preload(),
                Select::make('branch_id')
                    ->required()
                    ->relationship('branch', 'name')
                    ->label(__('Branch'))
                    ->searchable()
                    ->preload()
                    ->default(app(BranchService::class)->getDefaultBranchId()),
                TextInput::make('quantity_on_hand')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix(fn (?StockItem $record): string => $record?->medication?->stockUnit?->label ?? ''),
                TextInput::make('reorder_point')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->minValue(0)
                    ->suffix(fn (?StockItem $record): string => $record?->medication?->stockUnit?->label ?? ''),
            ]);
    }
}
