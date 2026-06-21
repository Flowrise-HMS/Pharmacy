<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class MedicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('display_name')
                    ->label('Name')
                    ->state(fn (Medication $record) => $record->displayName()),
                TextEntry::make('generic_name'),
                TextEntry::make('brand_name'),
                TextEntry::make('dosage_form')->badge(),
                TextEntry::make('strength'),
                Section::make('Units')
                    ->schema([
                        TextEntry::make('stockUnit.label')->label('Stock Unit'),
                        TextEntry::make('billingUnit.label')->label('Billing Unit'),
                        TextEntry::make('doseUnit.label')->label('Dose Unit'),
                        TextEntry::make('units_per_stock_unit')->label('Units per Stock Unit')->numeric(),
                    ]),
                TextEntry::make('rxnorm_code'),
                TextEntry::make('ndc_code'),
                TextEntry::make('controlled_schedule')->badge(),
                TextEntry::make('is_active')->badge()->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                Section::make('Stock Levels')
                    ->schema([
                        TextEntry::make('total_stock')
                            ->label('Total')
                            ->state(function (Medication $record) {
                                $qty = $record->stockItems()->sum('quantity_on_hand');
                                return $qty . ' ' . ($record->stockUnit?->label ?? '');
                            })
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                        RepeatableEntry::make('stockItems')
                            ->schema([
                                TextEntry::make('branch.name')->label('Branch'),
                                TextEntry::make('quantity_on_hand')
                                    ->label('Qty')
                                    ->formatStateUsing(fn (StockItem $record): string => $record->quantity_on_hand . ' ' . ($record->medication?->stockUnit?->label ?? '')),
                                TextEntry::make('reorder_point')
                                    ->label('Reorder At')
                                    ->formatStateUsing(fn (StockItem $record): string => $record->reorder_point . ' ' . ($record->medication?->stockUnit?->label ?? '')),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Pricing')
                    ->schema([
                        CurrencyEntry::make('service.price')->label('Cash price'),
                        CurrencyEntry::make('service.insurance_price')->label('Insurance price'),
                        TextEntry::make('service.is_insurance_covered')->label('Insurance covered')->badge()->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                    ])
                    ->visible(fn (Medication $record) => $record->billingService() !== null),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('updated_at')->dateTime(),
            ]);
    }
}
