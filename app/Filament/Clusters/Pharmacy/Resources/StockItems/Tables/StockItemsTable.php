<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Pharmacy\Models\Medication;

class StockItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('medication')
                    ->state(fn($record) => $record?->medication?->displayName())->label('Medication')->searchable(false),
                TextColumn::make('branch.name')->label('Branch')->searchable(),
                TextColumn::make('quantity_on_hand')->sortable(),
                TextColumn::make('reorder_point')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                ])
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch','name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('medication_id')
                    ->label(__('Medication'))
                    ->relationship('medication', 'generic_name')
                    ->getOptionLabelFromRecordUsing(fn (Medication $record) => $record?->displayName())
                    ->preload()
                    ->searchable(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
