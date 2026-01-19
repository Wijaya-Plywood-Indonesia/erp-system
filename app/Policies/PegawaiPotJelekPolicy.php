<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiPotJelek;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiPotJelekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiPotJelek');
    }

    public function view(AuthUser $authUser, PegawaiPotJelek $pegawaiPotJelek): bool
    {
        return $authUser->can('View:PegawaiPotJelek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiPotJelek');
    }

    public function update(AuthUser $authUser, PegawaiPotJelek $pegawaiPotJelek): bool
    {
        return $authUser->can('Update:PegawaiPotJelek');
    }

    public function delete(AuthUser $authUser, PegawaiPotJelek $pegawaiPotJelek): bool
    {
        return $authUser->can('Delete:PegawaiPotJelek');
    }

    public function restore(AuthUser $authUser, PegawaiPotJelek $pegawaiPotJelek): bool
    {
        return $authUser->can('Restore:PegawaiPotJelek');
    }

    public function forceDelete(AuthUser $authUser, PegawaiPotJelek $pegawaiPotJelek): bool
    {
        return $authUser->can('ForceDelete:PegawaiPotJelek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiPotJelek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiPotJelek');
    }

    public function replicate(AuthUser $authUser, PegawaiPotJelek $pegawaiPotJelek): bool
    {
        return $authUser->can('Replicate:PegawaiPotJelek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiPotJelek');
    }

}