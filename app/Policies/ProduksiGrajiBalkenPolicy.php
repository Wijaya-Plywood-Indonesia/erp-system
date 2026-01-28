<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiGrajiBalken;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiGrajiBalkenPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiGrajiBalken');
    }

    public function view(AuthUser $authUser, ProduksiGrajiBalken $produksiGrajiBalken): bool
    {
        return $authUser->can('View:ProduksiGrajiBalken');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiGrajiBalken');
    }

    public function update(AuthUser $authUser, ProduksiGrajiBalken $produksiGrajiBalken): bool
    {
        return $authUser->can('Update:ProduksiGrajiBalken');
    }

    public function delete(AuthUser $authUser, ProduksiGrajiBalken $produksiGrajiBalken): bool
    {
        return $authUser->can('Delete:ProduksiGrajiBalken');
    }

    public function restore(AuthUser $authUser, ProduksiGrajiBalken $produksiGrajiBalken): bool
    {
        return $authUser->can('Restore:ProduksiGrajiBalken');
    }

    public function forceDelete(AuthUser $authUser, ProduksiGrajiBalken $produksiGrajiBalken): bool
    {
        return $authUser->can('ForceDelete:ProduksiGrajiBalken');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiGrajiBalken');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiGrajiBalken');
    }

    public function replicate(AuthUser $authUser, ProduksiGrajiBalken $produksiGrajiBalken): bool
    {
        return $authUser->can('Replicate:ProduksiGrajiBalken');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiGrajiBalken');
    }

}