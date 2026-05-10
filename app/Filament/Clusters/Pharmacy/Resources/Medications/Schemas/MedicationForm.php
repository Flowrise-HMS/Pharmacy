<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Modules\Core\Models\Service;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DosageForm;

class MedicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('service_id')
                    ->label('Service')
                    ->required()
                    ->searchable()
                    ->options(fn () => Service::query()->orderBy('name')->pluck('name', 'id')->toArray()),
                TextInput::make('generic_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('brand_name')
                    ->maxLength(255),
                Select::make('dosage_form')
                    ->options(collect(DosageForm::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray())
                    ->required(),
                TextInput::make('strength')
                    ->maxLength(255),
                TextInput::make('rxnorm_code')
                    ->maxLength(255),
                TextInput::make('ndc_code')
                    ->maxLength(255),
                Select::make('controlled_schedule')
                    ->options(collect(ControlledSchedule::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray()),
                Select::make('is_active')
                    ->options([1 => 'Active', 0 => 'Inactive'])
                    ->default(1)
                    ->required(),
            ]);
    }
}
