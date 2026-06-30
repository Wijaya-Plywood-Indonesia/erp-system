<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailNotaBarangMasuk;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailNotaBarangMasukPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailNotaBarangMasuk');
    }

    public function view(AuthUser $authUser, DetailNotaBarangMasuk $detailNotaBarangMasuk): bool
    {
        return $authUser->can('View:DetailNotaBarangMasuk');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailNotaBarangMasuk');
    }

    public function update(AuthUser $authUser, DetailNotaBarangMasuk $detailNotaBarangMasuk): bool
    {
        return $authUser->can('Update:DetailNotaBarangMasuk');
    }

    public function delete(AuthUser $authUser, DetailNotaBarangMasuk $detailNotaBarangMasuk): bool
    {
        return $authUser->can('Delete:DetailNotaBarangMasuk');
    }

    public function restore(AuthUser $authUser, DetailNotaBarangMasuk $detailNotaBarangMasuk): bool
    {
        return $authUser->can('Restore:DetailNotaBarangMasuk');
    }

    public function forceDelete(AuthUser $authUser, DetailNotaBarangMasuk $detailNotaBarangMasuk): bool
    {
        return $authUser->can('ForceDelete:DetailNotaBarangMasuk');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailNotaBarangMasuk');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailNotaBarangMasuk');
    }

    public function replicate(AuthUser $authUser, DetailNotaBarangMasuk $detailNotaBarangMasuk): bool
    {
        return $authUser->can('Replicate:DetailNotaBarangMasuk');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailNotaBarangMasuk');
    }

}