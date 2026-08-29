<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiPalet;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiPaletPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiPalet');
    }

    public function view(AuthUser $authUser, ProduksiPalet $produksiPalet): bool
    {
        return $authUser->can('View:ProduksiPalet');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiPalet');
    }

    public function update(AuthUser $authUser, ProduksiPalet $produksiPalet): bool
    {
        return $authUser->can('Update:ProduksiPalet');
    }

    public function delete(AuthUser $authUser, ProduksiPalet $produksiPalet): bool
    {
        return $authUser->can('Delete:ProduksiPalet');
    }

    public function restore(AuthUser $authUser, ProduksiPalet $produksiPalet): bool
    {
        return $authUser->can('Restore:ProduksiPalet');
    }

    public function forceDelete(AuthUser $authUser, ProduksiPalet $produksiPalet): bool
    {
        return $authUser->can('ForceDelete:ProduksiPalet');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiPalet');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiPalet');
    }

    public function replicate(AuthUser $authUser, ProduksiPalet $produksiPalet): bool
    {
        return $authUser->can('Replicate:ProduksiPalet');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiPalet');
    }

}