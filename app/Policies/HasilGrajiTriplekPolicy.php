<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilGrajiTriplek;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilGrajiTriplekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilGrajiTriplek');
    }

    public function view(AuthUser $authUser, HasilGrajiTriplek $hasilGrajiTriplek): bool
    {
        return $authUser->can('View:HasilGrajiTriplek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilGrajiTriplek');
    }

    public function update(AuthUser $authUser, HasilGrajiTriplek $hasilGrajiTriplek): bool
    {
        return $authUser->can('Update:HasilGrajiTriplek');
    }

    public function delete(AuthUser $authUser, HasilGrajiTriplek $hasilGrajiTriplek): bool
    {
        return $authUser->can('Delete:HasilGrajiTriplek');
    }

    public function restore(AuthUser $authUser, HasilGrajiTriplek $hasilGrajiTriplek): bool
    {
        return $authUser->can('Restore:HasilGrajiTriplek');
    }

    public function forceDelete(AuthUser $authUser, HasilGrajiTriplek $hasilGrajiTriplek): bool
    {
        return $authUser->can('ForceDelete:HasilGrajiTriplek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilGrajiTriplek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilGrajiTriplek');
    }

    public function replicate(AuthUser $authUser, HasilGrajiTriplek $hasilGrajiTriplek): bool
    {
        return $authUser->can('Replicate:HasilGrajiTriplek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilGrajiTriplek');
    }

}