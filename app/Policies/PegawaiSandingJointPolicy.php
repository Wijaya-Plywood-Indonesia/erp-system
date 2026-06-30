<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiSandingJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiSandingJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiSandingJoint');
    }

    public function view(AuthUser $authUser, PegawaiSandingJoint $pegawaiSandingJoint): bool
    {
        return $authUser->can('View:PegawaiSandingJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiSandingJoint');
    }

    public function update(AuthUser $authUser, PegawaiSandingJoint $pegawaiSandingJoint): bool
    {
        return $authUser->can('Update:PegawaiSandingJoint');
    }

    public function delete(AuthUser $authUser, PegawaiSandingJoint $pegawaiSandingJoint): bool
    {
        return $authUser->can('Delete:PegawaiSandingJoint');
    }

    public function restore(AuthUser $authUser, PegawaiSandingJoint $pegawaiSandingJoint): bool
    {
        return $authUser->can('Restore:PegawaiSandingJoint');
    }

    public function forceDelete(AuthUser $authUser, PegawaiSandingJoint $pegawaiSandingJoint): bool
    {
        return $authUser->can('ForceDelete:PegawaiSandingJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiSandingJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiSandingJoint');
    }

    public function replicate(AuthUser $authUser, PegawaiSandingJoint $pegawaiSandingJoint): bool
    {
        return $authUser->can('Replicate:PegawaiSandingJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiSandingJoint');
    }

}