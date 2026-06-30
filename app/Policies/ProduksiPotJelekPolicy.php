<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiPotJelek;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiPotJelekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiPotJelek');
    }

    public function view(AuthUser $authUser, ProduksiPotJelek $produksiPotJelek): bool
    {
        return $authUser->can('View:ProduksiPotJelek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiPotJelek');
    }

    public function update(AuthUser $authUser, ProduksiPotJelek $produksiPotJelek): bool
    {
        return $authUser->can('Update:ProduksiPotJelek');
    }

    public function delete(AuthUser $authUser, ProduksiPotJelek $produksiPotJelek): bool
    {
        return $authUser->can('Delete:ProduksiPotJelek');
    }

    public function restore(AuthUser $authUser, ProduksiPotJelek $produksiPotJelek): bool
    {
        return $authUser->can('Restore:ProduksiPotJelek');
    }

    public function forceDelete(AuthUser $authUser, ProduksiPotJelek $produksiPotJelek): bool
    {
        return $authUser->can('ForceDelete:ProduksiPotJelek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiPotJelek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiPotJelek');
    }

    public function replicate(AuthUser $authUser, ProduksiPotJelek $produksiPotJelek): bool
    {
        return $authUser->can('Replicate:ProduksiPotJelek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiPotJelek');
    }

}