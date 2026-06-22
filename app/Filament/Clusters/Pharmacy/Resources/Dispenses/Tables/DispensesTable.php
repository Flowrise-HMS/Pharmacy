<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Pharmacy\Models\Dispense;

class DispensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requestItem.serviceRequest.request_number')->label('Request #')->searchable(),
                TextColumn::make('medication.generic_name')->label('Medication')->searchable(),
                TextColumn::make('quantity')
                    ->sortable()
                    ->formatStateUsing(fn (Dispense $record): string => $record->quantity.' '.($record->unit?->label ?? '')),
                TextColumn::make('dispensedBy.name')->label('Dispensed by')->searchable(),
                TextColumn::make('dispensed_at')->dateTime()->sortable(),
                TextColumn::make('batch_number')->toggleable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
