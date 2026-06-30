<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiRotary;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiRotaryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiRotary');
    }

    public function view(AuthUser $authUser, PegawaiRotary $pegawaiRotary): bool
    {
        return $authUser->can('View:PegawaiRotary');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiRotary');
    }

    public function update(AuthUser $authUser, PegawaiRotary $pegawaiRotary): bool
    {
        return $authUser->can('Update:PegawaiRotary');
    }

    public function delete(AuthUser $authUser, PegawaiRotary $pegawaiRotary): bool
    {
        return $authUser->can('Delete:PegawaiRotary');
    }

    public function restore(AuthUser $authUser, PegawaiRotary $pegawaiRotary): bool
    {
        return $authUser->can('Restore:PegawaiRotary');
    }

    public function forceDelete(AuthUser $authUser, PegawaiRotary $pegawaiRotary): bool
    {
        return $authUser->can('ForceDelete:PegawaiRotary');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiRotary');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiRotary');
    }

    public function replicate(AuthUser $authUser, PegawaiRotary $pegawaiRotary): bool
    {
        return $authUser->can('Replicate:PegawaiRotary');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiRotary');
    }

}