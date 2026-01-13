<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiGrajitriplek;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiGrajitriplekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiGrajitriplek');
    }

    public function view(AuthUser $authUser, ProduksiGrajitriplek $produksiGrajitriplek): bool
    {
        return $authUser->can('View:ProduksiGrajitriplek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiGrajitriplek');
    }

    public function update(AuthUser $authUser, ProduksiGrajitriplek $produksiGrajitriplek): bool
    {
        return $authUser->can('Update:ProduksiGrajitriplek');
    }

    public function delete(AuthUser $authUser, ProduksiGrajitriplek $produksiGrajitriplek): bool
    {
        return $authUser->can('Delete:ProduksiGrajitriplek');
    }

    public function restore(AuthUser $authUser, ProduksiGrajitriplek $produksiGrajitriplek): bool
    {
        return $authUser->can('Restore:ProduksiGrajitriplek');
    }

    public function forceDelete(AuthUser $authUser, ProduksiGrajitriplek $produksiGrajitriplek): bool
    {
        return $authUser->can('ForceDelete:ProduksiGrajitriplek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiGrajitriplek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiGrajitriplek');
    }

    public function replicate(AuthUser $authUser, ProduksiGrajitriplek $produksiGrajitriplek): bool
    {
        return $authUser->can('Replicate:ProduksiGrajitriplek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiGrajitriplek');
    }

}