<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiRotary');
    }

    public function view(AuthUser $authUser, ProduksiRotary $produksiRotary): bool
    {
        return $authUser->can('View:ProduksiRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiRotary');
    }

    public function update(AuthUser $authUser, ProduksiRotary $produksiRotary): bool
    {
        return $authUser->can('Update:ProduksiRotary');
    }

    public function delete(AuthUser $authUser, ProduksiRotary $produksiRotary): bool
    {
        return $authUser->can('Delete:ProduksiRotary');
    }

    public function restore(AuthUser $authUser, ProduksiRotary $produksiRotary): bool
    {
        return $authUser->can('Restore:ProduksiRotary');
    }

    public function forceDelete(AuthUser $authUser, ProduksiRotary $produksiRotary): bool
    {
        return $authUser->can('ForceDelete:ProduksiRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiRotary');
    }

    public function replicate(AuthUser $authUser, ProduksiRotary $produksiRotary): bool
    {
        return $authUser->can('Replicate:ProduksiRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiRotary');
    }

}