<?php

namespace Modules\Pharmacy\Classes\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Classes\Services\MedicationOrderService;

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
                            ->options(function () {
                                return Service::query()
                                    ->whereHas('category', fn ($q) => $q->where('code', 'MED'))
                                    ->pluck('name', 'id')
                                    ->toArray();
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
