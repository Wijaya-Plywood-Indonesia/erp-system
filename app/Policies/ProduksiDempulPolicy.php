<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiDempul;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiDempulPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiDempul');
    }

    public function view(AuthUser $authUser, ProduksiDempul $produksiDempul): bool
    {
        return $authUser->can('View:ProduksiDempul');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiDempul');
    }

    public function update(AuthUser $authUser, ProduksiDempul $produksiDempul): bool
    {
        return $authUser->can('Update:ProduksiDempul');
    }

    public function delete(AuthUser $authUser, ProduksiDempul $produksiDempul): bool
    {
        return $authUser->can('Delete:ProduksiDempul');
    }

    public function restore(AuthUser $authUser, ProduksiDempul $produksiDempul): bool
    {
        return $authUser->can('Restore:ProduksiDempul');
    }

    public function forceDelete(AuthUser $authUser, ProduksiDempul $produksiDempul): bool
    {
        return $authUser->can('ForceDelete:ProduksiDempul');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiDempul');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiDempul');
    }

    public function replicate(AuthUser $authUser, ProduksiDempul $produksiDempul): bool
    {
        return $authUser->can('Replicate:ProduksiDempul');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiDempul');
    }

}