<?php

namespace Modules\Pharmacy\Filament\Clusters\Pharmacy;

use BackedEnum;
use CodeWithDennis\FilamentLucideIcons\Enums\LucideIcon;
use Filament\Clusters\Cluster;
use Modules\Core\Enums\SidebarGroup;

class PharmacyCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = LucideIcon::Pill;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::PatientCare;

    protected static ?int $navigationSort = 60;

    protected static ?string $navigationLabel = 'Pharmacy';
}
