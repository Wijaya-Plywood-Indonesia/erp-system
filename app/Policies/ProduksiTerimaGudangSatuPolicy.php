<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiTerimaGudangSatu;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiTerimaGudangSatuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiTerimaGudangSatu');
    }

    public function view(AuthUser $authUser, ProduksiTerimaGudangSatu $produksiTerimaGudangSatu): bool
    {
        return $authUser->can('View:ProduksiTerimaGudangSatu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiTerimaGudangSatu');
    }

    public function update(AuthUser $authUser, ProduksiTerimaGudangSatu $produksiTerimaGudangSatu): bool
    {
        return $authUser->can('Update:ProduksiTerimaGudangSatu');
    }

    public function delete(AuthUser $authUser, ProduksiTerimaGudangSatu $produksiTerimaGudangSatu): bool
    {
        return $authUser->can('Delete:ProduksiTerimaGudangSatu');
    }

    public function restore(AuthUser $authUser, ProduksiTerimaGudangSatu $produksiTerimaGudangSatu): bool
    {
        return $authUser->can('Restore:ProduksiTerimaGudangSatu');
    }

    public function forceDelete(AuthUser $authUser, ProduksiTerimaGudangSatu $produksiTerimaGudangSatu): bool
    {
        return $authUser->can('ForceDelete:ProduksiTerimaGudangSatu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiTerimaGudangSatu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiTerimaGudangSatu');
    }

    public function replicate(AuthUser $authUser, ProduksiTerimaGudangSatu $produksiTerimaGudangSatu): bool
    {
        return $authUser->can('Replicate:ProduksiTerimaGudangSatu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiTerimaGudangSatu');
    }

}