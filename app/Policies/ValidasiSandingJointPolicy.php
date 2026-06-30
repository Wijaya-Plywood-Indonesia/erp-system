<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ValidasiSandingJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class ValidasiSandingJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ValidasiSandingJoint');
    }

    public function view(AuthUser $authUser, ValidasiSandingJoint $validasiSandingJoint): bool
    {
        return $authUser->can('View:ValidasiSandingJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ValidasiSandingJoint');
    }

    public function update(AuthUser $authUser, ValidasiSandingJoint $validasiSandingJoint): bool
    {
        return $authUser->can('Update:ValidasiSandingJoint');
    }

    public function delete(AuthUser $authUser, ValidasiSandingJoint $validasiSandingJoint): bool
    {
        return $authUser->can('Delete:ValidasiSandingJoint');
    }

    public function restore(AuthUser $authUser, ValidasiSandingJoint $validasiSandingJoint): bool
    {
        return $authUser->can('Restore:ValidasiSandingJoint');
    }

    public function forceDelete(AuthUser $authUser, ValidasiSandingJoint $validasiSandingJoint): bool
    {
        return $authUser->can('ForceDelete:ValidasiSandingJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ValidasiSandingJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ValidasiSandingJoint');
    }

    public function replicate(AuthUser $authUser, ValidasiSandingJoint $validasiSandingJoint): bool
    {
        return $authUser->can('Replicate:ValidasiSandingJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ValidasiSandingJoint');
    }

}