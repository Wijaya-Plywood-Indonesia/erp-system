<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiStik;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiStikPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiStik');
    }

    public function view(AuthUser $authUser, ProduksiStik $produksiStik): bool
    {
        return $authUser->can('View:ProduksiStik');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiStik');
    }

    public function update(AuthUser $authUser, ProduksiStik $produksiStik): bool
    {
        return $authUser->can('Update:ProduksiStik');
    }

    public function delete(AuthUser $authUser, ProduksiStik $produksiStik): bool
    {
        return $authUser->can('Delete:ProduksiStik');
    }

    public function restore(AuthUser $authUser, ProduksiStik $produksiStik): bool
    {
        return $authUser->can('Restore:ProduksiStik');
    }

    public function forceDelete(AuthUser $authUser, ProduksiStik $produksiStik): bool
    {
        return $authUser->can('ForceDelete:ProduksiStik');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiStik');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiStik');
    }

    public function replicate(AuthUser $authUser, ProduksiStik $produksiStik): bool
    {
        return $authUser->can('Replicate:ProduksiStik');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiStik');
    }

}