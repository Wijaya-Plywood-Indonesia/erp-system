<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\produksi_guellotine;
use Illuminate\Auth\Access\HandlesAuthorization;

class produksi_guellotinePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiGuellotine');
    }

    public function view(AuthUser $authUser, produksi_guellotine $produksiGuellotine): bool
    {
        return $authUser->can('View:ProduksiGuellotine');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiGuellotine');
    }

    public function update(AuthUser $authUser, produksi_guellotine $produksiGuellotine): bool
    {
        return $authUser->can('Update:ProduksiGuellotine');
    }

    public function delete(AuthUser $authUser, produksi_guellotine $produksiGuellotine): bool
    {
        return $authUser->can('Delete:ProduksiGuellotine');
    }

    public function restore(AuthUser $authUser, produksi_guellotine $produksiGuellotine): bool
    {
        return $authUser->can('Restore:ProduksiGuellotine');
    }

    public function forceDelete(AuthUser $authUser, produksi_guellotine $produksiGuellotine): bool
    {
        return $authUser->can('ForceDelete:ProduksiGuellotine');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiGuellotine');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiGuellotine');
    }

    public function replicate(AuthUser $authUser, produksi_guellotine $produksiGuellotine): bool
    {
        return $authUser->can('Replicate:ProduksiGuellotine');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiGuellotine');
    }

}