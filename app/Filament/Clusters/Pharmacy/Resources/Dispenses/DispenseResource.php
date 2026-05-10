<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages\ListDispenses;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Pages\ViewDispense;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Schemas\DispenseInfolist;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Dispenses\Tables\DispensesTable;
use Modules\Pharmacy\Models\Dispense;

class DispenseResource extends Resource
{
    protected static ?string $model = Dispense::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = PharmacyCluster::class;

    public static function infolist(Schema $schema): Schema
    {
        return DispenseInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DispensesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispenses::route('/'),
            'view' => ViewDispense::route('/{record}'),
        ];
    }
}
