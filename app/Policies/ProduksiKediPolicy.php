<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiKedi;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiKediPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiKedi');
    }

    public function view(AuthUser $authUser, ProduksiKedi $produksiKedi): bool
    {
        return $authUser->can('View:ProduksiKedi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiKedi');
    }

    public function update(AuthUser $authUser, ProduksiKedi $produksiKedi): bool
    {
        return $authUser->can('Update:ProduksiKedi');
    }

    public function delete(AuthUser $authUser, ProduksiKedi $produksiKedi): bool
    {
        return $authUser->can('Delete:ProduksiKedi');
    }

    public function restore(AuthUser $authUser, ProduksiKedi $produksiKedi): bool
    {
        return $authUser->can('Restore:ProduksiKedi');
    }

    public function forceDelete(AuthUser $authUser, ProduksiKedi $produksiKedi): bool
    {
        return $authUser->can('ForceDelete:ProduksiKedi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiKedi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiKedi');
    }

    public function replicate(AuthUser $authUser, ProduksiKedi $produksiKedi): bool
    {
        return $authUser->can('Replicate:ProduksiKedi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiKedi');
    }

}