<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Repair;
use Illuminate\Auth\Access\HandlesAuthorization;

class RepairPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Repair');
    }

    public function view(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('View:Repair');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Repair');
    }

    public function update(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('Update:Repair');
    }

    public function delete(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('Delete:Repair');
    }

    public function restore(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('Restore:Repair');
    }

    public function forceDelete(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('ForceDelete:Repair');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Repair');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Repair');
    }

    public function replicate(AuthUser $authUser, Repair $repair): bool
    {
        return $authUser->can('Replicate:Repair');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Repair');
    }

}