<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilPotAfJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilPotAfJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilPotAfJoint');
    }

    public function view(AuthUser $authUser, HasilPotAfJoint $hasilPotAfJoint): bool
    {
        return $authUser->can('View:HasilPotAfJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilPotAfJoint');
    }

    public function update(AuthUser $authUser, HasilPotAfJoint $hasilPotAfJoint): bool
    {
        return $authUser->can('Update:HasilPotAfJoint');
    }

    public function delete(AuthUser $authUser, HasilPotAfJoint $hasilPotAfJoint): bool
    {
        return $authUser->can('Delete:HasilPotAfJoint');
    }

    public function restore(AuthUser $authUser, HasilPotAfJoint $hasilPotAfJoint): bool
    {
        return $authUser->can('Restore:HasilPotAfJoint');
    }

    public function forceDelete(AuthUser $authUser, HasilPotAfJoint $hasilPotAfJoint): bool
    {
        return $authUser->can('ForceDelete:HasilPotAfJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilPotAfJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilPotAfJoint');
    }

    public function replicate(AuthUser $authUser, HasilPotAfJoint $hasilPotAfJoint): bool
    {
        return $authUser->can('Replicate:HasilPotAfJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilPotAfJoint');
    }

}