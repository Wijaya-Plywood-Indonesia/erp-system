<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiPilihVeneer;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiPilihVeneerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiPilihVeneer');
    }

    public function view(AuthUser $authUser, ValidasiPilihVeneer $validasiPilihVeneer): bool
    {
        return $authUser->can('View:ValidasiPilihVeneer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiPilihVeneer');
    }

    public function update(AuthUser $authUser, ValidasiPilihVeneer $validasiPilihVeneer): bool
    {
        return $authUser->can('Update:ValidasiPilihVeneer');
    }

    public function delete(AuthUser $authUser, ValidasiPilihVeneer $validasiPilihVeneer): bool
    {
        return $authUser->can('Delete:ValidasiPilihVeneer');
    }

    public function restore(AuthUser $authUser, ValidasiPilihVeneer $validasiPilihVeneer): bool
    {
        return $authUser->can('Restore:ValidasiPilihVeneer');
    }

    public function forceDelete(AuthUser $authUser, ValidasiPilihVeneer $validasiPilihVeneer): bool
    {
        return $authUser->can('ForceDelete:ValidasiPilihVeneer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiPilihVeneer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiPilihVeneer');
    }

    public function replicate(AuthUser $authUser, ValidasiPilihVeneer $validasiPilihVeneer): bool
    {
        return $authUser->can('Replicate:ValidasiPilihVeneer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiPilihVeneer');
    }

}