<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\HasilSandingJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class HasilSandingJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:HasilSandingJoint');
    }

    public function view(AuthUser $authUser, HasilSandingJoint $hasilSandingJoint): bool
    {
        return $authUser->can('View:HasilSandingJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:HasilSandingJoint');
    }

    public function update(AuthUser $authUser, HasilSandingJoint $hasilSandingJoint): bool
    {
        return $authUser->can('Update:HasilSandingJoint');
    }

    public function delete(AuthUser $authUser, HasilSandingJoint $hasilSandingJoint): bool
    {
        return $authUser->can('Delete:HasilSandingJoint');
    }

    public function restore(AuthUser $authUser, HasilSandingJoint $hasilSandingJoint): bool
    {
        return $authUser->can('Restore:HasilSandingJoint');
    }

    public function forceDelete(AuthUser $authUser, HasilSandingJoint $hasilSandingJoint): bool
    {
        return $authUser->can('ForceDelete:HasilSandingJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:HasilSandingJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:HasilSandingJoint');
    }

    public function replicate(AuthUser $authUser, HasilSandingJoint $hasilSandingJoint): bool
    {
        return $authUser->can('Replicate:HasilSandingJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:HasilSandingJoint');
    }

}