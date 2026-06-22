<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Classes\Services\BranchService;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockMovement;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->default(app(BranchService::class)->getDefaultBranchId())
                    ->required(),
                Select::make('medication_id')
                    ->label(__('Medication'))
                    ->relationship('medication', 'generic_name')
                    ->getOptionLabelFromRecordUsing(fn (Medication $record) => $record?->displayName() ?? 'Unknown')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('delta')
                    ->label(__('Quantity Change (Delta)'))
                    ->numeric()
                    ->required()
                    ->suffix(fn (?StockMovement $record): string => $record?->unit_label_snapshot ?? $record?->medication?->stockUnit?->label ?? ''),
                TextInput::make('quantity_after')
                    ->label(__('Quantity After'))
                    ->numeric()
                    ->required()
                    ->suffix(fn (?StockMovement $record): string => $record?->unit_label_snapshot ?? $record?->medication?->stockUnit?->label ?? ''),
                TextInput::make('reason')
                    ->maxLength(64),
                Select::make('performed_by')
                    ->label(__('Performed By'))
                    ->relationship('performedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->default(auth()->id())
                    ->required(),
                // todo:: Handle MorphTo later
                // TextInput::make('reference_type')
                //     ->label('Reference Type')
                //     ->maxLength(255),
                // TextInput::make('reference_id')
                //     ->label('Reference ID')
                //     ->maxLength(255),
            ]);
    }
}
