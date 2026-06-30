<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\VeneerBahanHp;
use Illuminate\Auth\Access\HandlesAuthorization;

class VeneerBahanHpPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:VeneerBahanHp');
    }

    public function view(AuthUser $authUser, VeneerBahanHp $veneerBahanHp): bool
    {
        return $authUser->can('View:VeneerBahanHp');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:VeneerBahanHp');
    }

    public function update(AuthUser $authUser, VeneerBahanHp $veneerBahanHp): bool
    {
        return $authUser->can('Update:VeneerBahanHp');
    }

    public function delete(AuthUser $authUser, VeneerBahanHp $veneerBahanHp): bool
    {
        return $authUser->can('Delete:VeneerBahanHp');
    }

    public function restore(AuthUser $authUser, VeneerBahanHp $veneerBahanHp): bool
    {
        return $authUser->can('Restore:VeneerBahanHp');
    }

    public function forceDelete(AuthUser $authUser, VeneerBahanHp $veneerBahanHp): bool
    {
        return $authUser->can('ForceDelete:VeneerBahanHp');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:VeneerBahanHp');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:VeneerBahanHp');
    }

    public function replicate(AuthUser $authUser, VeneerBahanHp $veneerBahanHp): bool
    {
        return $authUser->can('Replicate:VeneerBahanHp');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:VeneerBahanHp');
    }

}