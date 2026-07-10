<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Core\Settings\FeatureSettings;
use Modules\Core\Support\Currency;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Enums\ControlledSchedule;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Drug;

class MedicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Medication identity'))
                    ->description(__('Link a drug reference or enter medication details manually.'))
                    ->schema(self::identityFields()),
                Section::make(__('Billing'))
                    ->description(__('Cash and insurance pricing for the linked billing service.'))
                    ->schema(self::pricingFields()),
                Section::make(__('Units & packaging'))
                    ->schema(self::unitFields()),
                Section::make(__('Initial stock'))
                    ->description(__('Optionally add opening stock when creating a medication.'))
                    ->visibleOn('create')
                    ->schema(self::initialStockFields()),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    public static function identityFields(): array
    {
        return [
            Select::make('drug_reference_id')
                ->label(__('Drug'))
                ->options(function (): array {
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
                ->placeholder(__('Search local and external drug references'))
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
                        $set('drug_id', null);

                        return;
                    }

                    $set('drug_id', $drug->id);
                    $set('generic_name', $drug->generic_name);
                    $set('brand_name', $drug->brand_name);
                    $set('strength', $drug->strength_text);
                    $set('rxnorm_code', $drug->rxnorm_code);
                    $set('ndc_code', $drug->ndc_code);

                    if (filled($drug->dosage_form_text)) {
                        $set('dosage_form', app(MedicationService::class)->resolveDosageForm($drug->dosage_form_text));
                    }
                }),
            Hidden::make('drug_id'),
            Select::make('service_id')
                ->label(__('Billing service'))
                ->relationship(
                    'service',
                    'name',
                    fn ($query, $record) => $query
                        ->where(function ($q) use ($record): void {
                            $q->whereHas(
                                'category',
                                fn ($categoryQuery) => $categoryQuery->where('code', ServiceCategoryCode::MED->value)
                            );

                            if ($record?->service_id) {
                                $q->orWhere('id', $record->service_id);
                            }
                        })
                )
                ->searchable()
                ->preload()
                ->nullable()
                ->live()
                ->placeholder(__('Leave empty to auto-create a billing service'))
                ->helperText(__('Medication billing services use the Medications category.'))
                ->afterStateUpdated(function ($state, Set $set): void {
                    $service = filled($state) ? Service::query()->find($state) : null;

                    if (! $service) {
                        return;
                    }

                    $set('price', $service->price);
                    $set('insurance_price', $service->insurance_price);
                    $set('coverage_type', $service->coverage_type?->value ?? CoverageType::NONE->value);
                    $set('is_insurance_covered', (bool) $service->is_insurance_covered);
                }),
            Grid::make(2)
                ->schema([
                    TextInput::make('generic_name')
                        ->label(__('Generic name'))
                        ->required()
                        ->maxLength(255),
                    TextInput::make('brand_name')
                        ->label(__('Brand name'))
                        ->maxLength(255),
                ]),
            Grid::make(3)
                ->schema([
                    Select::make('dosage_form')
                        ->label(__('Dosage form'))
                        ->options(collect(DosageForm::cases())->mapWithKeys(
                            fn (DosageForm $case) => [$case->value => $case->getLabel()]
                        )->all())
                        ->searchable()
                        ->preload(),
                    TextInput::make('strength')
                        ->label(__('Strength'))
                        ->maxLength(100),
                    Select::make('controlled_schedule')
                        ->label(__('Controlled schedule'))
                        ->options(collect(ControlledSchedule::cases())->mapWithKeys(
                            fn (ControlledSchedule $case) => [$case->value => $case->getLabel()]
                        )->all())
                        ->searchable(),
                ]),
            Grid::make(2)
                ->schema([
                    TextInput::make('rxnorm_code')
                        ->label(__('RxNorm code'))
                        ->maxLength(50),
                    TextInput::make('ndc_code')
                        ->label(__('NDC code'))
                        ->maxLength(50),
                ]),
            Toggle::make('is_active')
                ->label(__('Active'))
                ->default(true)
                ->required(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function pricingFields(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    TextInput::make('price')
                        ->label(__('Cash price'))
                        ->numeric()
                        ->minValue(0)
                        ->prefix(Currency::defaultSymbol())
                        ->placeholder('0.00'),
                    TextInput::make('insurance_price')
                        ->label(__('Insurance price'))
                        ->numeric()
                        ->minValue(0)
                        ->prefix(Currency::defaultSymbol())
                        ->placeholder('0.00')
                        ->visible(fn (): bool => self::insuranceEnabled()),
                    Toggle::make('is_insurance_covered')
                        ->label(__('Insurance covered'))
                        ->default(false)
                        ->visible(fn (): bool => self::insuranceEnabled()),
                    Select::make('coverage_type')
                        ->label(__('Coverage type'))
                        ->options(CoverageType::class)
                        ->default(CoverageType::NONE)
                        ->visible(fn (): bool => self::insuranceEnabled()),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function unitFields(): array
    {
        return [
            Grid::make(3)
                ->schema([
                    Select::make('stock_unit_id')
                        ->label(__('Stock unit'))
                        ->relationship('stockUnit', 'label')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText(__('Unit used for inventory tracking')),
                    Select::make('billing_unit_id')
                        ->label(__('Billing unit'))
                        ->relationship('billingUnit', 'label')
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText(__('Unit used for pricing and invoicing')),
                    Select::make('dose_unit_id')
                        ->label(__('Dose unit'))
                        ->relationship('doseUnit', 'label')
                        ->searchable()
                        ->preload()
                        ->helperText(__('Unit used for prescriptions and MAR (optional)')),
                ]),
            TextInput::make('units_per_stock_unit')
                ->label(__('Units per stock unit'))
                ->numeric()
                ->minValue(0)
                ->step(0.0001)
                ->helperText(__('e.g. 100 ml per bottle, 10 tablets per strip')),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function initialStockFields(): array
    {
        return [
            Grid::make(2)
                ->schema([
                    Select::make('stock_branch_id')
                        ->label(__('Branch'))
                        ->options(fn (): array => Branch::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn (): ?string => app(BranchService::class)->getDefaultBranchId())
                        ->searchable()
                        ->preload(),
                    TextInput::make('initial_quantity')
                        ->label(__('Initial quantity'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function addStockFields(): array
    {
        return [
            Select::make('branch_id')
                ->label(__('Branch'))
                ->required()
                ->searchable()
                ->options(fn (): array => Branch::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                ->preload()
                ->default(fn (): ?string => app(BranchService::class)->getDefaultBranchId()),
            TextInput::make('quantity')
                ->label(__('Quantity'))
                ->required()
                ->numeric()
                // ->minValue(1)
                ->default(1),
        ];
    }

    protected static function insuranceEnabled(): bool
    {
        try {
            return app(FeatureSettings::class)->insurance_enabled;
        } catch (\Throwable) {
            return true;
        }
    }
}
