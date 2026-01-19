<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiGrajiTriplek;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiGrajiTriplekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiGrajiTriplek');
    }

    public function view(AuthUser $authUser, ValidasiGrajiTriplek $validasiGrajiTriplek): bool
    {
        return $authUser->can('View:ValidasiGrajiTriplek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiGrajiTriplek');
    }

    public function update(AuthUser $authUser, ValidasiGrajiTriplek $validasiGrajiTriplek): bool
    {
        return $authUser->can('Update:ValidasiGrajiTriplek');
    }

    public function delete(AuthUser $authUser, ValidasiGrajiTriplek $validasiGrajiTriplek): bool
    {
        return $authUser->can('Delete:ValidasiGrajiTriplek');
    }

    public function restore(AuthUser $authUser, ValidasiGrajiTriplek $validasiGrajiTriplek): bool
    {
        return $authUser->can('Restore:ValidasiGrajiTriplek');
    }

    public function forceDelete(AuthUser $authUser, ValidasiGrajiTriplek $validasiGrajiTriplek): bool
    {
        return $authUser->can('ForceDelete:ValidasiGrajiTriplek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiGrajiTriplek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiGrajiTriplek');
    }

    public function replicate(AuthUser $authUser, ValidasiGrajiTriplek $validasiGrajiTriplek): bool
    {
        return $authUser->can('Replicate:ValidasiGrajiTriplek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiGrajiTriplek');
    }

}