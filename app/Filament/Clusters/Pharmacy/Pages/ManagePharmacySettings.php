<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Pages\SettingsPage;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Enums\NavigationGroup;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Settings\PharmacySettings;

class ManagePharmacySettings extends SettingsPage
{
    use HasPageShield;

    protected static ?string $cluster = PharmacyCluster::class;

    protected static string $settings = PharmacySettings::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::SETTINGS;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Pharmacy';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Point of Sale'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('pos_collect_payment')
                            ->label(__('Allow pay-now at POS (global default)'))
                            ->helperText(__('Organization/branch settings can override this.')),
                        Select::make('pos_default_charge_mode')
                            ->label(__('Default charge mode'))
                            ->options([
                                'charge_account' => __('Send to billing'),
                                'pay_now' => __('Pay now'),
                            ])
                            ->required(),
                        CheckboxList::make('pos_payment_methods')
                            ->label(__('Enabled payment methods'))
                            ->options([
                                'cash' => __('Cash'),
                                'card' => __('Card'),
                                'bank_transfer' => __('Bank transfer'),
                                'mobile_money' => __('Mobile money'),
                            ])
                            ->columns(2)
                            ->columnSpanFull(),
                        Toggle::make('guest_checkout_enabled')
                            ->label(__('Guest checkout without patient record')),
                        Toggle::make('services_tab_enabled')
                            ->label(__('Show billable services tab on POS')),
                        Toggle::make('block_controlled_on_pos')
                            ->label(__('Block controlled substances on POS')),
                    ]),
                Section::make(__('Catalog & stock'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('external_drug_lookup')
                            ->label(__('Enable external drug lookup (RxNorm)')),
                        Select::make('default_reorder_point')
                            ->label(__('Default reorder point'))
                            ->options(collect(range(1, 50))->mapWithKeys(fn (int $v) => [$v => (string) $v]))
                            ->required(),
                    ]),
            ]);
    }
}
