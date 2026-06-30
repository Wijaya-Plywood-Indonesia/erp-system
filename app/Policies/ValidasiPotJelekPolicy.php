<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiPotJelek;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiPotJelekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiPotJelek');
    }

    public function view(AuthUser $authUser, ValidasiPotJelek $validasiPotJelek): bool
    {
        return $authUser->can('View:ValidasiPotJelek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiPotJelek');
    }

    public function update(AuthUser $authUser, ValidasiPotJelek $validasiPotJelek): bool
    {
        return $authUser->can('Update:ValidasiPotJelek');
    }

    public function delete(AuthUser $authUser, ValidasiPotJelek $validasiPotJelek): bool
    {
        return $authUser->can('Delete:ValidasiPotJelek');
    }

    public function restore(AuthUser $authUser, ValidasiPotJelek $validasiPotJelek): bool
    {
        return $authUser->can('Restore:ValidasiPotJelek');
    }

    public function forceDelete(AuthUser $authUser, ValidasiPotJelek $validasiPotJelek): bool
    {
        return $authUser->can('ForceDelete:ValidasiPotJelek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiPotJelek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiPotJelek');
    }

    public function replicate(AuthUser $authUser, ValidasiPotJelek $validasiPotJelek): bool
    {
        return $authUser->can('Replicate:ValidasiPotJelek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiPotJelek');
    }

}