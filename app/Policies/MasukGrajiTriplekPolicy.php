<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\MasukGrajiTriplek;
use Illuminate\Auth\Access\HandlesAuthorization;

class MasukGrajiTriplekPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:MasukGrajiTriplek');
    }

    public function view(AuthUser $authUser, MasukGrajiTriplek $masukGrajiTriplek): bool
    {
        return $authUser->can('View:MasukGrajiTriplek');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:MasukGrajiTriplek');
    }

    public function update(AuthUser $authUser, MasukGrajiTriplek $masukGrajiTriplek): bool
    {
        return $authUser->can('Update:MasukGrajiTriplek');
    }

    public function delete(AuthUser $authUser, MasukGrajiTriplek $masukGrajiTriplek): bool
    {
        return $authUser->can('Delete:MasukGrajiTriplek');
    }

    public function restore(AuthUser $authUser, MasukGrajiTriplek $masukGrajiTriplek): bool
    {
        return $authUser->can('Restore:MasukGrajiTriplek');
    }

    public function forceDelete(AuthUser $authUser, MasukGrajiTriplek $masukGrajiTriplek): bool
    {
        return $authUser->can('ForceDelete:MasukGrajiTriplek');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:MasukGrajiTriplek');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:MasukGrajiTriplek');
    }

    public function replicate(AuthUser $authUser, MasukGrajiTriplek $masukGrajiTriplek): bool
    {
        return $authUser->can('Replicate:MasukGrajiTriplek');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:MasukGrajiTriplek');
    }

}