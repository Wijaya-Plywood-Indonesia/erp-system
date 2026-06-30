<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LaporanProduksi;
use Illuminate\Auth\Access\HandlesAuthorization;

class LaporanProduksiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LaporanProduksi');
    }

    public function view(AuthUser $authUser, LaporanProduksi $laporanProduksi): bool
    {
        return $authUser->can('View:LaporanProduksi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LaporanProduksi');
    }

    public function update(AuthUser $authUser, LaporanProduksi $laporanProduksi): bool
    {
        return $authUser->can('Update:LaporanProduksi');
    }

    public function delete(AuthUser $authUser, LaporanProduksi $laporanProduksi): bool
    {
        return $authUser->can('Delete:LaporanProduksi');
    }

    public function restore(AuthUser $authUser, LaporanProduksi $laporanProduksi): bool
    {
        return $authUser->can('Restore:LaporanProduksi');
    }

    public function forceDelete(AuthUser $authUser, LaporanProduksi $laporanProduksi): bool
    {
        return $authUser->can('ForceDelete:LaporanProduksi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LaporanProduksi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LaporanProduksi');
    }

    public function replicate(AuthUser $authUser, LaporanProduksi $laporanProduksi): bool
    {
        return $authUser->can('Replicate:LaporanProduksi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LaporanProduksi');
    }

}