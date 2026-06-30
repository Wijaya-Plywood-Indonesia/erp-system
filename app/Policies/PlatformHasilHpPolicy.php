<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlatformHasilHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlatformHasilHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlatformHasilHp');
    }

    public function view(AuthUser $authUser, PlatformHasilHp $platformHasilHp): bool
    {
        return $authUser->can('View:PlatformHasilHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlatformHasilHp');
    }

    public function update(AuthUser $authUser, PlatformHasilHp $platformHasilHp): bool
    {
        return $authUser->can('Update:PlatformHasilHp');
    }

    public function delete(AuthUser $authUser, PlatformHasilHp $platformHasilHp): bool
    {
        return $authUser->can('Delete:PlatformHasilHp');
    }

    public function restore(AuthUser $authUser, PlatformHasilHp $platformHasilHp): bool
    {
        return $authUser->can('Restore:PlatformHasilHp');
    }

    public function forceDelete(AuthUser $authUser, PlatformHasilHp $platformHasilHp): bool
    {
        return $authUser->can('ForceDelete:PlatformHasilHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlatformHasilHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlatformHasilHp');
    }

    public function replicate(AuthUser $authUser, PlatformHasilHp $platformHasilHp): bool
    {
        return $authUser->can('Replicate:PlatformHasilHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlatformHasilHp');
    }

}