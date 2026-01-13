<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\LainLain;
use Illuminate\Auth\Access\HandlesAuthorization;

class LainLainPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:LainLain');
    }

    public function view(AuthUser $authUser, LainLain $lainLain): bool
    {
        return $authUser->can('View:LainLain');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:LainLain');
    }

    public function update(AuthUser $authUser, LainLain $lainLain): bool
    {
        return $authUser->can('Update:LainLain');
    }

    public function delete(AuthUser $authUser, LainLain $lainLain): bool
    {
        return $authUser->can('Delete:LainLain');
    }

    public function restore(AuthUser $authUser, LainLain $lainLain): bool
    {
        return $authUser->can('Restore:LainLain');
    }

    public function forceDelete(AuthUser $authUser, LainLain $lainLain): bool
    {
        return $authUser->can('ForceDelete:LainLain');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:LainLain');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:LainLain');
    }

    public function replicate(AuthUser $authUser, LainLain $lainLain): bool
    {
        return $authUser->can('Replicate:LainLain');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:LainLain');
    }

}