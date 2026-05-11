<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\CreateStockMovement;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\EditStockMovement;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\ListStockMovements;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Pages\ViewStockMovement;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Schemas\StockMovementForm;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Schemas\StockMovementInfolist;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockMovements\Tables\StockMovementsTable;
use Modules\Pharmacy\Models\StockMovement;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsUpDown;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = PharmacyCluster::class;

    public static function form(Schema $schema): Schema
    {
        return StockMovementForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockMovementInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockMovementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockMovements::route('/'),
            'create' => CreateStockMovement::route('/create'),
            'view' => ViewStockMovement::route('/{record}'),
            'edit' => EditStockMovement::route('/{record}/edit'),
        ];
    }
}
