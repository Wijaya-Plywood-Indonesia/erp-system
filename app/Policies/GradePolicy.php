<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Grade;
use Illuminate\Auth\Access\HandlesAuthorization;

class GradePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Grade');
    }

    public function view(AuthUser $authUser, Grade $grade): bool
    {
        return $authUser->can('View:Grade');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Grade');
    }

    public function update(AuthUser $authUser, Grade $grade): bool
    {
        return $authUser->can('Update:Grade');
    }

    public function delete(AuthUser $authUser, Grade $grade): bool
    {
        return $authUser->can('Delete:Grade');
    }

    public function restore(AuthUser $authUser, Grade $grade): bool
    {
        return $authUser->can('Restore:Grade');
    }

    public function forceDelete(AuthUser $authUser, Grade $grade): bool
    {
        return $authUser->can('ForceDelete:Grade');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Grade');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Grade');
    }

    public function replicate(AuthUser $authUser, Grade $grade): bool
    {
        return $authUser->can('Replicate:Grade');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Grade');
    }

}