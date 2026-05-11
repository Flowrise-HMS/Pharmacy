<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Set;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Classes\Services\DrugMaterializationService;
use Modules\Pharmacy\Classes\Services\DrugSearchService;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\MedicationResource;
use Modules\Pharmacy\Filament\Imports\MedicationImporter;
use Modules\Pharmacy\Models\Drug;

class ListMedications extends ListRecords
{
    protected static string $resource = MedicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_from_drug')
                ->label('Create from Drug')
                ->icon('heroicon-m-magnifying-glass')
                ->color('primary')
                ->slideOver()
                ->schema([
                    Select::make('drug_reference_id')
                        ->label('Drug Reference')
                        ->required()
                        ->searchable()
                        ->live()
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

                            $set('service_name', $drug->display_name);
                            $set('insurance_price', 0);
                            $set('price', 0);
                        }),
                    TextInput::make('service_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('price')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(0),
                    TextInput::make('insurance_price')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->default(0),
                    Select::make('is_insurance_covered')
                        ->boolean()
                        ->required()
                        ->default(false),
                    Select::make('requires_prescription')
                        ->boolean()
                        ->required()
                        ->default(true),
                    Select::make('requires_payment_before')
                        ->boolean()
                        ->required()
                        ->default(false),
                    Select::make('branch_id')
                        ->searchable()
                        ->options(fn () => Branch::query()->active()->orderBy('name')->pluck('name', 'id')->toArray()),
                    TextInput::make('initial_stock_quantity')
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ])
                ->action(function (array $data): void {
                    $drug = Drug::query()->findOrFail($data['drug_reference_id']);
                    $medication = app(DrugMaterializationService::class)->materialize($drug, $data);

                    Notification::make()
                        ->success()
                        ->title('Medication created from drug reference')
                        ->body($medication->service?->name ?? $medication->generic_name)
                        ->send();

                    $this->redirect(MedicationResource::getUrl('edit', ['record' => $medication]));
                }),
            ImportAction::make()
                ->importer(MedicationImporter::class)
                ->color('info'),
            CreateAction::make(),
        ];
    }
}
