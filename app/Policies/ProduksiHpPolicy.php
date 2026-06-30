<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiHp');
    }

    public function view(AuthUser $authUser, ProduksiHp $produksiHp): bool
    {
        return $authUser->can('View:ProduksiHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiHp');
    }

    public function update(AuthUser $authUser, ProduksiHp $produksiHp): bool
    {
        return $authUser->can('Update:ProduksiHp');
    }

    public function delete(AuthUser $authUser, ProduksiHp $produksiHp): bool
    {
        return $authUser->can('Delete:ProduksiHp');
    }

    public function restore(AuthUser $authUser, ProduksiHp $produksiHp): bool
    {
        return $authUser->can('Restore:ProduksiHp');
    }

    public function forceDelete(AuthUser $authUser, ProduksiHp $produksiHp): bool
    {
        return $authUser->can('ForceDelete:ProduksiHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiHp');
    }

    public function replicate(AuthUser $authUser, ProduksiHp $produksiHp): bool
    {
        return $authUser->can('Replicate:ProduksiHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiHp');
    }

}