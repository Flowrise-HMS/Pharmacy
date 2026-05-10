<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('medication.generic_name')->label('Medication')->searchable(),
                TextColumn::make('branch.name')->label('Branch')->searchable(),
                TextColumn::make('quantity_on_hand')->sortable(),
                TextColumn::make('reorder_point')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
