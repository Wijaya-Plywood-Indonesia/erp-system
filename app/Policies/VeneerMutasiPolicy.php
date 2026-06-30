<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\VeneerMutasi;
use Illuminate\Auth\Access\HandlesAuthorization;

class VeneerMutasiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VeneerMutasi');
    }

    public function view(AuthUser $authUser, VeneerMutasi $veneerMutasi): bool
    {
        return $authUser->can('View:VeneerMutasi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VeneerMutasi');
    }

    public function update(AuthUser $authUser, VeneerMutasi $veneerMutasi): bool
    {
        return $authUser->can('Update:VeneerMutasi');
    }

    public function delete(AuthUser $authUser, VeneerMutasi $veneerMutasi): bool
    {
        return $authUser->can('Delete:VeneerMutasi');
    }

    public function restore(AuthUser $authUser, VeneerMutasi $veneerMutasi): bool
    {
        return $authUser->can('Restore:VeneerMutasi');
    }

    public function forceDelete(AuthUser $authUser, VeneerMutasi $veneerMutasi): bool
    {
        return $authUser->can('ForceDelete:VeneerMutasi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VeneerMutasi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VeneerMutasi');
    }

    public function replicate(AuthUser $authUser, VeneerMutasi $veneerMutasi): bool
    {
        return $authUser->can('Replicate:VeneerMutasi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VeneerMutasi');
    }

}