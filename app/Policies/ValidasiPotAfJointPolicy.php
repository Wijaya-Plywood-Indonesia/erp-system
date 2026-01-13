<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiPotAfJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiPotAfJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiPotAfJoint');
    }

    public function view(AuthUser $authUser, ValidasiPotAfJoint $validasiPotAfJoint): bool
    {
        return $authUser->can('View:ValidasiPotAfJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiPotAfJoint');
    }

    public function update(AuthUser $authUser, ValidasiPotAfJoint $validasiPotAfJoint): bool
    {
        return $authUser->can('Update:ValidasiPotAfJoint');
    }

    public function delete(AuthUser $authUser, ValidasiPotAfJoint $validasiPotAfJoint): bool
    {
        return $authUser->can('Delete:ValidasiPotAfJoint');
    }

    public function restore(AuthUser $authUser, ValidasiPotAfJoint $validasiPotAfJoint): bool
    {
        return $authUser->can('Restore:ValidasiPotAfJoint');
    }

    public function forceDelete(AuthUser $authUser, ValidasiPotAfJoint $validasiPotAfJoint): bool
    {
        return $authUser->can('ForceDelete:ValidasiPotAfJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiPotAfJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiPotAfJoint');
    }

    public function replicate(AuthUser $authUser, ValidasiPotAfJoint $validasiPotAfJoint): bool
    {
        return $authUser->can('Replicate:ValidasiPotAfJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiPotAfJoint');
    }

}