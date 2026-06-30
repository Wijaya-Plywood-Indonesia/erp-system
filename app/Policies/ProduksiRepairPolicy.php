<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiRepair;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiRepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiRepair');
    }

    public function view(AuthUser $authUser, ProduksiRepair $produksiRepair): bool
    {
        return $authUser->can('View:ProduksiRepair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiRepair');
    }

    public function update(AuthUser $authUser, ProduksiRepair $produksiRepair): bool
    {
        return $authUser->can('Update:ProduksiRepair');
    }

    public function delete(AuthUser $authUser, ProduksiRepair $produksiRepair): bool
    {
        return $authUser->can('Delete:ProduksiRepair');
    }

    public function restore(AuthUser $authUser, ProduksiRepair $produksiRepair): bool
    {
        return $authUser->can('Restore:ProduksiRepair');
    }

    public function forceDelete(AuthUser $authUser, ProduksiRepair $produksiRepair): bool
    {
        return $authUser->can('ForceDelete:ProduksiRepair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiRepair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiRepair');
    }

    public function replicate(AuthUser $authUser, ProduksiRepair $produksiRepair): bool
    {
        return $authUser->can('Replicate:ProduksiRepair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiRepair');
    }

}