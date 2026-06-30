<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiRepair;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiRepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiRepair');
    }

    public function view(AuthUser $authUser, PegawaiRepair $pegawaiRepair): bool
    {
        return $authUser->can('View:PegawaiRepair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiRepair');
    }

    public function update(AuthUser $authUser, PegawaiRepair $pegawaiRepair): bool
    {
        return $authUser->can('Update:PegawaiRepair');
    }

    public function delete(AuthUser $authUser, PegawaiRepair $pegawaiRepair): bool
    {
        return $authUser->can('Delete:PegawaiRepair');
    }

    public function restore(AuthUser $authUser, PegawaiRepair $pegawaiRepair): bool
    {
        return $authUser->can('Restore:PegawaiRepair');
    }

    public function forceDelete(AuthUser $authUser, PegawaiRepair $pegawaiRepair): bool
    {
        return $authUser->can('ForceDelete:PegawaiRepair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiRepair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiRepair');
    }

    public function replicate(AuthUser $authUser, PegawaiRepair $pegawaiRepair): bool
    {
        return $authUser->can('Replicate:PegawaiRepair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiRepair');
    }

}