<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Criteria;
use Illuminate\Auth\Access\HandlesAuthorization;

class CriteriaPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Criteria');
    }

    public function view(AuthUser $authUser, Criteria $criteria): bool
    {
        return $authUser->can('View:Criteria');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Criteria');
    }

    public function update(AuthUser $authUser, Criteria $criteria): bool
    {
        return $authUser->can('Update:Criteria');
    }

    public function delete(AuthUser $authUser, Criteria $criteria): bool
    {
        return $authUser->can('Delete:Criteria');
    }

    public function restore(AuthUser $authUser, Criteria $criteria): bool
    {
        return $authUser->can('Restore:Criteria');
    }

    public function forceDelete(AuthUser $authUser, Criteria $criteria): bool
    {
        return $authUser->can('ForceDelete:Criteria');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Criteria');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Criteria');
    }

    public function replicate(AuthUser $authUser, Criteria $criteria): bool
    {
        return $authUser->can('Replicate:Criteria');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Criteria');
    }

}