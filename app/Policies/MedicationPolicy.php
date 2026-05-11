<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Pharmacy\Models\Medication;

class MedicationPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny Medication');
    }

    public function view(AuthUser $authUser, Medication $medication): bool
    {
        return $authUser->can('View Medication');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create Medication');
    }

    public function update(AuthUser $authUser, Medication $medication): bool
    {
        return $authUser->can('Update Medication');
    }

    public function delete(AuthUser $authUser, Medication $medication): bool
    {
        return $authUser->can('Delete Medication');
    }

    public function restore(AuthUser $authUser, Medication $medication): bool
    {
        return $authUser->can('Restore Medication');
    }

    public function forceDelete(AuthUser $authUser, Medication $medication): bool
    {
        return $authUser->can('ForceDelete Medication');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny Medication');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny Medication');
    }

    public function replicate(AuthUser $authUser, Medication $medication): bool
    {
        return $authUser->can('Replicate Medication');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder Medication');
    }
}
