<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RencanaRepair;
use Illuminate\Auth\Access\HandlesAuthorization;

class RencanaRepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RencanaRepair');
    }

    public function view(AuthUser $authUser, RencanaRepair $rencanaRepair): bool
    {
        return $authUser->can('View:RencanaRepair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RencanaRepair');
    }

    public function update(AuthUser $authUser, RencanaRepair $rencanaRepair): bool
    {
        return $authUser->can('Update:RencanaRepair');
    }

    public function delete(AuthUser $authUser, RencanaRepair $rencanaRepair): bool
    {
        return $authUser->can('Delete:RencanaRepair');
    }

    public function restore(AuthUser $authUser, RencanaRepair $rencanaRepair): bool
    {
        return $authUser->can('Restore:RencanaRepair');
    }

    public function forceDelete(AuthUser $authUser, RencanaRepair $rencanaRepair): bool
    {
        return $authUser->can('ForceDelete:RencanaRepair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RencanaRepair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RencanaRepair');
    }

    public function replicate(AuthUser $authUser, RencanaRepair $rencanaRepair): bool
    {
        return $authUser->can('Replicate:RencanaRepair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RencanaRepair');
    }

}