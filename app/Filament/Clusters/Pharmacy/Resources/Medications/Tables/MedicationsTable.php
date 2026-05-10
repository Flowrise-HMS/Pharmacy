<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MedicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service.name')->label('Service')->searchable(),
                TextColumn::make('generic_name')->searchable(),
                TextColumn::make('brand_name')->searchable(),
                TextColumn::make('dosage_form')->badge(),
                TextColumn::make('strength'),
                TextColumn::make('controlled_schedule')->badge(),
                TextColumn::make('is_active')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
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
