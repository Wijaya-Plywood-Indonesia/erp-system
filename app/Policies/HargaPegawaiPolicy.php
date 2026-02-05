<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HargaPegawai;
use Illuminate\Auth\Access\HandlesAuthorization;

class HargaPegawaiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HargaPegawai');
    }

    public function view(AuthUser $authUser, HargaPegawai $hargaPegawai): bool
    {
        return $authUser->can('View:HargaPegawai');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HargaPegawai');
    }

    public function update(AuthUser $authUser, HargaPegawai $hargaPegawai): bool
    {
        return $authUser->can('Update:HargaPegawai');
    }

    public function delete(AuthUser $authUser, HargaPegawai $hargaPegawai): bool
    {
        return $authUser->can('Delete:HargaPegawai');
    }

    public function restore(AuthUser $authUser, HargaPegawai $hargaPegawai): bool
    {
        return $authUser->can('Restore:HargaPegawai');
    }

    public function forceDelete(AuthUser $authUser, HargaPegawai $hargaPegawai): bool
    {
        return $authUser->can('ForceDelete:HargaPegawai');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HargaPegawai');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HargaPegawai');
    }

    public function replicate(AuthUser $authUser, HargaPegawai $hargaPegawai): bool
    {
        return $authUser->can('Replicate:HargaPegawai');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HargaPegawai');
    }

}