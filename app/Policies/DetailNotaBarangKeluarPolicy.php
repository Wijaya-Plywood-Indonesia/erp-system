<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailNotaBarangKeluar;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailNotaBarangKeluarPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailNotaBarangKeluar');
    }

    public function view(AuthUser $authUser, DetailNotaBarangKeluar $detailNotaBarangKeluar): bool
    {
        return $authUser->can('View:DetailNotaBarangKeluar');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailNotaBarangKeluar');
    }

    public function update(AuthUser $authUser, DetailNotaBarangKeluar $detailNotaBarangKeluar): bool
    {
        return $authUser->can('Update:DetailNotaBarangKeluar');
    }

    public function delete(AuthUser $authUser, DetailNotaBarangKeluar $detailNotaBarangKeluar): bool
    {
        return $authUser->can('Delete:DetailNotaBarangKeluar');
    }

    public function restore(AuthUser $authUser, DetailNotaBarangKeluar $detailNotaBarangKeluar): bool
    {
        return $authUser->can('Restore:DetailNotaBarangKeluar');
    }

    public function forceDelete(AuthUser $authUser, DetailNotaBarangKeluar $detailNotaBarangKeluar): bool
    {
        return $authUser->can('ForceDelete:DetailNotaBarangKeluar');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailNotaBarangKeluar');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailNotaBarangKeluar');
    }

    public function replicate(AuthUser $authUser, DetailNotaBarangKeluar $detailNotaBarangKeluar): bool
    {
        return $authUser->can('Replicate:DetailNotaBarangKeluar');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailNotaBarangKeluar');
    }

}