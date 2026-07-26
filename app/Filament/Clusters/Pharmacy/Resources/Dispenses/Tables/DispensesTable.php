<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Support\SuperAdmin;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\DispenseResource;
use Modules\Pharmacy\Models\Dispense;

class DispensesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('branch.name')->label(__('Branch'))->searchable()->sortable()->toggleable(),
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
                Action::make('activities')
                    ->visible(fn (): bool => SuperAdmin::check())
                    ->label('Activities')
                    ->icon('heroicon-o-bell-alert')
                    ->url(fn ($record) => DispenseResource::getUrl('activities', ['record' => $record])),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
