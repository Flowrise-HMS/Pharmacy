<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Core\Enums\CoverageType;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Drug;

class MedicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('drug_reference_id')
                    ->label('Drug Reference')
                    ->options(function () {
                        return collect(app(DrugSearchService::class)->getTopLocalDrugs(50))
                            ->filter(fn (array $result): bool => filled($result['drug_id']))
                            ->mapWithKeys(fn (array $result): array => [
                                $result['drug_id'] => $result['display_name'],
                            ])
                            ->all();
                    })
                    ->searchable()
                    ->live()
                    ->dehydrated(false)
                    ->placeholder('Search local and external drug references')
                    ->getSearchResultsUsing(function (string $search): array {
                        return collect(app(DrugSearchService::class)->search($search, 10))
                            ->filter(fn (array $result): bool => filled($result['drug_id']))
                            ->mapWithKeys(fn (array $result): array => [
                                $result['drug_id'] => ($result['source'] === 'external' ? '[External] ' : '').$result['display_name'],
                            ])
                            ->all();
                    })
                    ->getOptionLabelUsing(fn ($value): ?string => Drug::query()->find($value)?->display_name)
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $drug = filled($state) ? Drug::query()->find($state) : null;

                        if (! $drug) {
                            return;
                        }

                        $set('generic_name', $drug->generic_name);
                        $set('brand_name', $drug->brand_name);
                        $set('strength', $drug->strength_text);
                        $set('rxnorm_code', $drug->rxnorm_code);
                        $set('ndc_code', $drug->ndc_code);

                        if (filled($drug->dosage_form_text)) {
                            $normalized = strtolower(trim($drug->dosage_form_text));
                            $allowedValues = collect(DosageForm::cases())->map(fn (DosageForm $case) => $case->value)->all();

                            if (in_array($normalized, $allowedValues, true)) {
                                $set('dosage_form', $normalized);
                            }
                        }
                    }),
                TextInput::make('generic_name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('brand_name')
                    ->maxLength(255),
                Select::make('dosage_form')
                    ->options(collect(DosageForm::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])->toArray()),
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
                Section::make('Pricing')
                    ->columns(2)
                    ->schema([
                        TextInput::make('price')
                            ->label('Price (Cash)')
                            ->numeric()
                            ->minValue(0)
                            ->prefix(config('core.default_currency_symbol', 'GHS'))
                            ->placeholder('0.00'),
                        TextInput::make('insurance_price')
                            ->hidden()
                            ->default(0),
                        Toggle::make('is_insurance_covered')
                            ->hidden()
                            ->default(false),
                        Select::make('coverage_type')
                            ->hidden()
                            ->options(CoverageType::class)
                            ->default(CoverageType::NONE),
                    ]),
            ]);
    }
}
