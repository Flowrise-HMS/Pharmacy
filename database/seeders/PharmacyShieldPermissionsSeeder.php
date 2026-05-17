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

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
