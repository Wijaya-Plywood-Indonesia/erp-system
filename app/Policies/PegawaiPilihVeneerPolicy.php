<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiPilihVeneer;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiPilihVeneerPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiPilihVeneer');
    }

    public function view(AuthUser $authUser, PegawaiPilihVeneer $pegawaiPilihVeneer): bool
    {
        return $authUser->can('View:PegawaiPilihVeneer');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiPilihVeneer');
    }

    public function update(AuthUser $authUser, PegawaiPilihVeneer $pegawaiPilihVeneer): bool
    {
        return $authUser->can('Update:PegawaiPilihVeneer');
    }

    public function delete(AuthUser $authUser, PegawaiPilihVeneer $pegawaiPilihVeneer): bool
    {
        return $authUser->can('Delete:PegawaiPilihVeneer');
    }

    public function restore(AuthUser $authUser, PegawaiPilihVeneer $pegawaiPilihVeneer): bool
    {
        return $authUser->can('Restore:PegawaiPilihVeneer');
    }

    public function forceDelete(AuthUser $authUser, PegawaiPilihVeneer $pegawaiPilihVeneer): bool
    {
        return $authUser->can('ForceDelete:PegawaiPilihVeneer');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiPilihVeneer');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiPilihVeneer');
    }

    public function replicate(AuthUser $authUser, PegawaiPilihVeneer $pegawaiPilihVeneer): bool
    {
        return $authUser->can('Replicate:PegawaiPilihVeneer');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiPilihVeneer');
    }

}