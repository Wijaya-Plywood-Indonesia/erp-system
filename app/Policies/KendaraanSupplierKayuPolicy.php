<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\KendaraanSupplierKayu;
use Illuminate\Auth\Access\HandlesAuthorization;

class KendaraanSupplierKayuPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:KendaraanSupplierKayu');
    }

    public function view(AuthUser $authUser, KendaraanSupplierKayu $kendaraanSupplierKayu): bool
    {
        return $authUser->can('View:KendaraanSupplierKayu');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:KendaraanSupplierKayu');
    }

    public function update(AuthUser $authUser, KendaraanSupplierKayu $kendaraanSupplierKayu): bool
    {
        return $authUser->can('Update:KendaraanSupplierKayu');
    }

    public function delete(AuthUser $authUser, KendaraanSupplierKayu $kendaraanSupplierKayu): bool
    {
        return $authUser->can('Delete:KendaraanSupplierKayu');
    }

    public function restore(AuthUser $authUser, KendaraanSupplierKayu $kendaraanSupplierKayu): bool
    {
        return $authUser->can('Restore:KendaraanSupplierKayu');
    }

    public function forceDelete(AuthUser $authUser, KendaraanSupplierKayu $kendaraanSupplierKayu): bool
    {
        return $authUser->can('ForceDelete:KendaraanSupplierKayu');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:KendaraanSupplierKayu');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:KendaraanSupplierKayu');
    }

    public function replicate(AuthUser $authUser, KendaraanSupplierKayu $kendaraanSupplierKayu): bool
    {
        return $authUser->can('Replicate:KendaraanSupplierKayu');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:KendaraanSupplierKayu');
    }

}