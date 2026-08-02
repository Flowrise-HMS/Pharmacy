<?php

namespace Modules\Pharmacy\Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PharmacyCustomPermissionSeeder extends Seeder
{
    /** @var array<string, string[]> */
    protected array $matrix = [
        'dispense_medication' => [
            'super_admin',
            'pharmacist',
            'pharmacy_technician',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->matrix as $permissionName => $roles) {
            Permission::findOrCreate($permissionName, 'web');

            foreach ($roles as $roleName) {
                Role::query()
                    ->where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->first()
                    ?->givePermissionTo($permissionName);
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
