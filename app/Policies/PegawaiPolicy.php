<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Pegawai;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Pegawai');
    }

    public function view(AuthUser $authUser, Pegawai $pegawai): bool
    {
        return $authUser->can('View:Pegawai');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Pegawai');
    }

    public function update(AuthUser $authUser, Pegawai $pegawai): bool
    {
        return $authUser->can('Update:Pegawai');
    }

    public function delete(AuthUser $authUser, Pegawai $pegawai): bool
    {
        return $authUser->can('Delete:Pegawai');
    }

    public function restore(AuthUser $authUser, Pegawai $pegawai): bool
    {
        return $authUser->can('Restore:Pegawai');
    }

    public function forceDelete(AuthUser $authUser, Pegawai $pegawai): bool
    {
        return $authUser->can('ForceDelete:Pegawai');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Pegawai');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Pegawai');
    }

    public function replicate(AuthUser $authUser, Pegawai $pegawai): bool
    {
        return $authUser->can('Replicate:Pegawai');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Pegawai');
    }

}