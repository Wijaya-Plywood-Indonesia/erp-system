<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ModalJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class ModalJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ModalJoint');
    }

    public function view(AuthUser $authUser, ModalJoint $modalJoint): bool
    {
        return $authUser->can('View:ModalJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ModalJoint');
    }

    public function update(AuthUser $authUser, ModalJoint $modalJoint): bool
    {
        return $authUser->can('Update:ModalJoint');
    }

    public function delete(AuthUser $authUser, ModalJoint $modalJoint): bool
    {
        return $authUser->can('Delete:ModalJoint');
    }

    public function restore(AuthUser $authUser, ModalJoint $modalJoint): bool
    {
        return $authUser->can('Restore:ModalJoint');
    }

    public function forceDelete(AuthUser $authUser, ModalJoint $modalJoint): bool
    {
        return $authUser->can('ForceDelete:ModalJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ModalJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ModalJoint');
    }

    public function replicate(AuthUser $authUser, ModalJoint $modalJoint): bool
    {
        return $authUser->can('Replicate:ModalJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ModalJoint');
    }

}