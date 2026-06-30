<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Target;
use Illuminate\Auth\Access\HandlesAuthorization;

class TargetPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Target');
    }

    public function view(AuthUser $authUser, Target $target): bool
    {
        return $authUser->can('View:Target');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Target');
    }

    public function update(AuthUser $authUser, Target $target): bool
    {
        return $authUser->can('Update:Target');
    }

    public function delete(AuthUser $authUser, Target $target): bool
    {
        return $authUser->can('Delete:Target');
    }

    public function restore(AuthUser $authUser, Target $target): bool
    {
        return $authUser->can('Restore:Target');
    }

    public function forceDelete(AuthUser $authUser, Target $target): bool
    {
        return $authUser->can('ForceDelete:Target');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Target');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Target');
    }

    public function replicate(AuthUser $authUser, Target $target): bool
    {
        return $authUser->can('Replicate:Target');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Target');
    }

}