<?php

namespace Modules\Pharmacy\Classes\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\DrugMaterializationService;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Models\Drug;

class MedicationOrderAction
{
    public static function make(?Patient $patient = null, ?string $encounterId = null): Action
    {
        return Action::make('medication_order')
            ->label('Medication Order')
            ->icon('heroicon-m-beaker')
            ->slideOver()
            ->closeModalByClickingAway(false)
            ->visible(fn (): bool => Auth::user()?->can('order_prescription_medication') ?? false)
            ->schema([
                Repeater::make('items')
                    ->minItems(1)
                    ->schema([
                        Select::make('service_id')
                            ->label('Medication Service')
                            ->required()
                            ->searchable()
                            ->live()
                            ->getSearchResultsUsing(function (string $search) {
                                return collect(app(DrugSearchService::class)->search($search, 10))
                                    ->mapWithKeys(function (array $result): array {
                                        if (filled($result['service_id'])) {
                                            return [
                                                (string) $result['service_id'] => '[Catalog] '.$result['display_name'],
                                            ];
                                        }

                                        if (filled($result['drug_id'])) {
                                            $prefix = $result['source_provider'] === 'local' ? '[Reference] ' : '[External] ';

                                            return [
                                                'drug:'.$result['drug_id'] => $prefix.$result['display_name'],
                                            ];
                                        }

                                        return [];
                                    })
                                    ->all();
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (str_starts_with($value, 'drug:')) {
                                    $drugId = str($value)->after('drug:')->toString();
                                    $drug = Drug::query()->find($drugId);

                                    if (! $drug) {
                                        return $value;
                                    }

                                    $prefix = $drug->source_provider === 'local' ? '[Reference] ' : '[External] ';

                                    return $prefix.$drug->display_name;
                                }

                                return Service::find($value)?->name;
                            })
                            ->preload()
                            ->createOptionForm([
                                TextInput::make('generic_name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('brand_name')
                                    ->maxLength(255),
                                TextInput::make('strength')
                                    ->maxLength(255),
                                Select::make('dosage_form')
                                    ->options(DosageForm::class)
                                    ->default(DosageForm::TABLET),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                return app(MedicationService::class)->createWithService($data)->service_id;
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (str_starts_with($state, 'drug:')) {
                                    $drugId = str($state)->after('drug:')->toString();
                                    $drug = Drug::query()->find($drugId);

                                    if (! $drug) {
                                        return;
                                    }

                                    $medication = app(DrugMaterializationService::class)->materialize($drug, [
                                        'service_name' => $drug->display_name,
                                        'price' => 0,
                                        'insurance_price' => 0,
                                        'is_insurance_covered' => false,
                                        'is_active' => true,
                                        'requires_prescription' => true,
                                        'dosage_form' => 'tablet',
                                    ]);

                                    $set('service_id', $medication->service_id);
                                }
                            }),
                        TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->required(),
                    ])
                    ->required(),
                TextInput::make('guest_name')
                    ->visible(fn () => $patient === null),
                TextInput::make('guest_phone')
                    ->visible(fn () => $patient === null),
            ])
            ->action(function (array $data) use ($patient, $encounterId) {
                $service = app(MedicationOrderService::class);
                $user = Auth::user();

                if ($patient) {
                    $service->order($patient, $data['items'], $user, $encounterId);

                    return;
                }

                $service->order([
                    'guest_name' => $data['guest_name'] ?? 'Guest',
                    'guest_phone' => $data['guest_phone'] ?? '',
                ], $data['items'], $user, null);
            });
    }
}
