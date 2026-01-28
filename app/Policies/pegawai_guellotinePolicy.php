<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\pegawai_guellotine;
use Illuminate\Auth\Access\HandlesAuthorization;

class pegawai_guellotinePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiGuellotine');
    }

    public function view(AuthUser $authUser, pegawai_guellotine $pegawaiGuellotine): bool
    {
        return $authUser->can('View:PegawaiGuellotine');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiGuellotine');
    }

    public function update(AuthUser $authUser, pegawai_guellotine $pegawaiGuellotine): bool
    {
        return $authUser->can('Update:PegawaiGuellotine');
    }

    public function delete(AuthUser $authUser, pegawai_guellotine $pegawaiGuellotine): bool
    {
        return $authUser->can('Delete:PegawaiGuellotine');
    }

    public function restore(AuthUser $authUser, pegawai_guellotine $pegawaiGuellotine): bool
    {
        return $authUser->can('Restore:PegawaiGuellotine');
    }

    public function forceDelete(AuthUser $authUser, pegawai_guellotine $pegawaiGuellotine): bool
    {
        return $authUser->can('ForceDelete:PegawaiGuellotine');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiGuellotine');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiGuellotine');
    }

    public function replicate(AuthUser $authUser, pegawai_guellotine $pegawaiGuellotine): bool
    {
        return $authUser->can('Replicate:PegawaiGuellotine');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiGuellotine');
    }

}