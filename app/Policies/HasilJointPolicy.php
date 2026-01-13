<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilJoint');
    }

    public function view(AuthUser $authUser, HasilJoint $hasilJoint): bool
    {
        return $authUser->can('View:HasilJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilJoint');
    }

    public function update(AuthUser $authUser, HasilJoint $hasilJoint): bool
    {
        return $authUser->can('Update:HasilJoint');
    }

    public function delete(AuthUser $authUser, HasilJoint $hasilJoint): bool
    {
        return $authUser->can('Delete:HasilJoint');
    }

    public function restore(AuthUser $authUser, HasilJoint $hasilJoint): bool
    {
        return $authUser->can('Restore:HasilJoint');
    }

    public function forceDelete(AuthUser $authUser, HasilJoint $hasilJoint): bool
    {
        return $authUser->can('ForceDelete:HasilJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilJoint');
    }

    public function replicate(AuthUser $authUser, HasilJoint $hasilJoint): bool
    {
        return $authUser->can('Replicate:HasilJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilJoint');
    }

}