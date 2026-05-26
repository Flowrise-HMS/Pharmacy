<?php

namespace Modules\Pharmacy\Classes\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Enums\DosageForm;
use Modules\Pharmacy\Enums\MedicationFrequency;
use Modules\Pharmacy\Enums\MedicationRoute;
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
                            ->label('Medication')
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
                                TextInput::make('price')
                                    ->label('Price (Cash)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix(config('core.default_currency_symbol', 'GHS'))
                                    ->placeholder('0.00')
                                    ->default(0),
                            ])
                            ->createOptionUsing(function (array $data): string {
                                return app(MedicationService::class)->createWithService($data)->service_id;
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (str_starts_with($state, 'drug:')) {
                                    Notification::make()
                                        ->title('Drug reference selected')
                                        ->body('Create a medication from the Pharmacy module to use this drug.')
                                        ->info()
                                        ->send();
                                }
                            }),
                        TextInput::make('quantity')
                            ->numeric()
                            ->default(1)
                            ->required(),

                        Fieldset::make('Administration Details')
                            ->columns(2)
                            ->schema([
                                TextInput::make('dosage')
                                    ->label('Dosage')
                                    ->placeholder('e.g. 500mg'),

                                Select::make('frequency')
                                    ->label('Frequency')
                                    ->options(MedicationFrequency::class)
                                    ->searchable(),

                                Select::make('route')
                                    ->label('Route')
                                    ->options(MedicationRoute::class)
                                    ->searchable(),

                                TextInput::make('duration_days')
                                    ->label('Duration (days)')
                                    ->numeric()
                                    ->minValue(1),

                                Textarea::make('instructions')
                                    ->label('SIG / Instructions')
                                    ->rows(2)
                                    ->columnSpanFull(),

                                Checkbox::make('prn')
                                    ->label('Take as needed (PRN)'),

                                TextInput::make('indication')
                                    ->label('Indication'),

                                TextInput::make('refills')
                                    ->label('Refills')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ]),
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

                try {
                    $request = $patient ? $service->order($patient, $data['items'], $user, $encounterId)
                    : $service->order([
                        'guest_name' => $data['guest_name'] ?? 'Guest',
                        'guest_phone' => $data['guest_phone'] ?? '',
                    ], $data['items'], $user, null);


                    if ($request && $request->items->isNotEmpty()) {
                        $itemIds = $request->items?->pluck('id')?->toArray() ?? [];
                        $invoiceLines = InvoiceLine::where('billable_type', (new RequestItem)->getMorphClass())
                            ->whereIn('billable_id', $itemIds)
                            ->get();


                        if ($invoiceLines->isNotEmpty()) {
                            $lines = [];
                            $totalInsurance = 0;
                            $totalPatient = 0;

                            foreach ($invoiceLines as $line) {
                                $serviceName = $line->service?->name ?? 'Unknown';
                                $insuranceAmount = (float) ($line->insurance_expected_amount ?? 0);
                                $patientAmount = (float) ($line->patient_responsibility_amount ?? $line->line_total);
                                $totalInsurance += $insuranceAmount;
                                $totalPatient += $patientAmount;

                                if ($insuranceAmount > 0 || $patientAmount > 0) {
                                    $lines[] = "{$serviceName}: Insurance covers {$insuranceAmount} | Patient pays {$patientAmount}";
                                }
                            }

                            if (! empty($lines)) {
                                $body = implode("\n", $lines);
                                $body .= "\n\nTotal — Insurance: {$totalInsurance} | Patient: {$totalPatient}";

                                Notification::make()
                                    ->title('Medication order created — Insurance summary')
                                    ->body($body)
                                    ->info()
                                    ->send();
                            }
                        }
                    }


                }catch (\Exception $e){
                    Notification::make()
                        ->title('Order was not created')
                        ->danger()
                        ->send();
                    Log::error($e->getMessage());
                }
            });
    }
}
