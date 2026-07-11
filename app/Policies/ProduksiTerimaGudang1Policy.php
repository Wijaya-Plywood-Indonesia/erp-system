<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiTerimaGudang1;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiTerimaGudang1Policy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiTerimaGudang1');
    }

    public function view(AuthUser $authUser, ProduksiTerimaGudang1 $produksiTerimaGudang1): bool
    {
        return $authUser->can('View:ProduksiTerimaGudang1');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiTerimaGudang1');
    }

    public function update(AuthUser $authUser, ProduksiTerimaGudang1 $produksiTerimaGudang1): bool
    {
        return $authUser->can('Update:ProduksiTerimaGudang1');
    }

    public function delete(AuthUser $authUser, ProduksiTerimaGudang1 $produksiTerimaGudang1): bool
    {
        return $authUser->can('Delete:ProduksiTerimaGudang1');
    }

    public function restore(AuthUser $authUser, ProduksiTerimaGudang1 $produksiTerimaGudang1): bool
    {
        return $authUser->can('Restore:ProduksiTerimaGudang1');
    }

    public function forceDelete(AuthUser $authUser, ProduksiTerimaGudang1 $produksiTerimaGudang1): bool
    {
        return $authUser->can('ForceDelete:ProduksiTerimaGudang1');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiTerimaGudang1');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiTerimaGudang1');
    }

    public function replicate(AuthUser $authUser, ProduksiTerimaGudang1 $produksiTerimaGudang1): bool
    {
        return $authUser->can('Replicate:ProduksiTerimaGudang1');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiTerimaGudang1');
    }

}