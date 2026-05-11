<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DrugInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('source_provider')->badge(),
                TextEntry::make('source_identifier'),
                TextEntry::make('generic_name'),
                TextEntry::make('display_name'),
                TextEntry::make('brand_name'),
                TextEntry::make('strength_text'),
                TextEntry::make('dosage_form_text'),
                TextEntry::make('rxnorm_code'),
                TextEntry::make('ndc_code'),
                TextEntry::make('synonyms')
                    ->formatStateUsing(fn (?array $state): string => empty($state) ? '—' : implode(', ', $state)),
                TextEntry::make('search_rank'),
                TextEntry::make('times_prescribed'),
                TextEntry::make('times_stocked'),
                TextEntry::make('is_cached_external')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Cached external' : 'Local'),
                TextEntry::make('is_active')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive'),
                KeyValueEntry::make('raw_payload'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('updated_at')->dateTime(),
            ]);
    }
}
