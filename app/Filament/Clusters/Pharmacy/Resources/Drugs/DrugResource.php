<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\CreateDrug;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\EditDrug;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\ListDrugs;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Pages\ViewDrug;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Schemas\DrugForm;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Schemas\DrugInfolist;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Drugs\Tables\DrugsTable;
use Modules\Pharmacy\Models\Drug;

class DrugResource extends Resource
{
    protected static ?string $model = Drug::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = PharmacyCluster::class;

    public static function form(Schema $schema): Schema
    {
        return DrugForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DrugInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DrugsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDrugs::route('/'),
            'create' => CreateDrug::route('/create'),
            'view' => ViewDrug::route('/{record}'),
            'edit' => EditDrug::route('/{record}/edit'),
        ];
    }
}
