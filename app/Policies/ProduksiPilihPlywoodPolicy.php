<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiPilihPlywood;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiPilihPlywoodPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiPilihPlywood');
    }

    public function view(AuthUser $authUser, ProduksiPilihPlywood $produksiPilihPlywood): bool
    {
        return $authUser->can('View:ProduksiPilihPlywood');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiPilihPlywood');
    }

    public function update(AuthUser $authUser, ProduksiPilihPlywood $produksiPilihPlywood): bool
    {
        return $authUser->can('Update:ProduksiPilihPlywood');
    }

    public function delete(AuthUser $authUser, ProduksiPilihPlywood $produksiPilihPlywood): bool
    {
        return $authUser->can('Delete:ProduksiPilihPlywood');
    }

    public function restore(AuthUser $authUser, ProduksiPilihPlywood $produksiPilihPlywood): bool
    {
        return $authUser->can('Restore:ProduksiPilihPlywood');
    }

    public function forceDelete(AuthUser $authUser, ProduksiPilihPlywood $produksiPilihPlywood): bool
    {
        return $authUser->can('ForceDelete:ProduksiPilihPlywood');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiPilihPlywood');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiPilihPlywood');
    }

    public function replicate(AuthUser $authUser, ProduksiPilihPlywood $produksiPilihPlywood): bool
    {
        return $authUser->can('Replicate:ProduksiPilihPlywood');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiPilihPlywood');
    }

}