<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class PharmacyPosPolicy
{
    use HandlesAuthorization;

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View PharmacyPos');
    }

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny PharmacyPos');
    }

    public function checkout(AuthUser $authUser): bool
    {
        return $authUser->can('checkout PharmacyPos');
    }
}
