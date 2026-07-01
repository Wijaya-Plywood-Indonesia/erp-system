<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReferensiHargaProduksi;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReferensiHargaProduksiPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReferensiHargaProduksi');
    }

    public function view(AuthUser $authUser, ReferensiHargaProduksi $referensiHargaProduksi): bool
    {
        return $authUser->can('View:ReferensiHargaProduksi');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReferensiHargaProduksi');
    }

    public function update(AuthUser $authUser, ReferensiHargaProduksi $referensiHargaProduksi): bool
    {
        return $authUser->can('Update:ReferensiHargaProduksi');
    }

    public function delete(AuthUser $authUser, ReferensiHargaProduksi $referensiHargaProduksi): bool
    {
        return $authUser->can('Delete:ReferensiHargaProduksi');
    }

    public function restore(AuthUser $authUser, ReferensiHargaProduksi $referensiHargaProduksi): bool
    {
        return $authUser->can('Restore:ReferensiHargaProduksi');
    }

    public function forceDelete(AuthUser $authUser, ReferensiHargaProduksi $referensiHargaProduksi): bool
    {
        return $authUser->can('ForceDelete:ReferensiHargaProduksi');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReferensiHargaProduksi');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReferensiHargaProduksi');
    }

    public function replicate(AuthUser $authUser, ReferensiHargaProduksi $referensiHargaProduksi): bool
    {
        return $authUser->can('Replicate:ReferensiHargaProduksi');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReferensiHargaProduksi');
    }

}