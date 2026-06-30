<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiJoint');
    }

    public function view(AuthUser $authUser, PegawaiJoint $pegawaiJoint): bool
    {
        return $authUser->can('View:PegawaiJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiJoint');
    }

    public function update(AuthUser $authUser, PegawaiJoint $pegawaiJoint): bool
    {
        return $authUser->can('Update:PegawaiJoint');
    }

    public function delete(AuthUser $authUser, PegawaiJoint $pegawaiJoint): bool
    {
        return $authUser->can('Delete:PegawaiJoint');
    }

    public function restore(AuthUser $authUser, PegawaiJoint $pegawaiJoint): bool
    {
        return $authUser->can('Restore:PegawaiJoint');
    }

    public function forceDelete(AuthUser $authUser, PegawaiJoint $pegawaiJoint): bool
    {
        return $authUser->can('ForceDelete:PegawaiJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiJoint');
    }

    public function replicate(AuthUser $authUser, PegawaiJoint $pegawaiJoint): bool
    {
        return $authUser->can('Replicate:PegawaiJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiJoint');
    }

}