<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DetailPegawai;
use Illuminate\Auth\Access\HandlesAuthorization;

class DetailPegawaiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DetailPegawai');
    }

    public function view(AuthUser $authUser, DetailPegawai $detailPegawai): bool
    {
        return $authUser->can('View:DetailPegawai');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DetailPegawai');
    }

    public function update(AuthUser $authUser, DetailPegawai $detailPegawai): bool
    {
        return $authUser->can('Update:DetailPegawai');
    }

    public function delete(AuthUser $authUser, DetailPegawai $detailPegawai): bool
    {
        return $authUser->can('Delete:DetailPegawai');
    }

    public function restore(AuthUser $authUser, DetailPegawai $detailPegawai): bool
    {
        return $authUser->can('Restore:DetailPegawai');
    }

    public function forceDelete(AuthUser $authUser, DetailPegawai $detailPegawai): bool
    {
        return $authUser->can('ForceDelete:DetailPegawai');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DetailPegawai');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DetailPegawai');
    }

    public function replicate(AuthUser $authUser, DetailPegawai $detailPegawai): bool
    {
        return $authUser->can('Replicate:DetailPegawai');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DetailPegawai');
    }

}