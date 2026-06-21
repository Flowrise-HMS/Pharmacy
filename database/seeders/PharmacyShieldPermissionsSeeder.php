<?php

namespace Modules\Pharmacy\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PharmacyShieldPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $prescriptionRoles = ['doctor', 'pharmacist'];
        foreach ($prescriptionRoles as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
            if ($role === null) {
                continue;
            }
            $role->givePermissionTo('order_prescription_medication');
        }

        $widgetPermissionNames = Permission::query()
            ->where('guard_name', $guard)
            ->where('name', 'like', 'View %Widget')
            ->where(function ($query): void {
                $query->where('name', 'like', '%PharmacyOperations%')
                    ->orWhere('name', 'like', '%PharmacyDispensingQueue%')
                    ->orWhere('name', 'like', '%PharmacyLowStock%')
                    ->orWhere('name', 'like', '%PharmacyRevenueMix%')
                    ->orWhere('name', 'like', '%PharmacySales%')
                    ->orWhere('name', 'like', '%PharmacyDispenses%')
                    ->orWhere('name', 'like', '%FulfillmentType%')
                    ->orWhere('name', 'like', '%PrescriptionStatus%')
                    ->orWhere('name', 'like', '%MarAdherence%')
                    ->orWhere('name', 'like', '%TopDispensed%')
                    ->orWhere('name', 'like', '%TopSelling%')
                    ->orWhere('name', 'like', '%StockMovementReason%')
                    ->orWhere('name', 'like', '%LowStockItems%')
                    ->orWhere('name', 'like', '%RecentDispenses%')
                    ->orWhere('name', 'like', '%RecentPosSales%')
                    ->orWhere('name', 'like', '%OutsidePurchaseDispenses%');
            })
            ->pluck('name')
            ->all();

        $pagePermission = Permission::query()
            ->where('guard_name', $guard)
            ->where('name', 'page_PharmacyReport')
            ->value('name');

        $permissions = $widgetPermissionNames;
        if (is_string($pagePermission) && $pagePermission !== '') {
            $permissions[] = $pagePermission;
        }

        if ($permissions !== []) {
            foreach (['super_admin', 'pharmacist', 'doctor'] as $roleName) {
                $this->giveNamedPermissionsToRole($roleName, $permissions, $guard);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $names
     */
    protected function giveNamedPermissionsToRole(string $roleName, array $names, string $guard): void
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', $guard)->first();
        if ($role === null) {
            return;
        }

        $existing = Permission::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->pluck('name')
            ->all();

        if ($existing === []) {
            return;
        }

        $role->givePermissionTo($existing);
    }
}
