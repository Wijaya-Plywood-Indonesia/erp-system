<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HargaVeneer;
use Illuminate\Auth\Access\HandlesAuthorization;

class HargaVeneerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HargaVeneer');
    }

    public function view(AuthUser $authUser, HargaVeneer $hargaVeneer): bool
    {
        return $authUser->can('View:HargaVeneer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HargaVeneer');
    }

    public function update(AuthUser $authUser, HargaVeneer $hargaVeneer): bool
    {
        return $authUser->can('Update:HargaVeneer');
    }

    public function delete(AuthUser $authUser, HargaVeneer $hargaVeneer): bool
    {
        return $authUser->can('Delete:HargaVeneer');
    }

    public function restore(AuthUser $authUser, HargaVeneer $hargaVeneer): bool
    {
        return $authUser->can('Restore:HargaVeneer');
    }

    public function forceDelete(AuthUser $authUser, HargaVeneer $hargaVeneer): bool
    {
        return $authUser->can('ForceDelete:HargaVeneer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HargaVeneer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HargaVeneer');
    }

    public function replicate(AuthUser $authUser, HargaVeneer $hargaVeneer): bool
    {
        return $authUser->can('Replicate:HargaVeneer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HargaVeneer');
    }

}