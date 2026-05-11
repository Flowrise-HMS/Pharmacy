<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Context;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Date'),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable(),
                TextColumn::make('medication.generic_name')
                    ->label('Medication')
                    ->formatStateUsing(fn ($record) => $record->medication?->service?->name ?? $record->medication?->generic_name)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('delta')
                    ->label('Delta')
                    ->numeric()
                    ->sortable()
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('quantity_after')
                    ->label('After')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('performer.name')
                    ->label('By')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->default(Context::get('current_branch_id')),
                SelectFilter::make('medication_id')
                    ->label(__('Medication'))
                    ->relationship('medication', 'generic_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('performed_by')
                    ->label(__('Performed By'))
                    ->relationship('performedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->default(auth()->id()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
