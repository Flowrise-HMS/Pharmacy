<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\RepeatableEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Pharmacy\Models\Medication;

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
                TextEntry::make('rxnorm_code'),
                TextEntry::make('ndc_code'),
                TextEntry::make('controlled_schedule')->badge(),
                TextEntry::make('is_active')->badge()->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                Section::make('Stock Levels')
                    ->schema([
                        TextEntry::make('total_stock')
                            ->label('Total')
                            ->state(fn (Medication $record) => $record->stockItems()->sum('quantity_on_hand'))
                            ->numeric()
                            ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                        RepeatableEntry::make('stockItems')
                            ->schema([
                                TextEntry::make('branch.name')->label('Branch'),
                                TextEntry::make('quantity_on_hand')->label('Qty')->numeric(),
                                TextEntry::make('reorder_point')->label('Reorder At')->numeric(),
                            ])
                            ->columns(3),
                    ]),
                Section::make('Pricing')
                    ->schema([
                        TextEntry::make('service.price')->label('Cash price')->money(config('core.default_currency')),
                        TextEntry::make('service.insurance_price')->label('Insurance price')->money(config('core.default_currency')),
                        TextEntry::make('service.is_insurance_covered')->label('Insurance covered')->badge()->formatStateUsing(fn ($state) => $state ? 'Yes' : 'No'),
                    ])
                    ->visible(fn (Medication $record) => $record->billingService() !== null),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('updated_at')->dateTime(),
            ]);
    }
}
