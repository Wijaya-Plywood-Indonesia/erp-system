<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PengajuanBarang;
use Illuminate\Auth\Access\HandlesAuthorization;

class PengajuanBarangPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PengajuanBarang');
    }

    public function view(AuthUser $authUser, PengajuanBarang $pengajuanBarang): bool
    {
        return $authUser->can('View:PengajuanBarang');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PengajuanBarang');
    }

    public function update(AuthUser $authUser, PengajuanBarang $pengajuanBarang): bool
    {
        return $authUser->can('Update:PengajuanBarang');
    }

    public function delete(AuthUser $authUser, PengajuanBarang $pengajuanBarang): bool
    {
        return $authUser->can('Delete:PengajuanBarang');
    }

    public function restore(AuthUser $authUser, PengajuanBarang $pengajuanBarang): bool
    {
        return $authUser->can('Restore:PengajuanBarang');
    }

    public function forceDelete(AuthUser $authUser, PengajuanBarang $pengajuanBarang): bool
    {
        return $authUser->can('ForceDelete:PengajuanBarang');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PengajuanBarang');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PengajuanBarang');
    }

    public function replicate(AuthUser $authUser, PengajuanBarang $pengajuanBarang): bool
    {
        return $authUser->can('Replicate:PengajuanBarang');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PengajuanBarang');
    }

}