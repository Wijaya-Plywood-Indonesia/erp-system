<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiSanding;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiSandingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiSanding');
    }

    public function view(AuthUser $authUser, ProduksiSanding $produksiSanding): bool
    {
        return $authUser->can('View:ProduksiSanding');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiSanding');
    }

    public function update(AuthUser $authUser, ProduksiSanding $produksiSanding): bool
    {
        return $authUser->can('Update:ProduksiSanding');
    }

    public function delete(AuthUser $authUser, ProduksiSanding $produksiSanding): bool
    {
        return $authUser->can('Delete:ProduksiSanding');
    }

    public function restore(AuthUser $authUser, ProduksiSanding $produksiSanding): bool
    {
        return $authUser->can('Restore:ProduksiSanding');
    }

    public function forceDelete(AuthUser $authUser, ProduksiSanding $produksiSanding): bool
    {
        return $authUser->can('ForceDelete:ProduksiSanding');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiSanding');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiSanding');
    }

    public function replicate(AuthUser $authUser, ProduksiSanding $produksiSanding): bool
    {
        return $authUser->can('Replicate:ProduksiSanding');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiSanding');
    }

}