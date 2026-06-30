<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SupplierKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class SupplierKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SupplierKayu');
    }

    public function view(AuthUser $authUser, SupplierKayu $supplierKayu): bool
    {
        return $authUser->can('View:SupplierKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SupplierKayu');
    }

    public function update(AuthUser $authUser, SupplierKayu $supplierKayu): bool
    {
        return $authUser->can('Update:SupplierKayu');
    }

    public function delete(AuthUser $authUser, SupplierKayu $supplierKayu): bool
    {
        return $authUser->can('Delete:SupplierKayu');
    }

    public function restore(AuthUser $authUser, SupplierKayu $supplierKayu): bool
    {
        return $authUser->can('Restore:SupplierKayu');
    }

    public function forceDelete(AuthUser $authUser, SupplierKayu $supplierKayu): bool
    {
        return $authUser->can('ForceDelete:SupplierKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SupplierKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SupplierKayu');
    }

    public function replicate(AuthUser $authUser, SupplierKayu $supplierKayu): bool
    {
        return $authUser->can('Replicate:SupplierKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SupplierKayu');
    }

}