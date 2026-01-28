<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiPilihVeneer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiPilihVeneerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiPilihVeneer');
    }

    public function view(AuthUser $authUser, ProduksiPilihVeneer $produksiPilihVeneer): bool
    {
        return $authUser->can('View:ProduksiPilihVeneer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiPilihVeneer');
    }

    public function update(AuthUser $authUser, ProduksiPilihVeneer $produksiPilihVeneer): bool
    {
        return $authUser->can('Update:ProduksiPilihVeneer');
    }

    public function delete(AuthUser $authUser, ProduksiPilihVeneer $produksiPilihVeneer): bool
    {
        return $authUser->can('Delete:ProduksiPilihVeneer');
    }

    public function restore(AuthUser $authUser, ProduksiPilihVeneer $produksiPilihVeneer): bool
    {
        return $authUser->can('Restore:ProduksiPilihVeneer');
    }

    public function forceDelete(AuthUser $authUser, ProduksiPilihVeneer $produksiPilihVeneer): bool
    {
        return $authUser->can('ForceDelete:ProduksiPilihVeneer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiPilihVeneer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiPilihVeneer');
    }

    public function replicate(AuthUser $authUser, ProduksiPilihVeneer $produksiPilihVeneer): bool
    {
        return $authUser->can('Replicate:ProduksiPilihVeneer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiPilihVeneer');
    }

}