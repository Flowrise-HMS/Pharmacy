<?php

declare(strict_types=1);

namespace Modules\Pharmacy\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Modules\Pharmacy\Models\StockItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class StockItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny StockItem');
    }

    public function view(AuthUser $authUser, StockItem $stockItem): bool
    {
        return $authUser->can('View StockItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create StockItem');
    }

    public function update(AuthUser $authUser, StockItem $stockItem): bool
    {
        return $authUser->can('Update StockItem');
    }

    public function delete(AuthUser $authUser, StockItem $stockItem): bool
    {
        return $authUser->can('Delete StockItem');
    }

    public function restore(AuthUser $authUser, StockItem $stockItem): bool
    {
        return $authUser->can('Restore StockItem');
    }

    public function forceDelete(AuthUser $authUser, StockItem $stockItem): bool
    {
        return $authUser->can('ForceDelete StockItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny StockItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny StockItem');
    }

    public function replicate(AuthUser $authUser, StockItem $stockItem): bool
    {
        return $authUser->can('Replicate StockItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder StockItem');
    }

}