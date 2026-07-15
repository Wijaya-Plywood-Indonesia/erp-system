<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiTembeltriplek;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiTembeltriplekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiTembeltriplek');
    }

    public function view(AuthUser $authUser, ProduksiTembeltriplek $produksiTembeltriplek): bool
    {
        return $authUser->can('View:ProduksiTembeltriplek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiTembeltriplek');
    }

    public function update(AuthUser $authUser, ProduksiTembeltriplek $produksiTembeltriplek): bool
    {
        return $authUser->can('Update:ProduksiTembeltriplek');
    }

    public function delete(AuthUser $authUser, ProduksiTembeltriplek $produksiTembeltriplek): bool
    {
        return $authUser->can('Delete:ProduksiTembeltriplek');
    }

    public function restore(AuthUser $authUser, ProduksiTembeltriplek $produksiTembeltriplek): bool
    {
        return $authUser->can('Restore:ProduksiTembeltriplek');
    }

    public function forceDelete(AuthUser $authUser, ProduksiTembeltriplek $produksiTembeltriplek): bool
    {
        return $authUser->can('ForceDelete:ProduksiTembeltriplek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiTembeltriplek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiTembeltriplek');
    }

    public function replicate(AuthUser $authUser, ProduksiTembeltriplek $produksiTembeltriplek): bool
    {
        return $authUser->can('Replicate:ProduksiTembeltriplek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiTembeltriplek');
    }

}