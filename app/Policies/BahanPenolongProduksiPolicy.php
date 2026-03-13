<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\BahanPenolongProduksi;
use Illuminate\Auth\Access\HandlesAuthorization;

class BahanPenolongProduksiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:BahanPenolongProduksi');
    }

    public function view(AuthUser $authUser, BahanPenolongProduksi $bahanPenolongProduksi): bool
    {
        return $authUser->can('View:BahanPenolongProduksi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:BahanPenolongProduksi');
    }

    public function update(AuthUser $authUser, BahanPenolongProduksi $bahanPenolongProduksi): bool
    {
        return $authUser->can('Update:BahanPenolongProduksi');
    }

    public function delete(AuthUser $authUser, BahanPenolongProduksi $bahanPenolongProduksi): bool
    {
        return $authUser->can('Delete:BahanPenolongProduksi');
    }

    public function restore(AuthUser $authUser, BahanPenolongProduksi $bahanPenolongProduksi): bool
    {
        return $authUser->can('Restore:BahanPenolongProduksi');
    }

    public function forceDelete(AuthUser $authUser, BahanPenolongProduksi $bahanPenolongProduksi): bool
    {
        return $authUser->can('ForceDelete:BahanPenolongProduksi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:BahanPenolongProduksi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:BahanPenolongProduksi');
    }

    public function replicate(AuthUser $authUser, BahanPenolongProduksi $bahanPenolongProduksi): bool
    {
        return $authUser->can('Replicate:BahanPenolongProduksi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:BahanPenolongProduksi');
    }

}