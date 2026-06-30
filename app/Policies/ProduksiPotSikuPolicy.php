<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiPotSiku;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiPotSikuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiPotSiku');
    }

    public function view(AuthUser $authUser, ProduksiPotSiku $produksiPotSiku): bool
    {
        return $authUser->can('View:ProduksiPotSiku');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiPotSiku');
    }

    public function update(AuthUser $authUser, ProduksiPotSiku $produksiPotSiku): bool
    {
        return $authUser->can('Update:ProduksiPotSiku');
    }

    public function delete(AuthUser $authUser, ProduksiPotSiku $produksiPotSiku): bool
    {
        return $authUser->can('Delete:ProduksiPotSiku');
    }

    public function restore(AuthUser $authUser, ProduksiPotSiku $produksiPotSiku): bool
    {
        return $authUser->can('Restore:ProduksiPotSiku');
    }

    public function forceDelete(AuthUser $authUser, ProduksiPotSiku $produksiPotSiku): bool
    {
        return $authUser->can('ForceDelete:ProduksiPotSiku');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiPotSiku');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiPotSiku');
    }

    public function replicate(AuthUser $authUser, ProduksiPotSiku $produksiPotSiku): bool
    {
        return $authUser->can('Replicate:ProduksiPotSiku');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiPotSiku');
    }

}