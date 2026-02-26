<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\AkunGroup;
use Illuminate\Auth\Access\HandlesAuthorization;

class AkunGroupPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:AkunGroup');
    }

    public function view(AuthUser $authUser, AkunGroup $akunGroup): bool
    {
        return $authUser->can('View:AkunGroup');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:AkunGroup');
    }

    public function update(AuthUser $authUser, AkunGroup $akunGroup): bool
    {
        return $authUser->can('Update:AkunGroup');
    }

    public function delete(AuthUser $authUser, AkunGroup $akunGroup): bool
    {
        return $authUser->can('Delete:AkunGroup');
    }

    public function restore(AuthUser $authUser, AkunGroup $akunGroup): bool
    {
        return $authUser->can('Restore:AkunGroup');
    }

    public function forceDelete(AuthUser $authUser, AkunGroup $akunGroup): bool
    {
        return $authUser->can('ForceDelete:AkunGroup');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:AkunGroup');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:AkunGroup');
    }

    public function replicate(AuthUser $authUser, AkunGroup $akunGroup): bool
    {
        return $authUser->can('Replicate:AkunGroup');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:AkunGroup');
    }

}