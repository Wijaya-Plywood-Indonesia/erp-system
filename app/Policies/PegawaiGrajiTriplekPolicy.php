<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiGrajiTriplek;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiGrajiTriplekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiGrajiTriplek');
    }

    public function view(AuthUser $authUser, PegawaiGrajiTriplek $pegawaiGrajiTriplek): bool
    {
        return $authUser->can('View:PegawaiGrajiTriplek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiGrajiTriplek');
    }

    public function update(AuthUser $authUser, PegawaiGrajiTriplek $pegawaiGrajiTriplek): bool
    {
        return $authUser->can('Update:PegawaiGrajiTriplek');
    }

    public function delete(AuthUser $authUser, PegawaiGrajiTriplek $pegawaiGrajiTriplek): bool
    {
        return $authUser->can('Delete:PegawaiGrajiTriplek');
    }

    public function restore(AuthUser $authUser, PegawaiGrajiTriplek $pegawaiGrajiTriplek): bool
    {
        return $authUser->can('Restore:PegawaiGrajiTriplek');
    }

    public function forceDelete(AuthUser $authUser, PegawaiGrajiTriplek $pegawaiGrajiTriplek): bool
    {
        return $authUser->can('ForceDelete:PegawaiGrajiTriplek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiGrajiTriplek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiGrajiTriplek');
    }

    public function replicate(AuthUser $authUser, PegawaiGrajiTriplek $pegawaiGrajiTriplek): bool
    {
        return $authUser->can('Replicate:PegawaiGrajiTriplek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiGrajiTriplek');
    }

}