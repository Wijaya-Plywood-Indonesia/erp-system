<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanProduksi;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanProduksiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanProduksi');
    }

    public function view(AuthUser $authUser, BahanProduksi $bahanProduksi): bool
    {
        return $authUser->can('View:BahanProduksi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanProduksi');
    }

    public function update(AuthUser $authUser, BahanProduksi $bahanProduksi): bool
    {
        return $authUser->can('Update:BahanProduksi');
    }

    public function delete(AuthUser $authUser, BahanProduksi $bahanProduksi): bool
    {
        return $authUser->can('Delete:BahanProduksi');
    }

    public function restore(AuthUser $authUser, BahanProduksi $bahanProduksi): bool
    {
        return $authUser->can('Restore:BahanProduksi');
    }

    public function forceDelete(AuthUser $authUser, BahanProduksi $bahanProduksi): bool
    {
        return $authUser->can('ForceDelete:BahanProduksi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanProduksi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanProduksi');
    }

    public function replicate(AuthUser $authUser, BahanProduksi $bahanProduksi): bool
    {
        return $authUser->can('Replicate:BahanProduksi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanProduksi');
    }

}