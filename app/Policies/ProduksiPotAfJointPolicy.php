<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiPotAfJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiPotAfJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiPotAfJoint');
    }

    public function view(AuthUser $authUser, ProduksiPotAfJoint $produksiPotAfJoint): bool
    {
        return $authUser->can('View:ProduksiPotAfJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiPotAfJoint');
    }

    public function update(AuthUser $authUser, ProduksiPotAfJoint $produksiPotAfJoint): bool
    {
        return $authUser->can('Update:ProduksiPotAfJoint');
    }

    public function delete(AuthUser $authUser, ProduksiPotAfJoint $produksiPotAfJoint): bool
    {
        return $authUser->can('Delete:ProduksiPotAfJoint');
    }

    public function restore(AuthUser $authUser, ProduksiPotAfJoint $produksiPotAfJoint): bool
    {
        return $authUser->can('Restore:ProduksiPotAfJoint');
    }

    public function forceDelete(AuthUser $authUser, ProduksiPotAfJoint $produksiPotAfJoint): bool
    {
        return $authUser->can('ForceDelete:ProduksiPotAfJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiPotAfJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiPotAfJoint');
    }

    public function replicate(AuthUser $authUser, ProduksiPotAfJoint $produksiPotAfJoint): bool
    {
        return $authUser->can('Replicate:ProduksiPotAfJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiPotAfJoint');
    }

}