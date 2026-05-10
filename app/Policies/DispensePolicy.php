<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Pharmacy\Models\Dispense;
use Illuminate\Auth\Access\HandlesAuthorization;

class DispensePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny Dispense');
    }

    public function view(AuthUser $authUser, Dispense $dispense): bool
    {
        return $authUser->can('View Dispense');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create Dispense');
    }

    public function update(AuthUser $authUser, Dispense $dispense): bool
    {
        return $authUser->can('Update Dispense');
    }

    public function delete(AuthUser $authUser, Dispense $dispense): bool
    {
        return $authUser->can('Delete Dispense');
    }

    public function restore(AuthUser $authUser, Dispense $dispense): bool
    {
        return $authUser->can('Restore Dispense');
    }

    public function forceDelete(AuthUser $authUser, Dispense $dispense): bool
    {
        return $authUser->can('ForceDelete Dispense');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny Dispense');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny Dispense');
    }

    public function replicate(AuthUser $authUser, Dispense $dispense): bool
    {
        return $authUser->can('Replicate Dispense');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder Dispense');
    }

}