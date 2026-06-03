<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Models\Branch;
use Modules\Pharmacy\Models\Medication;
use Modules\Pharmacy\Models\StockItem;

class MedicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query
                ->withSum('stockItems', 'quantity_on_hand')
                ->with(['stockUnit'])?->whereHas('category', fn ($q) => $q?->where('code', '!=', ServiceCategoryCode::MED->value)
                ))
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('display_name')
                    ->label('Name')
                    ->state(fn (Medication $record) => $record->displayName()),
                TextColumn::make('generic_name')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('brand_name')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dosage_form')->badge(),
                TextColumn::make('strength'),
                TextColumn::make('stock_items_sum_quantity_on_hand')
                    ->label('In Stock')
                    ->sortable()
                    ->default(0)
                    ->color(fn ($state) => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(function (Medication $record): string {
                        $qty = $record->stock_items_sum_quantity_on_hand ?? 0;
                        return $qty . ' ' . ($record->stockUnit?->label ?? '');
                    }),
                TextColumn::make('service.price')
                    ->label('Cash price')
                    ->money(config('core.default_currency'))
                    ->default(0),
                TextColumn::make('billing_status')
                    ->label('Billing')
                    ->badge()
                    ->state(fn (Medication $record): string => $record->billingService()
                        ? ((float) $record->billingService()->price > 0 ? 'Priced' : 'Zero price')
                        : 'No billing')
                    ->color(fn (Medication $record): string => $record->billingService()
                        ? ((float) $record->billingService()->price > 0 ? 'success' : 'info')
                        : 'warning'),
                TextColumn::make('controlled_schedule')->badge(),
                TextColumn::make('is_active')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Active' : 'Inactive'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                Action::make('add_stock')
                    ->label('Add Stock')
                    ->icon('heroicon-m-plus-circle')
                    ->visible(fn () => auth()->user()?->can('create', StockItem::class))
                    ->modalHeading('Add Stock')
                    ->modalDescription(fn (Medication $record) => "Add stock to {$record->displayName()}")
                    ->schema([
                        Select::make('branch_id')
                            ->label('Branch')
                            ->required()
                            ->searchable()
                            ->options(fn () => Branch::query()?->active()?->orderBy('name')?->pluck('name', 'id')->toArray())
                            ->preload()
                            ->default(app(BranchService::class)->getDefaultBranchId()),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->default(1),
                    ])
                    ->action(function (Medication $record, array $data): void {
                        StockItem::firstOrCreate(
                            [
                                'medication_id' => $record->id,
                                'branch_id' => $data['branch_id'],
                            ],
                            [
                                'quantity_on_hand' => 0,
                                'reorder_point' => 10,
                            ]
                        )->increment('quantity_on_hand', (int) $data['quantity']);

                        Notification::make()
                            ->success()
                            ->title('Stock added')
                            ->body("{$data['quantity']} unit(s) added to {$record->displayName()}")
                            ->send();
                    }),
               ActionGroup::make([
                 ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
               ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
