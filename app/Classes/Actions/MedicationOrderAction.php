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
use Modules\Pharmacy\Classes\Services\MedicationOrderService;
use Modules\Pharmacy\Classes\Services\MedicationService;
use Modules\Pharmacy\Classes\Services\RxNormService;

class MedicationOrderAction
{
    public static function make(?Patient $patient = null, ?string $encounterId = null): Action
    {
        return Action::make('medication_order')
            ->label('Medication Order')
            ->icon('heroicon-m-beaker')
            ->slideOver()
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
                                $localServices = Service::query()
                                    ->whereHas('category', fn ($q) => $q->where('code', 'MED'))
                                    ->where('name', 'like', "%{$search}%")
                                    ->limit(10)
                                    ->pluck('name', 'id')
                                    ->toArray();

                                $rxNormService = app(RxNormService::class);
                                $externalResults = $rxNormService->search($search);

                                $externalFormatted = [];
                                foreach (array_slice($externalResults, 0, 10) as $external) {
                                    $externalFormatted['rxnorm:' . $external['rxcui'] . ':' . $external['name']] = '[External] ' . $external['name'];
                                }

                                return $localServices + $externalFormatted;
                            })
                            ->getOptionLabelUsing(function ($value): ?string {
                                if (str_starts_with($value, 'rxnorm:')) {
                                    $parts = explode(':', $value, 3);
                                    return isset($parts[2]) ? '[External] ' . $parts[2] : $value;
                                }
                                return Service::find($value)?->name;
                            })
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (str_starts_with($state, 'rxnorm:')) {
                                    $parts = explode(':', $state, 3);
                                    $rxcui = $parts[1] ?? null;
                                    $name = $parts[2] ?? 'Unknown Medication';

                                    // Materialize it in the local DB
                                    $medicationService = app(MedicationService::class);
                                    $medication = $medicationService->createWithService([
                                        'rxnorm_code' => $rxcui,
                                        'generic_name' => $name,
                                        'service_name' => $name,
                                        'price' => 0, // Needs pricing by pharmacy later
                                        'is_active' => true,
                                        'requires_prescription' => true,
                                        'dosage_form' => 'tablet', // Default fallback
                                    ]);

                                    // Set the newly created local service_id
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
