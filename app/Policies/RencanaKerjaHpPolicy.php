<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\RencanaKerjaHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class RencanaKerjaHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:RencanaKerjaHp');
    }

    public function view(AuthUser $authUser, RencanaKerjaHp $rencanaKerjaHp): bool
    {
        return $authUser->can('View:RencanaKerjaHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:RencanaKerjaHp');
    }

    public function update(AuthUser $authUser, RencanaKerjaHp $rencanaKerjaHp): bool
    {
        return $authUser->can('Update:RencanaKerjaHp');
    }

    public function delete(AuthUser $authUser, RencanaKerjaHp $rencanaKerjaHp): bool
    {
        return $authUser->can('Delete:RencanaKerjaHp');
    }

    public function restore(AuthUser $authUser, RencanaKerjaHp $rencanaKerjaHp): bool
    {
        return $authUser->can('Restore:RencanaKerjaHp');
    }

    public function forceDelete(AuthUser $authUser, RencanaKerjaHp $rencanaKerjaHp): bool
    {
        return $authUser->can('ForceDelete:RencanaKerjaHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:RencanaKerjaHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:RencanaKerjaHp');
    }

    public function replicate(AuthUser $authUser, RencanaKerjaHp $rencanaKerjaHp): bool
    {
        return $authUser->can('Replicate:RencanaKerjaHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:RencanaKerjaHp');
    }

}