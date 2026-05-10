<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Modules\Core\Enums\NavigationGroup;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\PharmacyCluster;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\CreateMedication;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\EditMedication;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\ListMedications;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Pages\ViewMedication;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas\MedicationForm;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Schemas\MedicationInfolist;
use Modules\Pharmacy\Filament\Clusters\Pharmacy\Resources\Medications\Tables\MedicationsTable;
use Modules\Pharmacy\Models\Medication;

class MedicationResource extends Resource
{
    protected static ?string $model = Medication::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::CLINICAL;

    protected static ?string $cluster = PharmacyCluster::class;

    public static function form(Schema $schema): Schema
    {
        return MedicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MedicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MedicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMedications::route('/'),
            'create' => CreateMedication::route('/create'),
            'view' => ViewMedication::route('/{record}'),
            'edit' => EditMedication::route('/{record}/edit'),
        ];
    }
}
