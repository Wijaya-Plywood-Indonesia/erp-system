<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiNyusup;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiNyusupPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiNyusup');
    }

    public function view(AuthUser $authUser, ProduksiNyusup $produksiNyusup): bool
    {
        return $authUser->can('View:ProduksiNyusup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiNyusup');
    }

    public function update(AuthUser $authUser, ProduksiNyusup $produksiNyusup): bool
    {
        return $authUser->can('Update:ProduksiNyusup');
    }

    public function delete(AuthUser $authUser, ProduksiNyusup $produksiNyusup): bool
    {
        return $authUser->can('Delete:ProduksiNyusup');
    }

    public function restore(AuthUser $authUser, ProduksiNyusup $produksiNyusup): bool
    {
        return $authUser->can('Restore:ProduksiNyusup');
    }

    public function forceDelete(AuthUser $authUser, ProduksiNyusup $produksiNyusup): bool
    {
        return $authUser->can('ForceDelete:ProduksiNyusup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiNyusup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiNyusup');
    }

    public function replicate(AuthUser $authUser, ProduksiNyusup $produksiNyusup): bool
    {
        return $authUser->can('Replicate:ProduksiNyusup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiNyusup');
    }

}