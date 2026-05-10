<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\CreateStockItem;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\EditStockItem;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\ListStockItems;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Pages\ViewStockItem;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Schemas\StockItemForm;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Schemas\StockItemInfolist;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\StockItems\Tables\StockItemsTable;
use Modules\Pharmacy\Models\StockItem;

class StockItemResource extends Resource
{
    protected static ?string $model = StockItem::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = PharmacyCluster::class;

    public static function form(Schema $schema): Schema
    {
        return StockItemForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StockItemInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StockItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStockItems::route('/'),
            'create' => CreateStockItem::route('/create'),
            'view' => ViewStockItem::route('/{record}'),
            'edit' => EditStockItem::route('/{record}/edit'),
        ];
    }
}
