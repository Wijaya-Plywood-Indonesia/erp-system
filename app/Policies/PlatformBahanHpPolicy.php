<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PlatformBahanHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class PlatformBahanHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PlatformBahanHp');
    }

    public function view(AuthUser $authUser, PlatformBahanHp $platformBahanHp): bool
    {
        return $authUser->can('View:PlatformBahanHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PlatformBahanHp');
    }

    public function update(AuthUser $authUser, PlatformBahanHp $platformBahanHp): bool
    {
        return $authUser->can('Update:PlatformBahanHp');
    }

    public function delete(AuthUser $authUser, PlatformBahanHp $platformBahanHp): bool
    {
        return $authUser->can('Delete:PlatformBahanHp');
    }

    public function restore(AuthUser $authUser, PlatformBahanHp $platformBahanHp): bool
    {
        return $authUser->can('Restore:PlatformBahanHp');
    }

    public function forceDelete(AuthUser $authUser, PlatformBahanHp $platformBahanHp): bool
    {
        return $authUser->can('ForceDelete:PlatformBahanHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PlatformBahanHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PlatformBahanHp');
    }

    public function replicate(AuthUser $authUser, PlatformBahanHp $platformBahanHp): bool
    {
        return $authUser->can('Replicate:PlatformBahanHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PlatformBahanHp');
    }

}