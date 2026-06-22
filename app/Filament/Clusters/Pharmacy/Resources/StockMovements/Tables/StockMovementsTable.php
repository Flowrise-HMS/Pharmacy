<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Context;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\StockMovementResource;
use Modules\Pharmacy\Models\Medication;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
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
                    ->sortable()
                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn ($record): string => $record->delta.' '.($record->unit_label_snapshot ?? $record->medication?->stockUnit?->label ?? '')),
                TextColumn::make('quantity_after')
                    ->label('After')
                    ->sortable()
                    ->formatStateUsing(fn ($record): string => $record->quantity_after.' '.($record->unit_label_snapshot ?? $record->medication?->stockUnit?->label ?? '')),
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
                    ->getOptionLabelFromRecordUsing(fn (Medication $record) => $record?->displayName() ?? 'Unknown')
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
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    Action::make('activities')
                        ->label('Activities')
                        ->icon('heroicon-o-bell-alert')
                        ->url(fn ($record) => StockMovementResource::getUrl('activities', ['record' => $record])),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
