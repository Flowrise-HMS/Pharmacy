<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DrugsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('display_name')->searchable()->wrap(),
                TextColumn::make('generic_name')->searchable(),
                TextColumn::make('brand_name')->searchable(),
                TextColumn::make('source_provider')->badge(),
                TextColumn::make('rxnorm_code'),
                TextColumn::make('search_rank')->sortable(),
                TextColumn::make('times_prescribed')->sortable(),
                TextColumn::make('times_stocked')->sortable(),
                TextColumn::make('is_cached_external')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Cached external' : 'Local'),
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
