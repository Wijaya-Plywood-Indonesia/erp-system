<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\PegawaiPotAfJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class PegawaiPotAfJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:PegawaiPotAfJoint');
    }

    public function view(AuthUser $authUser, PegawaiPotAfJoint $pegawaiPotAfJoint): bool
    {
        return $authUser->can('View:PegawaiPotAfJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:PegawaiPotAfJoint');
    }

    public function update(AuthUser $authUser, PegawaiPotAfJoint $pegawaiPotAfJoint): bool
    {
        return $authUser->can('Update:PegawaiPotAfJoint');
    }

    public function delete(AuthUser $authUser, PegawaiPotAfJoint $pegawaiPotAfJoint): bool
    {
        return $authUser->can('Delete:PegawaiPotAfJoint');
    }

    public function restore(AuthUser $authUser, PegawaiPotAfJoint $pegawaiPotAfJoint): bool
    {
        return $authUser->can('Restore:PegawaiPotAfJoint');
    }

    public function forceDelete(AuthUser $authUser, PegawaiPotAfJoint $pegawaiPotAfJoint): bool
    {
        return $authUser->can('ForceDelete:PegawaiPotAfJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:PegawaiPotAfJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:PegawaiPotAfJoint');
    }

    public function replicate(AuthUser $authUser, PegawaiPotAfJoint $pegawaiPotAfJoint): bool
    {
        return $authUser->can('Replicate:PegawaiPotAfJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:PegawaiPotAfJoint');
    }

}