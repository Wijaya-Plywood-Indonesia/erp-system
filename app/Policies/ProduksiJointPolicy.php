<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ProduksiJoint;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProduksiJointPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ProduksiJoint');
    }

    public function view(AuthUser $authUser, ProduksiJoint $produksiJoint): bool
    {
        return $authUser->can('View:ProduksiJoint');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ProduksiJoint');
    }

    public function update(AuthUser $authUser, ProduksiJoint $produksiJoint): bool
    {
        return $authUser->can('Update:ProduksiJoint');
    }

    public function delete(AuthUser $authUser, ProduksiJoint $produksiJoint): bool
    {
        return $authUser->can('Delete:ProduksiJoint');
    }

    public function restore(AuthUser $authUser, ProduksiJoint $produksiJoint): bool
    {
        return $authUser->can('Restore:ProduksiJoint');
    }

    public function forceDelete(AuthUser $authUser, ProduksiJoint $produksiJoint): bool
    {
        return $authUser->can('ForceDelete:ProduksiJoint');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ProduksiJoint');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ProduksiJoint');
    }

    public function replicate(AuthUser $authUser, ProduksiJoint $produksiJoint): bool
    {
        return $authUser->can('Replicate:ProduksiJoint');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ProduksiJoint');
    }

}