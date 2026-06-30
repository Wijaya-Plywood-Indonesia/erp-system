<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiSandingJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiSandingJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiSandingJoint');
    }

    public function view(AuthUser $authUser, ProduksiSandingJoint $produksiSandingJoint): bool
    {
        return $authUser->can('View:ProduksiSandingJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiSandingJoint');
    }

    public function update(AuthUser $authUser, ProduksiSandingJoint $produksiSandingJoint): bool
    {
        return $authUser->can('Update:ProduksiSandingJoint');
    }

    public function delete(AuthUser $authUser, ProduksiSandingJoint $produksiSandingJoint): bool
    {
        return $authUser->can('Delete:ProduksiSandingJoint');
    }

    public function restore(AuthUser $authUser, ProduksiSandingJoint $produksiSandingJoint): bool
    {
        return $authUser->can('Restore:ProduksiSandingJoint');
    }

    public function forceDelete(AuthUser $authUser, ProduksiSandingJoint $produksiSandingJoint): bool
    {
        return $authUser->can('ForceDelete:ProduksiSandingJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiSandingJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiSandingJoint');
    }

    public function replicate(AuthUser $authUser, ProduksiSandingJoint $produksiSandingJoint): bool
    {
        return $authUser->can('Replicate:ProduksiSandingJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiSandingJoint');
    }

}