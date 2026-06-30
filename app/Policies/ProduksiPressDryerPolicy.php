<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiPressDryer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiPressDryerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiPressDryer');
    }

    public function view(AuthUser $authUser, ProduksiPressDryer $produksiPressDryer): bool
    {
        return $authUser->can('View:ProduksiPressDryer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiPressDryer');
    }

    public function update(AuthUser $authUser, ProduksiPressDryer $produksiPressDryer): bool
    {
        return $authUser->can('Update:ProduksiPressDryer');
    }

    public function delete(AuthUser $authUser, ProduksiPressDryer $produksiPressDryer): bool
    {
        return $authUser->can('Delete:ProduksiPressDryer');
    }

    public function restore(AuthUser $authUser, ProduksiPressDryer $produksiPressDryer): bool
    {
        return $authUser->can('Restore:ProduksiPressDryer');
    }

    public function forceDelete(AuthUser $authUser, ProduksiPressDryer $produksiPressDryer): bool
    {
        return $authUser->can('ForceDelete:ProduksiPressDryer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiPressDryer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiPressDryer');
    }

    public function replicate(AuthUser $authUser, ProduksiPressDryer $produksiPressDryer): bool
    {
        return $authUser->can('Replicate:ProduksiPressDryer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiPressDryer');
    }

}