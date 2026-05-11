<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DrugForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('source_provider')
                    ->required()
                    ->maxLength(255)
                    ->default('local'),
                TextInput::make('source_identifier')
                    ->maxLength(255),
                TextInput::make('generic_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('display_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('brand_name')
                    ->maxLength(255),
                TextInput::make('strength_text')
                    ->maxLength(255),
                TextInput::make('dosage_form_text')
                    ->maxLength(255),
                TextInput::make('rxnorm_code')
                    ->maxLength(255),
                TextInput::make('ndc_code')
                    ->maxLength(255),
                TagsInput::make('synonyms'),
                KeyValue::make('raw_payload'),
                TextInput::make('search_rank')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                TextInput::make('times_prescribed')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                TextInput::make('times_stocked')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Select::make('is_cached_external')
                    ->boolean()
                    ->required()
                    ->default(false),
                Select::make('is_active')
                    ->boolean()
                    ->required()
                    ->default(true),
            ]);
    }
}
