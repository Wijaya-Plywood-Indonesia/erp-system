<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiJoint');
    }

    public function view(AuthUser $authUser, ValidasiJoint $validasiJoint): bool
    {
        return $authUser->can('View:ValidasiJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiJoint');
    }

    public function update(AuthUser $authUser, ValidasiJoint $validasiJoint): bool
    {
        return $authUser->can('Update:ValidasiJoint');
    }

    public function delete(AuthUser $authUser, ValidasiJoint $validasiJoint): bool
    {
        return $authUser->can('Delete:ValidasiJoint');
    }

    public function restore(AuthUser $authUser, ValidasiJoint $validasiJoint): bool
    {
        return $authUser->can('Restore:ValidasiJoint');
    }

    public function forceDelete(AuthUser $authUser, ValidasiJoint $validasiJoint): bool
    {
        return $authUser->can('ForceDelete:ValidasiJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiJoint');
    }

    public function replicate(AuthUser $authUser, ValidasiJoint $validasiJoint): bool
    {
        return $authUser->can('Replicate:ValidasiJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiJoint');
    }

}