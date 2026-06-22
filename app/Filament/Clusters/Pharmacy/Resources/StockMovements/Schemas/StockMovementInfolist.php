<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stock Movement Details')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime()
                                    ->label('Movement Date'),
                                TextEntry::make('branch.name')
                                    ->label('Branch'),
                                TextEntry::make('performer.name')
                                    ->label('Performed By'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('medication.generic_name')
                                    ->label('Medication')
                                    ->formatStateUsing(fn ($record) => $record->medication?->service?->name ?? $record->medication?->generic_name),
                                TextEntry::make('delta')
                                    ->label('Quantity Change')
                                    ->weight('bold')
                                    ->color(fn (int $state): string => $state >= 0 ? 'success' : 'danger')
                                    ->formatStateUsing(fn ($record): string => $record->delta.' '.($record->unit_label_snapshot ?? $record->medication?->stockUnit?->label ?? '')),
                                TextEntry::make('quantity_after')
                                    ->label('Quantity After')
                                    ->weight('bold')
                                    ->formatStateUsing(fn ($record): string => $record->quantity_after.' '.($record->unit_label_snapshot ?? $record->medication?->stockUnit?->label ?? '')),
                            ]),
                        TextEntry::make('reason')
                            ->label('Reason')
                            ->placeholder('No reason provided'),
                    ]),

                Section::make('System Reference')
                    ->description('Links this movement to a specific system event (e.g., Sale or Purchase)')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextEntry::make('reference_type')
                                    ->label('Reference Type')
                                    ->placeholder('—'),
                                TextEntry::make('reference_id')
                                    ->label('Reference ID')
                                    ->placeholder('—'),
                            ]),
                        TextEntry::make('reference')
                            ->label('Related Record')
                            ->placeholder('No related record found')
                            ->formatStateUsing(function ($record) {
                                if (! $record->reference) {
                                    return null;
                                }

                                return ($record->reference_type ?? 'Record').': '.($record->reference->name ?? $record->reference->number ?? $record->reference->id ?? 'Unknown');
                            }),
                    ])
                    ->collapsible(),
            ]);
    }
}
